# Story FIX-20.6: Réconcilier les données de production incompatibles avec le contrat cible

Status: done

## Story

As a **Platform operator preparing to deploy the FIX-20 contract change to production**,
I want **an idempotent Laravel command that rebalances the ~181 candidatures stuck in `accepted`/`rejected` states on 4 mission payments that never completed FedaPay checkout, marks those payments `Failed`, reopens the 4 missions, and notifies every affected user with actionable French messaging**,
so that **when FIX-20.1 deploys to production, it applies to a clean state — no orphan historical rows silently diverging from the new contract, no producers stuck with a `pending_payment` mission they cannot retry, and no Faces looking at an `accepted` candidature they can never confirm**.

## Acceptance Criteria

1. **Given** the production database before the command runs **When** the audit SQL from the epic (Requêtes 1 + 2, reproduced in Dev Notes) is executed **Then** it returns 4 `accepted` candidatures + 177 `rejected` candidatures spread over 4 `MissionPayment` rows (IDs 1, 2, 3, 7) on 4 missions (IDs 8, 11, 14, 17), all with `fedapay_transaction_id IS NULL`. The command must identify these rows **dynamically via the same queries** — never via a hardcoded list — so it remains correct if counts shift between audit and run.
2. **Given** a new Artisan command named `candidature:reconcile-stale-selections` (exact name) **When** invoked with `--dry-run` **Then** it prints a per-payment summary (payment id, mission id, counts of candidatures it would flip back to `pending` broken down by current status, count of escrow stubs to delete, count of notifications it would queue) and writes nothing to the database. Transactions are rolled back at the end of the dry-run to guarantee zero mutations.
3. **Given** the same command invoked with `--apply` (mutually exclusive with `--dry-run`) **When** it runs **Then** for each in-scope `MissionPayment` row it performs the following data mutations in a single DB transaction per payment: (a) flips each in-scope `accepted` candidature to `pending`; (b) flips each in-scope `rejected` candidature to `pending`; (c) deletes the `MissionPaymentCandidature` rows attached to the payment via `WHERE mission_payment_id = $payment->id` — note Requête 2's `NOT EXISTS` filter means only the accepted candidatures have `mpc` entries (4 rows total across all payments); the deletion is naturally a no-op for the rejected scope; (d) sets the `MissionPayment.status` to `failed` (do not delete — financial audit trail); (e) sets the parent `missions.status` back to `published` when it is `pending_payment`. **After** the transaction commits successfully, the command queues the three notification types described in AC #6 *outside* the transaction (per `notifySafely` semantics — a notification failure must never roll back the data fix).
4. **Given** the `cancelled` candidatures discovered during the audit (6 rows: 4 on mission 8, 2 on mission 11, 0 on missions 14/17) **When** the command runs in either mode **Then** no `cancelled` row is read, updated, or deleted. The scope filter must match the epic wording exactly: `status IN ('accepted', 'rejected')` — `cancelled` is explicitly out. This must be asserted by an explicit unit test (not just implicitly covered by the feature test).
5. **Given** the command is invoked a second time after a successful `--apply` run **When** it runs again in either mode **Then** it identifies zero candidatures and zero payments (the audit query returns empty because the statuses have been reset), writes nothing, queues no notifications, exits success. This covers both re-running apply and re-running dry-run post-apply.
6. **Given** `--apply` transitions candidatures back to `pending` **When** it queues notifications **Then** it writes three distinct notification types, matching the epic's text direction (French, correct accents). Every `data` payload mirrors the existing `candidature_rejected` shape (`message`, `mission_id`, `mission_titre`, `url`) so the existing frontend renderer's default branch falls back gracefully to `data.message` even without a per-type icon/route mapping:
   - Type `candidature_reset_from_accepted` for the 4 Faces whose candidature was `accepted`. Tone: factual, no blame. `data` = `{ message, mission_id, mission_titre, url: '/face/candidatures' }`. Message: *"Le paiement du producteur pour la mission « {mission_titre} » n'a pas été finalisé. Votre candidature a été remise en attente et pourra à nouveau être sélectionnée si le producteur relance la sélection."*
   - Type `candidature_reset_from_rejected` for the 177 Faces whose candidature was `rejected`. Tone: positive, second chance. `data` = `{ message, mission_id, mission_titre, url: '/face/candidatures' }`. Message: *"La sélection précédente pour la mission « {mission_titre} » n'a pas été finalisée. Votre candidature est de nouveau en attente et peut être retenue si le producteur relance la sélection."*
   - Type `mission_selection_reset_producer` for each of the 4 Producers whose mission was reopened. `data` = `{ message, mission_id, mission_titre, url: '/producer/missions/{mission_uuid}/candidatures' }`. Message: *"Le paiement de votre sélection pour la mission « {mission_titre} » n'a pas abouti. La mission est de nouveau ouverte aux candidatures — vous pouvez relancer votre sélection depuis la page de la mission."*
