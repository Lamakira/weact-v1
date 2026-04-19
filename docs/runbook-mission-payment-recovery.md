# Runbook — Manual Mission Payment Recovery

**Audience:** Operators / on-call / support staff investigating mission payment incidents where a producer cannot pay, cannot retry, or reports a mission stuck in `pending_payment`.

**Scope:** Mission payment initiation, compensation, webhook processing. This runbook does **not** cover Booking payments (separate flow in `BookingService`).

---

## 1. Failure signal — log lines

Every mission payment failure emits a structured log entry with enough context to reconstruct DB state without reading source code.

| Log message | Logged by | Level |
| --- | --- | --- |
| `Mission payment initiation failed` | `MissionPaymentService::handleInitiationFailure` | `error` |
| `Mission payment compensation failed after initiation error` | `MissionPaymentService::handleInitiationFailure` | `error` |
| `Mission payment resume failed` | `MissionPaymentService::handleResumeInitiationFailure` | `error` |
| `Mission payment declined or canceled by webhook` | `HandleFedapayWebhook::handle` | `warning` |

### Common context fields

| Field | Meaning |
| --- | --- |
| `payment_id` | `mission_payments.id` — primary key to look up the failed row |
| `mission_id` | `missions.id` — parent mission |
| `producer_id` | `producers.id` — who initiated the payment |
| `phase` | Where in the flow the failure occurred (see §2) |
| `remote_transaction_id` | FedaPay transaction id if a hosted checkout was already created — null means nothing hit FedaPay |
| `needs_compensation` | True if local state was tentatively mutated and must be restored |
| `compensation_attempted` / `compensation_failed` / `compensation_outcome` | What the service did about the mutated state |
| `manual_recovery_required` | True when a FedaPay transaction exists but local compensation restored the mission — an orphan remote payment may need operator action |
| `error_class` / `error_message` | The original Throwable that caused the failure |

## 2. Failure phases

Read `phase` first, then check `compensation_outcome` — the override below can promote the incident to a higher-severity phase.

| Phase | Meaning | Typical DB state after failure |
| --- | --- | --- |
| `request_checkout` | Threw before `requestHostedCheckout` returned a usable transaction | Local state fully compensated — mission back to `published`, `mission_payments` row deleted (`mission_payment_candidatures` entries cascade via FK). Candidatures stay `pending` throughout — they are never mutated during initiation under the FIX-20.1 contract. **⚠️ Remote edge case:** FedaPay's `Transaction::create()` succeeds *before* `generateToken()` is called (`FedapayService::initiatePaymentForMission`), so a `generateToken()` failure still throws from this phase with a remote transaction already provisioned. The log reports `manual_recovery_required=false` (we never learned the remote id) but a FedaPay-side orphan can exist — see §4.4 for the dashboard check. |
| `finalize_local` | FedaPay accepted the transaction but the local DB finalize failed | Local state compensated; a FedaPay transaction exists and is **orphaned** (`manual_recovery_required=true`) |
| `post_finalize` | Local persist succeeded and then something else threw | Local state is committed; treat as normal pending payment — do **not** rollback blindly |
| `compensate` | Compensation transaction itself failed | Orphan `mission_payments` row (no `fedapay_transaction_id`) + `mission.status = pending_payment`. Candidatures are not part of the frozen state — they stay `pending` under the FIX-20.1 contract. Manual SQL recovery required. |
| `resume` | Producer hit resume-checkout but the call to FedaPay failed | Nothing changed; producer can retry when upstream recovers (no `compensation_*` fields are emitted on this phase) |
| `webhook` | FedaPay sent a `transaction.declined` or `transaction.canceled` for a mission payment | Local state unchanged — payment stays pending, mission stays `pending_payment` until producer retries or you cancel manually |

> **Override (request_checkout / finalize_local only):** if the primary `Mission payment initiation failed` log has `compensation_outcome=failed`, or you see a paired `Mission payment compensation failed after initiation error` entry, treat the incident as **`phase=compensate`** regardless of the original phase — the rollback did not complete cleanly. This override does **not** apply to `phase=resume` (no compensation runs on resume) or `phase=webhook`.

## 3. Verification procedure

Before touching anything, confirm the current state matches what the log says.

```sql
-- Inspect the payment row
SELECT id, mission_id, producer_id, status, fedapay_transaction_id, fedapay_ref, paid_at, created_at, updated_at
FROM mission_payments
WHERE id = :payment_id;

-- Inspect the parent mission
SELECT id, uuid, status, producer_id, updated_at
FROM missions
WHERE id = :mission_id;

-- Inspect every candidature touched by this mission.
-- Under FIX-20.1 candidatures stay `pending` during initiation/compensation; this query is
-- only diagnostic POST-WEBHOOK (phase `webhook` or after `markAsPaid` has run). During
-- `phase=request_checkout`/`finalize_local`/`compensate` it will just return the full pending set.
SELECT id, uuid, face_id, status, updated_at
FROM candidatures
WHERE mission_id = :mission_id
ORDER BY id;

-- Escrow entries attached to the payment
SELECT id, candidature_id, face_id, escrow_status, montant_face_recoit
FROM mission_payment_candidatures
WHERE mission_payment_id = :payment_id;
```

