# Runbook — FIX-20.6 Reconcile Stale Candidature Selections

**Audience:** Operators preparing to deploy the FIX-20.1 mission-payment contract change to production.

**Scope:** One-shot data-fix Artisan command (`candidature:reconcile-stale-selections`) that rebalances candidatures left in `accepted`/`rejected` states by mission payments that never confirmed via FedaPay (no `fedapay_transaction_id`, payment status not `paid`).

**Why this exists:** Under the new FIX-20.1 contract, candidatures only transition to `accepted` once the FedaPay webhook fires `Paid`. Historical orphans created before FIX-20.1 violate this invariant and would show up as `INVARIANT_VIOLATION` log canaries (added in FIX-20.2) once FIX-20.1 ships. This command cleans the production state up under the *old* code first.

---

## 1. Sequencing

> **Run this command on production BEFORE deploying FIX-20.1 to production.**

FIX-20.1 already landed on the `dev` branch (commit `eef57c5`). It is **not** on `main` / production at the time of writing. The command must run on prod against the *old* code so that:

- The historical orphans get reconciled while the old service still understands the `Accepted = selected, payment maybe not yet paid` shape without crashing.
- After the command completes, prod is in a clean shape that the new contract (`Accepted = selection paid and confirmable`) can take over without seeing pre-existing violations.

**If FIX-20.1 has already been deployed to prod when you run this command:** that is recoverable but requires re-auditing first (the post-FIX-20.1 code path no longer creates new orphans, so the population is frozen, but any merges between the original audit and now might have shifted counts). Re-run §2 first; if counts changed materially, escalate before running `--apply`.

---

## 2. Pre-flight audit

Run both queries against the production database (or the most recent staging snapshot) and record the row counts.

```sql
-- Requête 1 — candidatures Accepted dont le MissionPayment n'est pas Paid
SELECT c.id, c.uuid, c.status, c.mission_id,
       mp.id AS payment_id, mp.status AS payment_status,
       mp.fedapay_transaction_id, mp.created_at, mp.updated_at
FROM candidatures c
JOIN mission_payment_candidatures mpc ON mpc.candidature_id = c.id
JOIN mission_payments mp ON mp.id = mpc.mission_payment_id
WHERE c.status = 'accepted'
  AND mp.status <> 'paid';

-- Requête 2 — candidatures Rejected issues d'un reject en masse sur une mission
-- dont le payment n'a jamais confirmé
SELECT c.id, c.mission_id, c.status,
       mp.id AS payment_id, mp.status AS payment_status
FROM candidatures c
JOIN missions m ON m.id = c.mission_id
JOIN mission_payments mp ON mp.mission_id = m.id
WHERE c.status = 'rejected'
  AND mp.status <> 'paid'
  AND NOT EXISTS (
    SELECT 1 FROM mission_payment_candidatures mpc
    WHERE mpc.candidature_id = c.id AND mpc.mission_payment_id = mp.id
  );
```

**Expected baseline (audit performed 2026-04-14, refreshed 2026-04-19):**

| Bucket | Count | Payments | Missions |
| --- | --- | --- | --- |
| `accepted` candidatures | 6 | mp.id ∈ {1, 2, 3, 7, 9} | mission.id ∈ {8, 11, 13, 14, 17} |
| `rejected` candidatures | 214 | (same payments) | (same missions) |
| `cancelled` candidatures (out of scope) | 6 documented | n/a | mission 8: 4, mission 11: 2 (mission 13 cancelled count not re-audited; re-check in §2) |

**Per-payment rejected breakdown:**

| payment_id | mission_id | accepted | rejected |
| --- | --- | --- | --- |
| 1 | 8 | 1 | 114 |
| 2 | 11 | 1 | 27 |
| 3 | 14 | 1 | 19 |
| 7 | 17 | 1 | 17 |
| 9 | 13 | 2 | 37 |