7. **Given** a notification-rate flag `--notifications-per-second=N` (default 20) **When** the command queues notifications **Then** it paces `Notification::create(...)` calls via a simple sleep-between-calls (`usleep((int) (1_000_000 / max(1, N)))` after each write) so no more than N writes happen per second, to avoid saturating the Echo/Reverb broadcast observer pipeline. The naïve sleep is sufficient — no token bucket / windowed limiter needed for a 185-row run. Pacing is skipped in `--dry-run` (it never actually creates rows).
8. **Given** a feature test `ReconcileStaleSelectionsCommandTest` **When** it seeds fixtures matching the four real-world scenarios (missions 8, 11, 14, 17 with their respective candidature counts and statuses, including the `cancelled` rows on missions 8 and 11) **Then**: (a) a `--dry-run` invocation leaves every row unchanged and the notifications table empty; (b) a subsequent `--apply` invocation flips every in-scope candidature to `pending`, marks all 4 payments `failed`, sets all 4 missions back to `published`, deletes the `MissionPaymentCandidature` entries for those payments (only the accepted-scope rows have them), leaves every `cancelled` row unchanged, and writes one notification per in-scope Face plus one per Producer, split across the three types from AC #6 with the exact French message strings (verbatim accents and guillemets); (c) a second `--apply` invocation is a no-op (writes nothing, queues nothing). Fixture sizing: the test may use scaled-down per-mission counts (e.g., 1 accepted + 3 rejected + 1 cancelled per mission) — assertions are expressed relative to the seeded counts, **not** the production 181/4/185 totals. If the test uses production-exact counts, it must assert exactly `181 + 4 = 185` notifications.
9. **Given** `docs/runbook-fix-20-6-reconcile-stale-selections.md` **When** reading it before running the command in production **Then** it documents: pre-flight audit (run the two SQL queries from the epic, record counts), DB snapshot instructions, invocation order (dry-run → human review → apply), post-run verification (re-run audit → expect zero rows), and a partial-state recovery recipe. **The recovery recipe must state explicitly** that because each payment is processed in its own transaction, a partial apply (some payments reconciled, others not) is itself a consistent state — re-running `--apply` is the recovery path (the discovery query is idempotent and naturally skips already-reconciled payments). The DB snapshot is only needed if a *single* payment's mid-transaction failure has somehow corrupted state, which the per-payment transaction wrapper makes impossible by construction. The runbook is fully self-contained (operator does not need to read the story file).
10. **Given** the sequencing constraint from the epic ("FIX-20.6 doit tourner **avant** FIX-20.1 pour que le refactor opère sur une base propre") **When** this story ships **Then** the story file + sprint-status annotation both state clearly that even though FIX-20.1 already landed on `dev` (commit `eef57c5`), FIX-20.6 must still run on **production** *before* FIX-20.1 is deployed there. If FIX-20.1 has already been deployed to prod when this command is built, the operator must document that fact in the runbook and the scope must be re-audited (orphan rows may have changed shape).
11. **Given** the backend regression suites **When** run after the code lands **Then** `cd backend && php artisan test --filter=MissionPayment` and `cd backend && php artisan test --filter=Candidature` stay green. Frontend suites and `type-check` are out of scope (no frontend files change).

## Tasks / Subtasks

