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
| `request_checkout` | Threw before FedaPay returned a usable transaction | Fully compensated — mission back to `published`, candidatures back to `pending`, `mission_payments` row deleted |
| `finalize_local` | FedaPay accepted the transaction but the local DB finalize failed | Local state compensated; a FedaPay transaction exists and is **orphaned** (`manual_recovery_required=true`) |
| `post_finalize` | Local persist succeeded and then something else threw | Local state is committed; treat as normal pending payment — do **not** rollback blindly |
| `compensate` | Compensation transaction itself failed | Local state is frozen in the tentatively-mutated shape — manual SQL recovery is required |
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

-- Inspect every candidature touched by this mission
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

The most dangerous state. Local rows were partially mutated:

- `mission.status = pending_payment`
- selected candidatures = `accepted`
- some candidatures may have been flipped to `rejected`
- a `mission_payments` row exists with no `fedapay_transaction_id`

Important: do **not** bulk-reset every `rejected` candidature on the mission. The service compensation only restores the candidature IDs touched by the failed payment attempt; older unrelated rejections must stay rejected.

Verification steps before rollback:

```sql
-- Selected candidatures attached to the failed payment
SELECT c.id, c.uuid, c.face_id, c.status, c.updated_at
FROM candidatures c
INNER JOIN mission_payment_candidatures mpc
    ON mpc.candidature_id = c.id
WHERE mpc.mission_payment_id = :payment_id
ORDER BY c.id;

-- Shortlist rejected candidatures that changed around the failure window.
-- Review these rows manually before restoring them.
SELECT id, uuid, face_id, status, updated_at
FROM candidatures
WHERE mission_id = :mission_id
  AND status = 'rejected'
  AND updated_at BETWEEN :failure_window_start AND :failure_window_end
ORDER BY updated_at, id;
```

Manual fix:

```sql
BEGIN;

-- Restore only the selected candidatures linked to this failed payment
UPDATE candidatures c
INNER JOIN mission_payment_candidatures mpc
    ON mpc.candidature_id = c.id
SET c.status = 'pending', c.updated_at = NOW()
WHERE mpc.mission_payment_id = :payment_id
  AND c.status = 'accepted';

-- Restore only the rejected candidatures you have verified were flipped by this failed attempt
UPDATE candidatures
SET status = 'pending', updated_at = NOW()
WHERE id IN (:verified_rejected_candidature_ids)
  AND status = 'rejected';

-- Restore the mission
UPDATE missions
SET status = 'published', updated_at = NOW()
WHERE id = :mission_id AND status = 'pending_payment';

-- Drop escrow stubs
DELETE FROM mission_payment_candidatures
WHERE mission_payment_id = :payment_id;

-- Drop the failed payment row
DELETE FROM mission_payments
WHERE id = :payment_id
  AND status = 'pending'
  AND fedapay_transaction_id IS NULL;

COMMIT;
```

After commit, re-open the candidature selection screen with the producer and confirm the candidate list matches the verified IDs above before asking them to retry payment.

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

**`phase=request_checkout`** — apply this section only when the main failure log shows `compensation_outcome=succeeded`. If `compensation_outcome=failed`, or you also see a `Mission payment compensation failed after initiation error` entry, stop here and switch to §4.1 — the rollback did not complete cleanly. Otherwise nothing to fix: compensation already restored the mission and the producer can retry safely.

**`phase=resume`** — resume never mutates local state, so `compensation_*` fields are not emitted on this phase. Do **not** look for `compensation_outcome`; the §4.1 escape hatch above does not apply here. Nothing to fix locally — tell the producer to retry once upstream recovers.

In both sub-cases, if retries keep failing, escalate to engineering with the original `error_class` / `error_message`.

### 4.5 — Webhook never landed but FedaPay says approved

Symptom: producer paid on FedaPay, but `mission_payments.status = pending` and `missions.status = pending_payment`.

Preferred path: ask the producer to hit resume checkout one more time. The service will self-heal (`MissionPaymentService::initiatePayment` → approved branch → `markAsPaid`). This reconciles local state and fans out the proper notifications.

If self-healing is not possible:

The guards on each `UPDATE` make this snippet safe to re-run — a concurrent webhook that wins the race will leave the row already in its target state, and these statements become no-ops instead of overwriting `paid_at` / `locked_at` or re-closing an already-closed mission.

```sql
BEGIN;

UPDATE mission_payments
SET status = 'paid', paid_at = NOW(), fedapay_ref = :fedapay_ref, updated_at = NOW()
WHERE id = :payment_id
  AND status = 'pending';

UPDATE mission_payment_candidatures
SET escrow_status = 'locked', locked_at = NOW(), updated_at = NOW()
WHERE mission_payment_id = :payment_id
  AND locked_at IS NULL;

UPDATE missions
SET status = 'closed', updated_at = NOW()
WHERE id = :mission_id
  AND status = 'pending_payment';

COMMIT;
```

After commit, manually queue the `mission_payment_confirmed` and `mission_participation_confirmation_required` notifications (or notify the users out-of-band).

## 5. Reporting incidents

For any case that required a manual SQL fix:

1. Save the grep of the relevant log lines (`payment_id`, `mission_id`, `phase`).
2. Attach the pre-rollback backup of the affected rows.
3. File an issue tagged `incident/mission-payment` linking the producer ticket.
4. Include the `error_class` and `error_message` so engineering can trace the root cause.