> **Payment 9 note (2026-04-19):** This payment was added to the scope at pre-flight time. FedaPay transaction `110554804` (created 2026-04-16) reached status **Expirée** — the producer opened the hosted checkout but never paid (0 CFA paid, no mode de paiement). Before the reconcile run the operator executed `UPDATE mission_payments SET fedapay_transaction_id = NULL WHERE id = 9;` to fold it into the command's `whereNull` discovery guard, because (a) the FedaPay transaction is terminated and will not fire any webhook, and (b) the row is semantically equivalent to the four original orphans from this point on. If you are re-reading this runbook on a future incident, **do not blindly reproduce this UPDATE** — verify the target transaction's FedaPay status is truly terminal (`Expirée`, `canceled`, `declined`) first.

**If the counts have drifted materially** (more than ±5 candidatures, or any new payment with `fedapay_transaction_id IS NULL` that is NOT in {1, 2, 3, 7, 9}), stop and escalate — the scope changed since this baseline and the story text needs adjustment before running `--apply`.

Record the actual counts before continuing:

```
date            : ____________________
operator        : ____________________
accepted count  : ____________________
rejected count  : ____________________
payment ids     : ____________________
mission ids     : ____________________
fix-20.1 deployed to prod? : yes / no
```

---

## 3. Snapshot the database

Before any write operation, take a logical snapshot you can restore from:

```bash
mysqldump -h <prod_host> -u <user> -p \
  --single-transaction --quick --routines \
  weact \
  candidatures mission_payments mission_payment_candidatures missions notifications \
  > weact-fix-20-6-pre-$(date -u +%Y%m%dT%H%M%SZ).sql
```

The five tables above are the only ones the command writes to. Restore granularity is per-table.

> The snapshot is defence-in-depth, not the normal recovery path. See §7 for why partial-state recovery does not need it.

---

## 4. Pause FedaPay webhook delivery

> **Do this before `--apply`. Skip it for `--dry-run` (no writes, no race).**

FedaPay retries webhook deliveries for up to ~24h. If a belated `approved` webhook for one of the four in-scope payments (ids 1, 2, 3, 7) lands **after** the reconcile run has flipped that payment to `failed`, the pre-FIX-20.1 production webhook handler will blindly promote it back to `paid` — but the `mission_payment_candidatures` rows are already gone, leaving the payment in an impossible state (`paid` with zero selection entries). That would strand the Producer with a charged payment and no selected Face.

**Mitigation — operator action in the FedaPay dashboard:**

1. Log into the FedaPay dashboard with an admin account.
2. Go to **Webhooks → Endpoints** (or equivalent in the current UI).
3. For each webhook endpoint pointing at the `weact` production URL, either:
   - **Disable** the endpoint (preferred — FedaPay will stop attempting redelivery while it is disabled), **or**
   - **Record** the endpoint configuration and **delete** it, then re-create it after step 6 of this runbook (more invasive; only use if disable is not available).
4. Note the five in-scope `fedapay_transaction_id` values. All five are `NULL` after the §2 pre-flight (payment 9 was nullified because its FedaPay transaction `110554804` is `Expirée`), so there are **no** pending FedaPay retries tied to these specific payments — but other unrelated payments are still active, so the pause affects the whole flow. Coordinate the window with stakeholders (producers mid-checkout during the pause will see a hanging "Redirecting to FedaPay…" step).
5. Keep the window short: target **≤ 15 minutes** between pause and re-enable. The command itself takes ~15 s on 220 rows; the bulk of the window is §5 dry-run verification + §6 apply verification.
6. **Re-enable** the webhook endpoints after §7 post-apply verification passes. Check the FedaPay dashboard for any `pending delivery` entries queued during the pause — they will attempt to deliver when the endpoint is re-enabled. Monitor the webhook handler logs for ~10 minutes after re-enable; any `status='failed'` payment that somehow receives an `approved` event must be escalated immediately (it means a webhook for an in-scope payment slipped through despite the audit).