Cross-check against FedaPay dashboard using `remote_transaction_id` (if present) to confirm whether a real transaction exists and what its latest status is.

## 4. Rollback procedures

Pick the procedure that matches the phase. Always run rollbacks inside a single DB transaction. Always back up the affected rows first (e.g., `mysqldump --where="id=:payment_id" mission_payments`).

### 4.1 — `phase=compensate` (compensation itself threw)

The most dangerous state. Under the FIX-20.1 contract the frozen shape is narrow — candidatures are never mutated during initiation, so they stay `pending` regardless of whether compensation completed. The only rows to clean are:

- `mission.status = pending_payment`
- an orphan `mission_payments` row exists with no `fedapay_transaction_id` (and its `mission_payment_candidatures` entries)

Use the §3 verification queries (payment row, mission row, escrow entries) to confirm the actual state before touching anything. The candidature-inspection query in §3 is uninformative here — candidatures will all still be `pending`.

Manual fix:

```sql
BEGIN;

-- Restore the mission so the producer can relaunch their selection
UPDATE missions
SET status = 'published', updated_at = NOW()
WHERE id = :mission_id AND status = 'pending_payment';

-- Belt-and-braces: explicitly drop the escrow stubs in case the FK cascade
-- is absent on a branched schema. On the standard schema the cascade on
-- `mission_payment_candidatures.mission_payment_id` makes this a no-op.
DELETE FROM mission_payment_candidatures
WHERE mission_payment_id = :payment_id;

-- Drop the failed payment row. The `fedapay_transaction_id IS NULL` guard
-- protects against stomping a row that has a real FedaPay transaction
-- attached (that case falls under §4.2).
DELETE FROM mission_payments
WHERE id = :payment_id
  AND status = 'pending'
  AND fedapay_transaction_id IS NULL;

COMMIT;
```

After commit, re-open the candidature selection screen with the producer so they can relaunch payment on a clean slate.

### 4.2 — `phase=finalize_local` with `manual_recovery_required=true`

Local state was fully compensated automatically, but a real FedaPay transaction exists with `custom_metadata.mission_payment_id` pointing at a row that **no longer exists**.

1. Open the FedaPay dashboard and locate the transaction by `remote_transaction_id`.
2. If the transaction is still `pending` → cancel it in FedaPay (no funds moved).
3. If the transaction is already `approved` → a producer paid for nothing:
   - Refund via FedaPay.
   - Notify the producer manually.
   - File an incident for the engineering team — this means FedaPay approved a payment with a stale metadata pointer. See `docs/runbook-mission-payment-recovery.md#5-reporting-incidents`.

No local SQL is needed — compensation already restored the DB.

### 4.3 — `phase=post_finalize`

Local state is committed and valid. Do **not** rollback. Treat it as a normal pending payment:

- If the producer reports nothing was charged → they can hit resume checkout.
- If the producer says they paid and the mission is still pending → webhook probably never landed. Use §4.5.

### 4.4 — `phase=request_checkout` or `phase=resume`

Both phases share the same operator action (tell the producer to retry once upstream recovers), but they reach this section through different invariants — read whichever sub-case matches the failed phase.

**`phase=request_checkout`** — apply this section only when the main failure log shows `compensation_outcome=succeeded`. If `compensation_outcome=failed`, or you also see a `Mission payment compensation failed after initiation error` entry, stop here and switch to §4.1 — the rollback did not complete cleanly. Otherwise nothing to fix locally: compensation already restored the mission and the producer can retry safely.

Before telling the producer to retry, run a **dashboard check for a remote orphan transaction**. `FedapayService::initiatePaymentForMission` calls `Transaction::create()` *before* `generateToken()`, so a `generateToken()` failure throws from this phase with a remote transaction already provisioned. The local log reports `manual_recovery_required=false` because the service never learned the remote id, but a FedaPay-side orphan can still exist:

1. Look up transactions on the FedaPay dashboard filtered by `custom_metadata.mission_payment_id = :payment_id` (the `payment_id` from the failure log). The local row is gone by now, but the metadata survives on FedaPay.
2. If the transaction is still `pending` → cancel it in FedaPay (no funds moved). Safe to proceed with producer retry.
3. If the transaction is `approved` → a producer paid for nothing. Refund via FedaPay, notify the producer manually, and file an incident (see §5) — this means the customer saw a checkout URL after all, completed it, and our local state was already wiped by compensation.
4. If no transaction matches the metadata → nothing to do; the failure really was pre-`Transaction::create()` (e.g., `firstOrFail` on the producer user, DNS error, auth failure). Producer can retry.

**`phase=resume`** — resume never mutates local state, so `compensation_*` fields are not emitted on this phase. Do **not** look for `compensation_outcome`; the §4.1 escape hatch above does not apply here. Nothing to fix locally — tell the producer to retry once upstream recovers.

In both sub-cases, if retries keep failing, escalate to engineering with the original `error_class` / `error_message`.

### 4.5 — Webhook never landed but FedaPay says approved