- [x] Task 1: Pre-flight — confirm the audit scope still matches production reality (AC: #1, #10)
  - [x] Re-run the two audit queries from the epic against the current production snapshot (or the most recent staging copy) and record the counts in the runbook. If the numbers have drifted from `4 accepted / 177 rejected / 4 payments / 4 missions`, stop and escalate — the scope changed and the story text needs adjustment before coding.
  - [x] Confirm via `git log origin/main -- backend/app/Services/MissionPaymentService.php` whether FIX-20.1 has been deployed to main/prod yet. Record the answer in the Completion Notes. If yes, re-audit: post-FIX-20.1 code does not produce new orphans so the existing 181 count is still the full target set, but any new merges since the audit must be re-checked.
- [x] Task 2: Create the Artisan command `ReconcileStaleSelectionsCommand` (AC: #1, #2, #3, #4, #5, #7)
  - [x] Place at `backend/app/Console/Commands/ReconcileStaleSelectionsCommand.php`. Borrow the *structural* shape of `BackfillCancelledBookingWalletRefundsCommand` (per-row `DB::transaction`, structured logs, typed return codes, `declare(strict_types=1)`, etc.) but **intentionally diverge** on the option contract: this command requires explicit `--dry-run` XOR `--apply` (no default-write behavior), plus `--notifications-per-second=20`. The stricter contract is deliberate — a one-shot data-fix command must not write by accident.
  - [x] Signature guard: fail with a clear message if neither `--dry-run` nor `--apply` is given, or if both are given. Mutually exclusive.
  - [x] Discovery query: use the epic's Requête 1 + 2 as raw DB queries (joined on `mission_payment_candidatures` + `mission_payments`) to fetch the in-scope payment IDs dynamically. Do not hardcode IDs (1, 2, 3, 7). The set of `cancelled` rows must be explicitly excluded via `status IN ('accepted', 'rejected')`.
  - [x] Per-payment processing inside `DB::transaction(...)`: lock the `MissionPayment` row, flip candidatures back to `pending` in two separate `Candidature::where(...)->update(...)` statements (one scoped to `status = 'accepted'`, one to `status = 'rejected'`), delete `MissionPaymentCandidature` entries via `where('mission_payment_id', $payment->id)->delete()` (only the accepted-scope rows have entries — Requête 2's `NOT EXISTS` filter guarantees rejected rows have none, so the delete touches at most the entries for the 4 accepted candidatures), set `MissionPayment.status = 'failed'`, reset `missions.status = 'published'` when `pending_payment`. In `--dry-run` mode, throw a marker exception at the end of the closure that the outer loop catches silently so the transaction rolls back cleanly (no writes).
  - [x] Notification dispatch (after the per-payment transaction commits — never inside it): build the list of notification payloads per processed payment and feed them through a small helper that calls `Notification::create(...)` (wrapped in the same try/catch pattern as `MissionPaymentService::notifySafely`) and sleeps `usleep((int) (1_000_000 / max(1, $rate)))` after each write to respect the `--notifications-per-second` rate. Pacing is disabled in `--dry-run` (it never actually creates rows). A notification failure must log a warning and continue — it must never abort the data fix or the rest of the run.
  - [x] Structured logging at INFO level per payment with: `payment_id`, `mission_id`, `accepted_reset_count`, `rejected_reset_count`, `entries_deleted`, `notifications_queued`, `mode`.
  - [x] Idempotence guarantee: the discovery query naturally returns an empty set once statuses are reset, so re-running `--apply` after success is automatically a no-op. Add a one-line note in code confirming why.
- [x] Task 3: Wire up the three notification types (AC: #6)
  - [x] Reuse the existing `Notification::create(...)` pattern (no new table, no new model). Only the `type` string and `data` payload are new.
  - [x] Exact French strings per AC #6. Accents correct (memory rule `feedback_accents_francais`). No trailing spaces, no smart quotes — use straight double quotes with French guillemets inside (`« »`), same as the existing notification messages in `MissionPaymentService`.
  - [x] Producer notifications use `User::where('userable_type', Producer::class)->where('userable_id', $producerId)` to resolve the user id (same lookup pattern `MissionPaymentService::markAsPaid` uses for `mission_payment_confirmed`).
  - [x] Face notifications use `User::where('userable_type', Face::class)->where('userable_id', $faceId)` (same pattern as `MissionPaymentService::getUserIdForFace`).
- [x] Task 4: Write `ReconcileStaleSelectionsCommandTest` (AC: #4, #5, #8)
  - [x] Place at `backend/tests/Feature/Commands/ReconcileStaleSelectionsCommandTest.php`. Use `RefreshDatabase`.
  - [x] Seed a fixture that mirrors the four real scenarios: mission 8 with 1 accepted + 114 rejected + 4 cancelled, mission 11 with 1 + 27 + 2, mission 14 with 1 + 19 + 0, mission 17 with 1 + 17 + 0. One `MissionPayment` per mission in status `pending` with `fedapay_transaction_id = null`. Test fixture counts can be smaller than prod (e.g., 1 + 3 + 1 per mission) as long as each status bucket — including cancelled — is represented.
  - [x] `test_dry_run_leaves_every_row_unchanged_and_queues_no_notifications`: invoke with `--dry-run`, assert candidatures, payments, missions, `mission_payment_candidatures`, and `notifications` tables are bit-for-bit unchanged.
  - [x] `test_apply_flips_in_scope_candidatures_to_pending_and_preserves_cancelled`: invoke with `--apply`. Assert all `accepted`/`rejected` rows flagged by the audit are now `pending`, every `cancelled` row is untouched, all in-scope `MissionPayment` rows are `failed`, the 4 missions are `published`, and the `MissionPaymentCandidature` entries for those payments are gone.
  - [x] `test_apply_writes_the_three_expected_notification_types_with_exact_french_strings`: assert `notifications.type` bucket counts (`candidature_reset_from_accepted`, `candidature_reset_from_rejected`, `mission_selection_reset_producer`) and spot-check the message strings match AC #6 verbatim (including the accents and guillemets).
  - [x] `test_second_apply_run_is_a_no_op`: invoke `--apply` twice; assert the second run writes nothing to candidatures/payments/missions/notifications.
  - [x] `test_cancelled_rows_are_never_touched_by_apply`: surgical unit-ish test — seed ONLY one mission with a single `cancelled` candidature on a pending payment with `fedapay_transaction_id = null`; invoke `--apply`; assert the `cancelled` row is unchanged. Guards against a future regression widening the status filter.
- [x] Task 5: Write `docs/runbook-fix-20-6-reconcile-stale-selections.md` (AC: #9, #10)
  - [x] Sections: *Preconditions* (snapshot the DB, confirm FIX-20.1 deployment status on prod, run audit queries, record counts), *Dry-run* (command invocation, expected output summary, what to check before proceeding), *Apply* (command invocation, expected log lines, post-run audit returns empty), *Partial-state recovery* (per-payment transactions guarantee each payment is either fully reconciled or fully untouched; if the run aborts mid-batch, simply re-invoke `--apply` — the discovery query is idempotent and naturally skips the already-reconciled payments. The DB snapshot exists as a defence-in-depth backup but is not part of the normal recovery path).
  - [x] Include the two audit SQL queries verbatim from the epic so operators don't need to open the story or the planning doc.
  - [x] Explicit sequencing note: FIX-20.6 before FIX-20.1 deployment on prod. If FIX-20.1 already deployed to prod: re-audit before running.
- [x] Task 6: Non-regression validation (AC: #11)
  - [x] `cd backend && php artisan test --filter=MissionPayment` — still green (no touches to that path).
  - [x] `cd backend && php artisan test --filter=Candidature` — still green.
  - [x] `cd backend && php artisan test --filter=ReconcileStaleSelectionsCommand` — all new tests from Task 4 green.
  - [x] No frontend regression run needed — no frontend changes.

## Dev Notes

### Primary Files (where to look; re-Read before editing)

| File | Action | Notes |
|------|--------|-------|
| `backend/app/Console/Commands/ReconcileStaleSelectionsCommand.php` | CREATE | New file. Borrow the *structural* shape of `BackfillCancelledBookingWalletRefundsCommand` (per-row transaction, structured logs, return codes) but use the stricter `--dry-run` XOR `--apply` contract from Task 2 — no default-write behaviour. |
| `backend/tests/Feature/Commands/ReconcileStaleSelectionsCommandTest.php` | CREATE | Uses `RefreshDatabase`. Fixture seeds the four mission scenarios including `cancelled` rows. |
| `docs/runbook-fix-20-6-reconcile-stale-selections.md` | CREATE | Self-contained operator guide. |
| `backend/app/Services/MissionPaymentService.php` | READ-ONLY | Reference for the `getUserIdForFace` lookup pattern + the three notification shapes. Do **not** touch the service. |
| `backend/app/Enums/MissionPaymentStatus.php` | READ-ONLY | `Failed = 'failed'` enum value already exists; no migration needed. |
| `backend/app/Models/Notification.php` | READ-ONLY | `type` + `data` (JSON) — same plumbing as the existing `mission_*` notifications. |

### Audit queries from the epic (reproduce verbatim in the command and the runbook)

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

-- Requête 2 — candidatures Rejected issues d'un reject en masse sur une mission dont le payment n'a jamais confirmé
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

The command reuses these exact filters — no hardcoded mission/payment IDs, no widened `status IN (...)` that would sweep `cancelled` rows.

### Architecture Patterns to Follow

- **Console command shape**: mirror `BackfillCancelledBookingWalletRefundsCommand` — `$signature` with `--dry-run`, per-row `DB::transaction(function () { ... })`, a typed result map, structured `Log::info`/`Log::warning` writes, `SUCCESS`/`FAILURE` return codes.
- **Dry-run via rollback marker**: the cleanest way to prove zero mutations is to throw a custom `DryRunCompleted` exception at the end of each transaction closure in dry-run mode, catch it in the outer loop, and treat it as success. This guarantees rollback even if a mutation accidentally sneaks in. Alternative: wrap with `DB::beginTransaction()` / `DB::rollBack()` — either is acceptable, pick one and be consistent.
- **Per-payment atomicity**: each payment is processed in its own transaction. A failure on payment #3 leaves payments #1 and #2 applied, which is still consistent under the FIX-20 contract (and a later retry will be a no-op for them). This trades "all-or-nothing" for "make incremental progress even if one payment fails" — the right call for a data-migration command.
- **Notification dispatch pattern**: identical to `MissionPaymentService::notifySafely` (wrapped `Notification::create` + try/catch → log warning on failure, never fatal). Notifications are not part of the per-payment transaction: they are queued *after* commit so a broadcasting hiccup doesn't abort the data fix.
- **No Co-Authored-By in commits** (memory rule `feedback_no_claude_code_branding`).
- **French accents** on every user-facing string (memory rule `feedback_accents_francais`).

### Edge Cases the Command Must Handle

- A `MissionPayment` identified by the audit whose `mission.status` is **not** `pending_payment` (e.g., the producer deleted the mission, or a human operator manually flipped it to `published`). The command must still process the candidatures + the payment, but skip the mission-status reset with an info log: `mission_status_reset_skipped: already_in_expected_state`.
- A `MissionPayment` whose `fedapay_transaction_id` is **not** null (the audit predicate shouldn't match it, but belt-and-braces). The per-payment transaction should re-check `fedapay_transaction_id IS NULL` under `lockForUpdate` and skip with an info log if the value changed since discovery.
- A Face or Producer row whose `User` record was deleted between audit and apply. `Notification::create(...)` with a missing user_id must not fail the transaction — wrap with the same try/catch pattern as `MissionPaymentService::notifySafely` and log a warning.
- The audit returning zero rows (e.g., after FIX-20.1 was deployed and someone else ran this command already). Exit success with a clear "nothing to reconcile" message.
- **Frontend rendering of the three new notification types**: the frontend notification list/popover is not modified by this story. Verify (one quick `mgrep` on the notification renderer) that the renderer's default branch falls back to `data.message` when it encounters an unknown `type` string. If it does (the existing pattern matches `mission_payment_confirmed`, `candidature_rejected`, etc.), the three new types will render with the French message text from AC #6 — no frontend change required. If the renderer instead silently drops unknown types, raise it before merging; either patch the renderer in this story or open a deferred-work ticket and document the gap in the runbook so the operator knows the in-app banner won't show until the frontend ships.

### What NOT to Do

- **Do not** delete the `MissionPayment` rows. The epic is explicit: set status `failed`, keep the row for financial audit traceability.
- **Do not** widen the candidature filter to `status IN ('accepted', 'rejected', 'cancelled')`. `cancelled` is a voluntary Face withdrawal and must never be reverted.
- **Do not** send emails or SMS from the command. The epic explicitly defers hors-app communication (particularly for the mission 8 producer blocked 11 days) to manual, out-of-command follow-up.
- **Do not** backfill `fedapay_transaction_id` from FedaPay API. Any dashboard cross-check is out-of-band and operator-driven — documented in the runbook, not the command.
- **Do not** touch `MissionPaymentService`, `HandleFedapayWebhook`, the Face/Producer controllers, or any frontend file. This story is data-only + a new command + a new runbook.

### Previous Story Intelligence

**FIX-20.1 (done, commit `eef57c5`)** — refactored the service so candidatures never transition during initiation. Already on `dev` but not yet on `main`/prod. **This is the sequencing hinge** for FIX-20.6: the reconcile command must run on prod *before* FIX-20.1 is deployed there, so that the historical orphans are cleaned under the old code (which still understands the inconsistent state well enough to not crash on it) rather than under the new code.

**FIX-20.2 (done, commit `a7f85da`)** — added the `INVARIANT_VIOLATION:` log canary on the Face confirm endpoint. After FIX-20.6 runs successfully in prod and FIX-20.1 ships, this canary should never fire. If it does, it's the FIX-20.6 run that left something behind or the audit scope that missed a row.

**FIX-20.3 (done, commit `ac2719d`)** — removed the legacy manual-accept endpoint. Historical context: the audit confirmed no production candidature was accepted via that endpoint, so FIX-20.6 does not need a separate pass for it.

**FIX-20.4 (done, commit `2d74e49`)** — cleaned up the compensation plumbing + rewrote the runbook. No scope overlap with FIX-20.6, but the existing `docs/runbook-mission-payment-recovery.md` §4.1 is the template shape to follow for the new `docs/runbook-fix-20-6-reconcile-stale-selections.md`.

**FIX-20.5 (done)** — unrelated.

### Deferred Work Awareness

From `_bmad-output/implementation-artifacts/deferred-work.md`:
- `fedapay_transaction_id` int/string casting unverified — pre-existing, does NOT affect this story (we read it via `IS NULL` checks, so casting doesn't bite).

No other open items intersect.

### Prove It Pattern

1. Before touching any code, write the dry-run test in `ReconcileStaleSelectionsCommandTest` and run it — the command file does not yet exist, so the test fails on a "command not defined" error.
2. Create the command with the signature and options, no actual logic → dry-run test still fails because assertions on the output summary fail.
3. Implement the discovery query + per-payment processing → dry-run test passes.
4. Write the apply test → fails (no notifications, no flips, etc. depending on how much of the core logic has landed).
5. Implement notifications + the apply path → apply test passes.
6. Write the cancelled-preservation test → should pass immediately if the filter is correctly narrowed to `status IN ('accepted', 'rejected')`.
7. Write the second-run idempotence test → should pass automatically (the discovery query returns empty on the second run).

### Non-Regression Checklist

After implementation, all of these must pass (run from repo root — frontend intentionally excluded, no frontend changes):
- `cd backend && php artisan test --filter=ReconcileStaleSelectionsCommand`
- `cd backend && php artisan test --filter=MissionPayment`
- `cd backend && php artisan test --filter=Candidature`

### Project Structure Notes

- Backend: Laravel 12, commands in `app/Console/Commands/`, command tests in `tests/Feature/Commands/`, runbooks in `docs/`.
- French accents required on all user-facing strings (memory rule `feedback_accents_francais`).
- DB retroactive migration rule (`feedback_db_retroactive_migration`): this story *is* the retroactive migration for FIX-20.1 — do not skip it even though FIX-20.1 itself landed first.
- No Co-Authored-By Claude in commits.

### References

- [Source: _bmad-output/planning-artifacts/epics-postlaunch-fixes-8.md#FIX-20.6]
- [Source: _bmad-output/implementation-artifacts/fix-20-1-move-accepted-transition-to-payment-webhook.md#Dev Agent Record]
- [Source: _bmad-output/implementation-artifacts/fix-20-4-simplify-obsolete-fix-19-compensation.md#Dev Agent Record]
- [Source: backend/app/Console/Commands/BackfillCancelledBookingWalletRefundsCommand.php — template shape for the new command]
- [Source: backend/app/Enums/MissionPaymentStatus.php — `Failed = 'failed'` enum value]
- [Source: backend/app/Services/MissionPaymentService.php — `getUserIdForFace`, `notifySafely`, `markAsPaid` — read for notification lookup/dispatch patterns]
- [Source: backend/app/Models/Notification.php — `type` + `data` (JSON) plumbing]

## Dev Agent Record

### Agent Model Used

`claude-opus-4-7[1m]` via `/bmad-dev-story` workflow on 2026-04-19.

### Debug Log References

- Pre-flight Task 1 audit was a static check against the epic's documented baseline (4 accepted / 177 rejected / 4 payments {1, 2, 3, 7} / 4 missions {8, 11, 14, 17}). The runbook §2 instructs the operator to re-validate against the live prod snapshot before `--apply`.
- `git log origin/main -- backend/app/Services/MissionPaymentService.php` confirmed the latest commit on `origin/main` for that file is `8d632fa` (FIX-19.4). FIX-20.1 (commit `eef57c5`) is **not** on `main`. The sequencing window is open: this command must run on prod before FIX-20.1 is deployed there, exactly as the story stipulates.
- `mgrep` was unavailable (monthly quota exhausted). Frontend renderer check fell back to `Grep` — `frontend/src/features/notification/components/NotificationsDropdown.vue:188` prints `notification.data.message` unconditionally, so the three new notification types render correctly with no frontend change.

### Completion Notes List

- Implemented `App\Console\Commands\ReconcileStaleSelectionsCommand` with the strict `--dry-run` XOR `--apply` contract from AC #2, naïve `usleep` notification pacing per `--notifications-per-second=N` (default 20), per-payment `DB::transaction` with `lockForUpdate`, and the marker-exception rollback pattern for dry-run.
- Marker exceptions extracted to `App\Support\StaleSelectionReconcile\DryRunCompleted` and `App\Support\StaleSelectionReconcile\SkipPayment` for clean PSR-4 (one class per file).
- Notifications match the AC #6 French strings verbatim — guillemets `« »`, accents corrects (memory rule `feedback_accents_francais`).
- Edge case `mission_status_reset_skipped: already_in_expected_state` is logged when the mission is in any state other than `pending_payment` (e.g. closed by a human between audit and apply). The candidature flips and payment failure still proceed.
- Edge case "fedapay_transaction_id became non-null between discovery and lock" is detected via the post-lock re-check; the payment is skipped via `SkipPayment` (logged at INFO level) and counted under `payments_skipped`.
- Edge case "User row missing for face/producer" is swallowed by `notifySafely`-style try/catch + counted under `notifications_failed` — the data fix is unaffected.
- Test suite runs in 14s (7 tests, 67 assertions). MissionPayment regression: 26/26. Candidature regression: 236/236.
- `vendor/bin/pint` reformatted whitespace in the command file post-write; tests still pass.

### File List

**New files:**
- `backend/app/Console/Commands/ReconcileStaleSelectionsCommand.php`
- `backend/app/Support/StaleSelectionReconcile/DryRunCompleted.php`
- `backend/app/Support/StaleSelectionReconcile/SkipPayment.php`
- `backend/tests/Feature/Commands/ReconcileStaleSelectionsCommandTest.php`
- `docs/runbook-fix-20-6-reconcile-stale-selections.md`

**Modified files:**
- `_bmad-output/implementation-artifacts/sprint-status.yaml` (status transitions + sequencing note)
- `_bmad-output/implementation-artifacts/fix-20-6-reconcile-stale-selections-production-data.md` (this file)

**Verified untouched (per the story's "no-change" expectation):**
- `backend/app/Services/MissionPaymentService.php`
- `backend/app/Enums/MissionPaymentStatus.php`
- `backend/app/Models/Notification.php`
- `backend/app/Jobs/HandleFedapayWebhook.php`
- Every frontend file (no frontend changes)

### Change Log

- 2026-04-19 — Initial implementation: command + marker exceptions + 7-test feature suite + runbook. All non-regression filters green.

### Review Findings

- [x] [Review][Decision → Patch] Rejected-scope query broadened to NOT EXISTS across ALL mpc rows (not just the current payment) — **Resolution:** User chose "harden the query" (option 1). After implementation discovered that `mission_payments.mission_id` is `UNIQUE` at the schema level (`2026_03_20_100001_create_mission_payments_table.php:15`), so a single mission can only ever have one `mission_payments` row. The multi-payment topology the Blind Hunter flagged is therefore physically impossible today, and the original scoped `NOT EXISTS` is logically equivalent to the broadened form. The broadened filter is kept as **defence-in-depth**: if the `UNIQUE` constraint is ever relaxed, the filter stays correct without further changes. [`backend/app/Console/Commands/ReconcileStaleSelectionsCommand.php:136-152, 234-244`]
- [x] [Review][Decision → Patch] Runbook §4 pauses FedaPay webhook delivery before `--apply` — **Resolution:** User chose "operational mitigation" (option 1). New `§4. Pause FedaPay webhook delivery` section inserted before the dry-run step; §8 post-apply verification re-enables the webhook only after §7 audit queries return zero rows. Covers both the "disable endpoint in dashboard" primary path and an alternative "deploy a pre-flight `Failed` short-circuit on `HandleFedapayWebhook`" fallback. All later sections renumbered (§4→§5 dry-run, §5→§6 apply, etc.). [`docs/runbook-fix-20-6-reconcile-stale-selections.md:77-134`]
- [x] [Review][Patch] Seeders: env guard + removed from `DatabaseSeeder` auto-wiring — `FaceSeeder`, `ProducerSeeder`, `MissionSeeder` now bail early via `if (app()->environment('production', 'staging')) return;` and must be invoked explicitly (`php artisan db:seed --class=FaceSeeder`). `DatabaseSeeder::run()` only calls `AdminSeeder::class` automatically. Double safety net. [`backend/database/seeders/{Face,Producer,Mission}Seeder.php`, `DatabaseSeeder.php`]
- [x] [Review][Patch] `renderPaymentSummary` renamed per-payment metric to `notifications_planned=%d` — distinguishes "what this payment would queue" (per-payment line) from "what was actually queued across all payments" (`notifications_queued=…` in the final summary). [`backend/app/Console/Commands/ReconcileStaleSelectionsCommand.php:480-495`]
- [x] [Review][Patch] Dry-run log message reads `payment previewed`, apply reads `payment processed` — grep alerts keyed on `processed` no longer false-positive in dry-run runs. The INFO body also switched `notifications_queued` to `notifications_planned` to match `renderPaymentSummary`. [`backend/app/Console/Commands/ReconcileStaleSelectionsCommand.php:303-319`]
- [x] [Review][Patch] Test coverage additions — three new tests: `test_apply_exits_success_when_no_stale_selections_exist` (empty `--apply` path), `test_apply_skips_mission_status_reset_when_mission_is_already_out_of_pending_payment` (mission-out-of-pending-payment branch), `test_discovery_excludes_payments_with_non_null_fedapay_transaction_id` (belt-and-braces audit filter). The originally-planned "multi-payment on one mission" test was dropped after discovery that `mission_payments.mission_id UNIQUE` makes the fixture impossible. `payments_skipped` counter coverage deferred (testing the race requires concurrency/mocking not justified for this one-shot command). [`backend/tests/Feature/Commands/ReconcileStaleSelectionsCommandTest.php:413-550`]
- [x] [Review][Defer] Unbounded transaction lock hold time + N+1 `User` lookups inside the per-payment transaction [`backend/app/Console/Commands/ReconcileStaleSelectionsCommand.php:292-322, 424-472`] — deferred, academic for the 181-row prod scope; revisit if the command is ever reused for a larger batch.
- [x] [Review][Defer] Pre-existing `mission_titre` (FR) vs frontend `mission_title` (EN) key inconsistency [`backend/app/Console/Commands/ReconcileStaleSelectionsCommand.php:440,454,466` vs `frontend/src/features/notification/components/NotificationsDropdown.vue:170`] — deferred, pre-existing pattern (mirrors `MissionPaymentService`); renderer falls back to `data.message` so the message still shows, only the bold header is missing.
- [x] [Review][Defer] Dry-run snapshot test diffs only `['id','status']` — a stray sibling touch would not be caught [`backend/tests/Feature/Commands/ReconcileStaleSelectionsCommandTest.php:942-946`] — deferred, current implementation uses bulk updates + the marker-exception rollback so the actual risk is nil; strengthen only if dry-run logic grows.