**Alternative if FedaPay does not support pausing webhooks:**

Deploy a one-line pre-flight patch on the production webhook handler that short-circuits when `MissionPayment.status === 'failed'`:

```php
if ($payment->status === MissionPaymentStatus::Failed) {
    Log::warning('HandleFedapayWebhook: ignoring approved event for reconciled (failed) payment', [
        'payment_id' => $payment->id,
        'event' => $event->name,
    ]);
    return;
}
```

> **Important:** FIX-20.1 only adds a `Paid` idempotency guard (`if ($payment->status === MissionPaymentStatus::Paid) return $payment;` in `MissionPaymentService::markAsPaid`). It does **not** short-circuit on `Failed` state. A belated webhook `transaction.approved` arriving for a reconciled payment that still has a live `fedapay_transaction_id` would flip it to `Paid` despite the reconcile. The 2026-04-19 prod run skipped the pause safely because (a) payments 1/2/3/7 never had a `fedapay_transaction_id` and (b) payment 9's transaction `110554804` was terminal (`Expirée`) — neither case allows FedaPay to emit an `approved` event. **Any future incident where an in-scope payment still has a non-terminal FedaPay transaction must use the pause or deploy the Failed guard above.**

**Do not skip this section** unless (a) no in-scope payment has ever had a `fedapay_transaction_id`, or (b) every in-scope payment's remote transaction is verified terminal (`Expirée`, `canceled`, `declined`). Otherwise, one stray event is enough to recreate the problem the reconcile run is supposed to fix.

---

## 5. Dry-run

```bash
cd backend
php artisan candidature:reconcile-stale-selections --dry-run
```

Expected output shape (per in-scope payment, plus a final summary line):

```
Reconciling 5 in-scope payment(s) in dry-run mode.
[dry-run] payment_id=1 mission_id=8  accepted_reset=1 rejected_reset=114 entries_deleted=1 mission=reset_to_published notifications_planned=116
[dry-run] payment_id=2 mission_id=11 accepted_reset=1 rejected_reset=27  entries_deleted=1 mission=reset_to_published notifications_planned=29
[dry-run] payment_id=3 mission_id=14 accepted_reset=1 rejected_reset=19  entries_deleted=1 mission=reset_to_published notifications_planned=21
[dry-run] payment_id=7 mission_id=17 accepted_reset=1 rejected_reset=17  entries_deleted=1 mission=reset_to_published notifications_planned=19
[dry-run] payment_id=9 mission_id=13 accepted_reset=2 rejected_reset=37  entries_deleted=2 mission=reset_to_published notifications_planned=40
Done. payments_processed=5, accepted_reset=6, rejected_reset=214, entries_deleted=6, missions_reset=5, notifications_queued=0, notifications_failed=0, payments_skipped=0
```