Symptom: producer paid on FedaPay, but `mission_payments.status = pending` and `missions.status = pending_payment`.

**Strongly preferred path — self-heal via resume checkout.** Ask the producer to hit resume checkout one more time. The service will self-heal (`MissionPaymentService::initiatePayment` → approved branch → `markAsPaid` → `applySelectionOutcomesOnPaid`). This reconciles local state, creates `Conversation` rows for accepted candidatures, and fans out every notification automatically. Manual SQL is the fallback when self-heal is impossible (e.g., the producer cannot reach the SPA).

**Manual fallback.** Under the FIX-20.1 contract, `markAsPaid` does more than flip the payment/entry/mission rows — it also calls `applySelectionOutcomesOnPaid`, which transitions candidatures, provisions conversations, and queues notifications. A manual SQL fix must reproduce every step or the mission will be stuck in a half-healed state (paid but Faces still `pending`, no `Conversation` rows, no notifications).

The guards on each `UPDATE` make this snippet safe to re-run — a concurrent webhook that wins the race will leave each row already in its target state, and these statements become no-ops instead of overwriting `paid_at` / `locked_at` / status / conversation rows.

```sql
BEGIN;

-- 1. Flip the payment to paid.
UPDATE mission_payments
SET status = 'paid', paid_at = NOW(), fedapay_ref = :fedapay_ref, updated_at = NOW()
WHERE id = :payment_id
  AND status = 'pending';

-- 2. Lock the escrow entries.
UPDATE mission_payment_candidatures
SET escrow_status = 'locked', locked_at = NOW(), updated_at = NOW()
WHERE mission_payment_id = :payment_id
  AND locked_at IS NULL;

-- 3. Close the mission.
UPDATE missions
SET status = 'closed', updated_at = NOW()
WHERE id = :mission_id
  AND status = 'pending_payment';

-- 4. Transition the selected candidatures from `pending` to `accepted`.
--    Guarded on `status = 'pending'` so a concurrent webhook race is a no-op.
UPDATE candidatures
SET status = 'accepted', updated_at = NOW()
WHERE id IN (
    SELECT candidature_id FROM mission_payment_candidatures
    WHERE mission_payment_id = :payment_id
)
  AND status = 'pending';

-- 5. Transition the remaining `pending` candidatures on the same mission to `rejected`.
--    CRITICAL: filter must match `applySelectionOutcomesOnPaid` exactly. Do NOT widen this
--    to "every non-selected Face on the mission" — previously-rejected and `cancelled`
--    rows stay untouched.
UPDATE candidatures
SET status = 'rejected', updated_at = NOW()
WHERE mission_id = :mission_id
  AND status = 'pending'
  AND id NOT IN (
      SELECT candidature_id FROM mission_payment_candidatures
      WHERE mission_payment_id = :payment_id
  );

-- 6. Provision a `Conversation` row per newly-accepted candidature. Idempotent via the
--    unique key on `conversations.candidature_id` — a replay is a no-op.
INSERT INTO conversations (candidature_id, created_at, updated_at)
SELECT mpc.candidature_id, NOW(), NOW()
FROM mission_payment_candidatures mpc
WHERE mpc.mission_payment_id = :payment_id
ON DUPLICATE KEY UPDATE conversations.candidature_id = conversations.candidature_id;

COMMIT;
```

After commit, manually queue the three notification types `applySelectionOutcomesOnPaid` + `markAsPaid` would have dispatched (or notify the users out-of-band):

1. `mission_payment_confirmed` → to the producer's user.
2. `mission_participation_confirmation_required` → one per selected Face (query `SELECT face_id FROM mission_payment_candidatures WHERE mission_payment_id = :payment_id`, then map to the `user_id` via `users.userable_type = 'App\Models\Face' AND userable_id = :face_id`).
3. `candidature_rejected` → one per **newly-rejected** Face, matching the filter from step 5 above. Explicitly **not** previously-rejected rows — re-notifying those would tell a Face their candidature was rejected twice.

Before queueing anything, verify the recipients actually resolve to `users` rows. `MissionPaymentService` silently skips missing recipients (`getUserIdForFace()` returns `null`; producer lookup is optional), so if a selected Face or the producer has no matching `users` row, do **not** invent a notification row by hand. Log the data-integrity gap in the incident ticket and contact that user out-of-band instead.

Notification replay is **not** idempotent. The SQL block above is safe to rerun, but `notifications` has no dedupe guard here, so on a second operator pass you must first check what was already sent (or queued) before inserting any notification rows again. If the first attempt partially succeeded, queue only the missing notifications.

These notifications are dispatched automatically by `applySelectionOutcomesOnPaid` via the service path; the manual SQL fix bypasses the service so the operator must queue them explicitly.

## 5. Reporting incidents

For any case that required a manual SQL fix:

1. Save the grep of the relevant log lines (`payment_id`, `mission_id`, `phase`).
2. Attach the pre-rollback backup of the affected rows.
3. File an issue tagged `incident/mission-payment` linking the producer ticket.
4. Include the `error_class` and `error_message` so engineering can trace the root cause.