(Per-payment counts are illustrative — match against the audit numbers from §2. The per-payment `notifications_planned=…` reflects what each payment *would* queue; the final summary's `notifications_queued=0` in dry-run is expected because the real notification fan-out only happens under `--apply`.)

**Verification before proceeding to apply:**

- All four payment IDs in the per-payment lines match the IDs in §2.
- `accepted_reset` and `rejected_reset` match the per-payment buckets you recorded.
- Each `entries_deleted=1` (one mpc row per accepted candidature).
- `mission=reset_to_published` for each — if you see `mission=skipped:already_in_expected_state` for any payment, note it but proceed; that just means a human or another job already reverted the mission state.
- `payments_skipped=0` — non-zero means a payment moved out of scope between discovery and lock (e.g. someone manually set `fedapay_transaction_id`); investigate before applying.

The dry-run uses a per-payment `DB::transaction` that throws a marker exception at the end of the closure, forcing rollback. Zero rows are written. Re-run the §2 audit afterwards as a paranoid check — counts must be unchanged.

---

## 6. Apply

```bash
cd backend
php artisan candidature:reconcile-stale-selections --apply
```

Optional: tune notification pacing with `--notifications-per-second=N` (default 20). For the production scope (~225 notifications: 6 accepted + 214 rejected + 5 producer), 20/s ≈ 12 seconds total — generally fine. Lower it if Reverb / Echo broadcast pipeline is tight.

Expected log lines (each emitted at INFO level via `Log::info`):

```
Stale selection reconcile: payment processed
  payment_id=...
  mission_id=...
  producer_id=...
  accepted_reset_count=...
  rejected_reset_count=...
  entries_deleted=...
  mission_reset=true|false
  mission_reset_skipped_reason=null|"already_in_expected_state"
  notifications_planned=...
  mode=apply
```

Dry-run runs log the same fields but under the message `Stale selection reconcile: payment previewed` with `mode=dry-run`, so grep alerts on the apply verb (`processed`) never fire on preview runs.

Console output mirrors the dry-run shape, but with `[apply]` instead of `[dry-run]` and a non-zero `notifications_queued` total in the final summary (`notifications_planned` remains per-payment).

**What the command writes (per in-scope payment, in a single DB transaction):**

1. Flips every in-scope `accepted` candidature back to `pending`.
2. Flips every in-scope `rejected` candidature back to `pending`.
3. Deletes the `mission_payment_candidatures` rows attached to the payment (only the accepted-scope rows have entries — Requête 2's `NOT EXISTS` filter guarantees the rejected scope has none, so the delete is naturally a no-op for rejected candidatures).
4. Sets `mission_payments.status = 'failed'` (the row is **kept** for financial audit traceability — never deleted).
5. Sets `missions.status = 'published'` if the mission was `pending_payment`. If the mission is in any other state (e.g. closed, draft, deleted), the mission update is skipped and an info log records `mission_status_reset_skipped: already_in_expected_state`.

**Notifications (queued AFTER each per-payment transaction commits, paced by `--notifications-per-second`):**

| Type | Recipients | Tone |
| --- | --- | --- |
| `candidature_reset_from_accepted` | One per `accepted`-scope Face | Factual, no blame |
| `candidature_reset_from_rejected` | One per `rejected`-scope Face | Positive, "second chance" |
| `mission_selection_reset_producer` | One per Producer of an in-scope mission | Action-oriented (relancer la sélection) |

A notification create failure (e.g. broken Echo broadcast, missing User row) logs a warning and continues — it never aborts the data fix or the rest of the run. Watch logs for `Stale selection reconcile: notification create failed` and `Stale selection reconcile: notification skipped — user not found`.

> **Notification replay caution:** `--apply` always queues notifications for every successfully-processed payment. If the run aborts mid-notification-fan-out and you re-run `--apply`, the second run is a no-op (the audit returns empty, no notifications) — but if you re-run only the failed payment via a manual SQL fix-up *and then* call the command, no duplicate notifications go out (the discovery query won't pick those payments up). In short: do not manually re-set candidature statuses with the intent of having the command re-notify; the command is single-shot per payment.

---

## 7. Post-apply verification

Re-run the §2 audit queries. **Both must return zero rows** — the in-scope `accepted` and `rejected` candidatures are now `pending`, so neither query matches.

```sql
-- Both should return: 0 rows.
SELECT COUNT(*) FROM candidatures c
  JOIN mission_payment_candidatures mpc ON mpc.candidature_id = c.id
  JOIN mission_payments mp ON mp.id = mpc.mission_payment_id
  WHERE c.status = 'accepted' AND mp.status <> 'paid';

SELECT COUNT(*) FROM candidatures c
  JOIN missions m ON m.id = c.mission_id
  JOIN mission_payments mp ON mp.mission_id = m.id
  WHERE c.status = 'rejected'
    AND mp.status <> 'paid'
    AND NOT EXISTS (
      SELECT 1 FROM mission_payment_candidatures mpc
      WHERE mpc.candidature_id = c.id AND mpc.mission_payment_id = mp.id
    );
```

Spot-check side effects:

```sql
-- Each in-scope payment is now `failed`.
SELECT id, status, fedapay_transaction_id FROM mission_payments WHERE id IN (1, 2, 3, 7, 9);

-- Each in-scope mission is back to `published`.
SELECT id, status FROM missions WHERE id IN (8, 11, 13, 14, 17);

-- The documented cancelled candidatures are unchanged (missions 8 and 11; mission 13 not audited, spot-check too if relevant).
SELECT id, mission_id, status FROM candidatures WHERE status = 'cancelled' AND mission_id IN (8, 11, 13);

-- Notifications were queued: expected 6 accepted + 214 rejected + 5 producer = 225.
SELECT type, COUNT(*) FROM notifications
 WHERE type IN ('candidature_reset_from_accepted', 'candidature_reset_from_rejected', 'mission_selection_reset_producer')
 GROUP BY type;
```

**Then deploy FIX-20.1 to production.** The `INVARIANT_VIOLATION` canary added in FIX-20.2 should never fire after this point. If it does, an orphan was missed by the audit — re-run §2, snapshot the new finding, and reach for §8.

**Finally, re-enable FedaPay webhook delivery** (§4 step 6). Do this only after all the checks above pass.

---

## 8. Partial-state recovery

Each payment is processed in its own `DB::transaction`. This guarantees:

- A payment is either **fully reconciled** (candidatures flipped, entries deleted, payment failed, mission reset) or **fully untouched** (transaction rolled back). There is no in-between for a single payment.
- A run that aborts halfway through the batch (network blip, manual ^C, etc.) leaves a mix of fully-reconciled and fully-untouched payments — itself a consistent state under the FIX-20 contract.

**Recovery path:** Just re-run `php artisan candidature:reconcile-stale-selections --apply`. The discovery query (§2) is idempotent: it naturally skips payments whose candidatures are already `pending` (neither query matches them anymore), so the second run picks up only the leftovers. Notifications are also not duplicated because the recovered payments are no longer in scope.

The §3 database snapshot exists as defence-in-depth — restore it only if a single payment somehow got into an inconsistent shape mid-transaction (which the per-payment transaction wrapper makes impossible by construction; the only realistic trigger is a hardware failure or an out-of-band SQL UPDATE during the run).

If a partial run aborted because of a notification fan-out failure (after the data transaction had already committed): the data is correct, the warning logs identify the failed notification recipients, and an out-of-band follow-up (Slack DM, email) is the appropriate manual fix. Do **not** try to replay the command for "missed" notifications — see the replay caution in §6.

---

## 9. Rollback

The command writes irreversibly within each transaction (sets `payment.status = 'failed'`, deletes mpc entries). To roll back:

1. Restore the §3 SQL snapshot for the five affected tables (or for individual rows if you can identify them precisely).
2. Re-audit with §2 — counts should be back to the pre-run baseline.
3. Investigate the root cause before re-running. If the original audit was wrong, fix the audit or the command and re-validate via dry-run before another `--apply`.

---

## 10. Out of scope

- **Fedapay dashboard cross-checks.** After the §2 pre-flight, all five in-scope payments have `fedapay_transaction_id IS NULL`; payment 9's original transaction `110554804` was verified `Expirée` on 2026-04-19 before the row was nullified (see §2 note). If a future audit finds any additional payment with `fedapay_transaction_id IS NOT NULL` in scope, **escalate before running** — verify the FedaPay-side status first; do not blindly nullify.
- **Email/SMS communication to affected users** (especially the mission-8 Producer who has been blocked since 2026-04-03). The command queues in-app notifications only; out-of-band human communication is a separate manual step.
- **Mission-payment service refactor.** This command does not touch `MissionPaymentService`, `HandleFedapayWebhook`, controllers, or any frontend code. Those changes are FIX-20.1's responsibility.
