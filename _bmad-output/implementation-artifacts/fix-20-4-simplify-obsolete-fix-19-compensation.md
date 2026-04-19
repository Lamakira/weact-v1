# Story FIX-20.4: Simplifier la compensation FIX-19 rendue partiellement obsolète

Status: done

## Story

As a **Platform operator reading the mission-payment recovery runbook during an incident**,
I want **the compensation path and its runbook to describe only the mutations that still happen under the FIX-20.1 contract**,
so that **the runbook no longer instructs me to roll back candidature statuses that the service has not touched, the `MissionPaymentService` API surface carries no dead plumbing, and FIX-20 leaves a tidy, minimal compensation story on the way out**.

## Acceptance Criteria

1. **Given** FIX-20.1 has landed (commit `eef57c5`, `prepareSelectionForPayment` no longer mutates `candidature.status`) **When** inspecting `MissionPaymentService::handleInitiationFailure` **Then** no branch attempts to roll back candidature statuses. *(Already true in the current code — this AC is a verification checkpoint.)*
2. **Given** `MissionPaymentService::compensateFailedPreparation` **When** it runs on a failed initiation **Then** it only performs: (a) mission status restore `PendingPayment → Published`, and (b) `MissionPayment` deletion — with `MissionPaymentCandidature` entries deleted via the existing `cascadeOnDelete` foreign key on `mission_payment_candidatures.mission_payment_id`. No candidature status writes, no notification dispatches. *(Already true — verification checkpoint.)*
3. **Given** the return shape of `MissionPaymentService::prepareSelectionForPayment` **When** auditing its consumers **Then** the `selected_candidature_ids` field is removed (dead — no consumer reads it). The method returns a `MissionPayment` directly (Option A — single committed shape; see Dev Notes → Shape decision for the locked-in rationale).
4. **Given** the downstream signatures that accept `$prepared` (`handleInitiationFailure`, `compensateFailedPreparation`) **When** the return-shape change from AC #3 lands **Then** both take `MissionPayment $payment` directly (no array wrapper) and their docblocks are updated in lockstep.
5. **Given** `docs/runbook-mission-payment-recovery.md` **When** reading §2 (phase table) **Then** the `request_checkout` row no longer says "candidatures back to `pending`" — under FIX-20.1 candidatures never leave `pending` during initiation, so the compensation description must be "mission back to `published`, `mission_payments` row deleted" only.
6. **Given** `docs/runbook-mission-payment-recovery.md` **When** reading §3 (Verification procedure) **Then** it reflects the post-FIX-20.1 contract: the candidature-inspection query is annotated as only informative *post-webhook* (under the new contract candidatures stay `pending` during initiation, so that query is uninformative for `phase=request_checkout`/`finalize_local`/`compensate`). The other §3 queries (payment, mission, escrow entries) stay unchanged.
7. **Given** `docs/runbook-mission-payment-recovery.md` **When** reading §4.1 (`phase=compensate` rollback) **Then** the section is rewritten to reflect that the partially-mutated state under the new contract is limited to: `mission.status = pending_payment` plus a `mission_payments` row with no `fedapay_transaction_id`. No "selected candidatures = accepted" bullet, no "some candidatures may have been flipped to rejected" bullet, no SQL block that restores candidature statuses, no rejected-candidature shortlist query. The manual-fix SQL restores the mission and drops the payment (plus optional belt-and-braces explicit `DELETE` on `mission_payment_candidatures` before the payment delete).
8. **Given** `docs/runbook-mission-payment-recovery.md` §4.5 (Webhook never landed but FedaPay says approved) **When** an operator must bypass the self-heal path and fix state by hand **Then** the section describes the **full set of mutations that `markAsPaid` + `applySelectionOutcomesOnPaid` perform**, not just the payment/mission/escrow flip. Specifically:
   - Strongly recommend the self-heal path first (producer retries resume checkout → `MissionPaymentService::initiatePayment` reconciles).
   - If manual is required, the SQL block must also transition **selected candidatures** (those with a row in `mission_payment_candidatures` for this payment) from `pending` → `accepted`, **and** the remaining `pending` candidatures on the same mission — and only those — to `rejected`. Wording must match the code's actual filter at `MissionPaymentService::applySelectionOutcomesOnPaid`: `mission_id = :mission_id AND status = 'pending' AND id NOT IN (SELECT candidature_id FROM mission_payment_candidatures WHERE mission_payment_id = :payment_id)`. Do **not** tell operators to notify "every non-selected Face" — previously-rejected/cancelled Faces must stay untouched.
   - Create a `conversations` row per newly-accepted candidature (`INSERT INTO conversations (candidature_id, created_at, updated_at) SELECT candidature_id, NOW(), NOW() FROM mission_payment_candidatures WHERE mission_payment_id = :payment_id ON DUPLICATE KEY UPDATE candidature_id = candidature_id;` — idempotent via the unique key on `candidature_id`).
   - Manually queue `mission_payment_confirmed` (producer), `mission_participation_confirmation_required` (per selected Face from the entries), and `candidature_rejected` (per newly-rejected Face matching the filter above).
9. **Given** the audit of `dispatchSelectionNotifications` callers **When** searching `backend/` **Then** it is called from exactly one place — `applySelectionOutcomesOnPaid` at webhook-paid time — and no premature dispatch remains in `confirmAndInitiatePayment`. *(Already true — verification checkpoint.)*
10. **Given** the MissionPayment + Candidature backend regression suites **When** run after the code sweep **Then** both pass without any test modifications. If the return-shape change in AC #3 forces a minimal adjustment (signatures only — no fixture or assertion changes), apply it and document in Dev Agent Record.

## Tasks / Subtasks

- [x] Task 1: Verification checkpoint — confirm FIX-20.1 left no stray candidature mutations in the compensation path (AC: #1, #2, #9)
  - [x] `handleInitiationFailure` has no `Candidature::...->update(...)` call.
  - [x] `compensateFailedPreparation` only does mission-status restore + `$payment->delete()`. Cascade FK `mission_payment_candidatures.mission_payment_id → mission_payments.id` has `cascadeOnDelete` in `database/migrations/2026_03_20_100002_create_mission_payment_candidatures_table.php`.
  - [x] `dispatchSelectionNotifications` has exactly one caller — `applySelectionOutcomesOnPaid`.
- [x] Task 2: Verify `selected_candidature_ids` is truly dead (AC: #3, #4)
  - [x] `rg` confirmed only four internal self-references in `MissionPaymentService.php` (docblocks + the assignment + the array key). No external consumer.
- [x] Task 3: Apply the code sweep — Option A (AC: #3, #4)
  - [x] `prepareSelectionForPayment` now returns `MissionPayment` directly. Dropped the `$selectedCandidatureIds` build and the `selected_candidature_ids` key. Docblock updated.
  - [x] `handleInitiationFailure` now takes `MissionPayment $payment` (not `array $prepared`). All `$prepared['payment']->…` reads replaced with `$payment->…`.
  - [x] `compensateFailedPreparation` takes `MissionPayment $preparedPayment` (parameter renamed to avoid shadowing the locked `$payment` inside the transaction closure).
  - [x] `confirmAndInitiatePayment` renames the local from `$prepared` to `$preparedPayment` and threads it through `requestHostedCheckout`, `finalizePreparedPayment`, `handleInitiationFailure`.
- [x] Task 4: `docs/runbook-mission-payment-recovery.md` §2 phase table (AC: #5)
  - [x] `request_checkout` row now says "mission back to `published`, `mission_payments` row deleted (entries cascade via FK). Candidatures stay `pending` throughout — never mutated during initiation under the FIX-20.1 contract."
  - [x] `compensate` row tightened to the narrower frozen shape: orphan payment row + `mission.status = pending_payment`; candidatures are not part of the frozen state.
- [x] Task 5: §3 verification procedure annotation (AC: #6)
  - [x] Added a note on the "Inspect every candidature" query clarifying it's only diagnostic post-webhook and uninformative during `phase=request_checkout`/`finalize_local`/`compensate`.
- [x] Task 6: §4.1 `phase=compensate` rewrite (AC: #7)
  - [x] Narrowed the "Local rows were partially mutated" bullets to mission + payment only.
  - [x] Deleted the "do not bulk-reset every rejected candidature" paragraph and the two candidature-targeted verification queries. Redirected operators to §3.
  - [x] New manual-fix SQL: mission restore + belt-and-braces `DELETE FROM mission_payment_candidatures` + `DELETE FROM mission_payments ... AND fedapay_transaction_id IS NULL`.
- [x] Task 7: §4.5 webhook-never-landed rewrite (AC: #8)
  - [x] Strengthened the "Strongly preferred path — self-heal via resume checkout" recommendation; manual SQL is explicitly last resort.
  - [x] Extended the manual SQL block with the four additional steps `applySelectionOutcomesOnPaid` performs: selected → accepted (guarded on `status = 'pending'`), non-selected pending → rejected (exact service filter), idempotent `Conversation` insert via `ON DUPLICATE KEY UPDATE`.
  - [x] Rewrote the closing paragraph listing all three notifications (`mission_payment_confirmed`, `mission_participation_confirmation_required`, `candidature_rejected`) with explicit mapping from `mission_payment_candidatures` entries to `user_id`, and a critical note that the `candidature_rejected` queue must only cover newly-rejected rows (not previously-rejected).
- [x] Task 8: Non-regression validation (AC: #10)
  - [x] `cd backend && php artisan test --filter=MissionPayment` — 26 passed (19 MissionPaymentInitiationTest + 3 new FIX-20.1 webhook acceptance tests + 4 MissionPaymentStatusTest). No test modifications needed.
  - [x] `cd backend && php artisan test --filter=Candidature` — 235 passed. No coupling detected.
  - [x] Frontend out of scope — skipped per story.

### Review Findings

- [x] [Review][Patch] `request_checkout` still has an undocumented orphan-transaction edge when FedaPay `Transaction::create()` succeeds but `generateToken()` throws, so the runbook overstates "fully compensated" safety and should tell operators to cross-check FedaPay even when `remote_transaction_id` is absent [docs/runbook-mission-payment-recovery.md:40] — **Resolved 2026-04-18:** §2 `request_checkout` row now carries a "⚠️ Remote edge case" warning pointing at `FedapayService::initiatePaymentForMission` (`Transaction::create()` runs before `generateToken()`), and §4.4 adds a four-step FedaPay dashboard check (filter by `custom_metadata.mission_payment_id`, cancel a pending orphan, refund + incident on an approved orphan, proceed on no match) that operators must run *before* telling the producer to retry.
- [x] [Review][Patch] §4.5 manual recovery SQL inserts into `conversations.uuid`, but the `conversations` table only has `id`, `candidature_id`, and timestamps, so the documented manual fix will fail as written [docs/runbook-mission-payment-recovery.md:209] — **Resolved 2026-04-19:** the insert now targets `(candidature_id, created_at, updated_at)` only, matching the real `conversations` schema.
- [x] [Review][Patch] §4.5 says the SQL block is replay-safe, but that guarantee stops at the database mutations; the follow-up notification queueing has no dedupe guard, so the runbook should explicitly warn operators not to blindly requeue notifications on a rerun without checking what was already sent [docs/runbook-mission-payment-recovery.md:218] — **Resolved 2026-04-19:** §4.5 now states explicitly that notification replay is not idempotent and instructs operators to inspect what was already sent before queueing anything on a rerun.
- [x] [Review][Patch] §4.5 assumes every selected Face and the producer resolve to a `users` row for notification queueing, but the service intentionally skips missing recipients; the runbook should tell operators to verify recipient resolution and fall back to out-of-band contact when no matching user exists [docs/runbook-mission-payment-recovery.md:220] — **Resolved 2026-04-19:** §4.5 now tells operators to verify recipient resolution first, avoid hand-creating notifications for missing users, log the integrity gap, and use out-of-band contact when needed.

## Dev Notes

### Primary Files (treat line numbers as orientation only — re-Read before editing)

| File | Action | Where to look |
|------|--------|---------------|
| `backend/app/Services/MissionPaymentService.php` | MODIFY — strip dead plumbing | `confirmAndInitiatePayment` call site, `prepareSelectionForPayment` return, `handleInitiationFailure` signature, `compensateFailedPreparation` signature |
| `docs/runbook-mission-payment-recovery.md` | MODIFY — §2 phase table (`request_checkout` + `compensate` rows), §3 annotation on the candidature-inspection query, §4.1 rewrite, §4.5 rewrite + expanded SQL + notification list |
| `backend/tests/Feature/Mission/MissionPaymentInitiationTest.php` | READ-ONLY — no changes expected. Only touch if a signature change breaks a call site (should not, per Shape decision above) |
| `backend/database/migrations/2026_03_20_100002_create_mission_payment_candidatures_table.php` | READ-ONLY — confirm the `cascadeOnDelete` on `mission_payment_id` for the §4.1 wording |

### Architecture Patterns to Follow

- **Cascade delete pattern**: the `mission_payment_candidatures.mission_payment_id` FK has `cascadeOnDelete`, so `$payment->delete()` is sufficient to clear the entries. The runbook can still suggest an explicit `DELETE` for operators as a belt-and-braces habit, but the service itself does not need it.
- **Signature minimalism**: CLAUDE.md — "Don't add features, refactor, or introduce abstractions beyond what the task requires." The locked shape is `MissionPayment` directly (see Dev Notes → Shape decision).
- **No new tests required**: FIX-20.1 already rewrote `MissionPaymentInitiationTest` assertions for the new contract. This story is a pure cleanup pass; no Prove It pattern unless the return-shape change breaks a test (in which case the test modification *is* the Prove It output).
- **Runbook voice**: terse, operator-facing, no narrative. Keep the Markdown table shape in §2 and the `BEGIN; ... COMMIT;` SQL blocks in §4.1/4.5.

### Previous Story Intelligence

**FIX-20.1 (done, commit `eef57c5`)** — already did the bulk of the work this story was originally scoped for:
- Removed candidature rollback from `compensateFailedPreparation`.
- Removed inline `dispatchSelectionNotifications` from `confirmAndInitiatePayment`.
- Updated `MissionPaymentInitiationTest` for the Pending-until-paid contract.
- Left two small loose ends that FIX-20.4 now closes: (a) `selected_candidature_ids` is still in the return shape and built as `$selectedCandidatureIds` but never read; (b) the runbook still describes the pre-FIX-20.1 compensation shape and rollback SQL.

**FIX-20.2 (done, commit `a7f85da`)** — added the `INVARIANT_VIOLATION:` log canary to `Face/CandidatureController::confirm`. Unrelated to the compensation path, but worth mentioning because it's the other half of the defence-in-depth pattern FIX-20 leaves behind: one canary at confirm-time, one minimal compensation at initiation-failure-time.

**FIX-20.3 (done, commit `ac2719d`)** — removed the legacy manual accept endpoint, which was the only pre-FedaPay path that could have ever produced an `Accepted-without-Paid` row. Combined with FIX-20.1, there is now no live code path that can produce the state §4.1 of the runbook historically described.

**FIX-20.5 (done, commit `9bca244`)** — unrelated to this story.

**FIX-20.6 (backlog)** — will clean up historical stale rows (181 candidatures). Not a prerequisite for this cleanup, but note that FIX-20.4 should land **before** FIX-20.6 runs in production if possible, so the runbook operators read during/after the migration already matches the new contract.

### Deferred Work Awareness

From `_bmad-output/implementation-artifacts/deferred-work.md`:
- `$payment` may be read without definition when `handleInitiationFailure()` returns instead of throwing — pre-existing (FIX-19.1 scope), do NOT fix here.
- `fedapay_transaction_id` int/string casting unverified — pre-existing, do NOT fix here.

Neither intersects the code sweep in Task 3.

### What NOT to Touch

- **`applySelectionOutcomesOnPaid`** — owned by FIX-20.1, already stable. Do not revisit the Conversation/notification logic.
- **`markAsPaid`** — its self-healing branch + webhook idempotence guard are load-bearing. Do not refactor.
- **`Face/CandidatureController::confirm`** — FIX-20.2 added the log canary. Do not touch.
- **`HandleFedapayWebhook`** — unchanged since FIX-20.1, no new scope.
- **Migration rétroactive** — FIX-20.6 owns the 181-row data reconciliation. Do not attempt a backfill here.

### Shape decision — locked to Option A

`prepareSelectionForPayment` returns a `MissionPayment` directly. Rationale:

- The array wrapper currently carries exactly one key, and consumer audit confirmed no other caller reads the now-dead `selected_candidature_ids`. Keeping the wrapper would preserve an abstraction with no remaining purpose (CLAUDE.md: "Don't design for hypothetical future requirements").
- The supposed "docblock-only" alternative is misleading — even with the wrapper kept, the code still needs to stop building `$selectedCandidatureIds` and stop setting the dead key, so the concrete diff is not smaller.
- Existing partial mocks in `MissionPaymentInitiationTest` stub only `requestHostedCheckout`, `finalizePreparedPayment`, and `compensateFailedPreparation`. None of those stubs reach into the internal `$prepared` array shape; they take `MissionPayment` arguments already. The signature change should not force any test adjustments.

Option A is locked — do not re-evaluate during implementation.

### Runbook rewrite — recap of what changes

**§2 phase table, `request_checkout` row:** the "candidatures back to `pending`" clause is wrong under FIX-20.1. New description: "Fully compensated — mission back to `published`, `mission_payments` row deleted (entries cascade via FK)."

**§2 phase table, `compensate` row:** narrow the frozen-state description to the post-FIX-20.1 shape — mission stuck in `pending_payment` + orphan `mission_payments` row (no `fedapay_transaction_id`). No candidature rows involved.

**§3 verification procedure:** keep every query as-is, but annotate the "every candidature touched by this mission" query as only informative *post-webhook*. During `phase=request_checkout`/`finalize_local`/`compensate` it just returns the pending set and is not diagnostic.

**§4.1 `phase=compensate` rollback:** narrow the "Local rows were partially mutated" bullets to mission + payment only. Delete the "do not bulk-reset every rejected candidature" paragraph and the two candidature-targeted SQL queries. Collapse the manual-fix SQL to: restore mission + (optional) explicit `DELETE` on entries + delete payment.

**§4.5 webhook-never-landed manual recovery:** the hard part. The current section only rewrites mission/payment/escrow state and then tells operators to queue two notifications. Under FIX-20.1, `markAsPaid` also calls `applySelectionOutcomesOnPaid`, which:

1. Transitions selected candidatures (those with an entry in `mission_payment_candidatures`) from `pending` → `accepted`.
2. Transitions other still-`pending` candidatures on the same mission to `rejected` — matching the exact filter at `MissionPaymentService::applySelectionOutcomesOnPaid`: `mission_id = :mission_id AND status = 'pending' AND id NOT IN (SELECT candidature_id FROM mission_payment_candidatures WHERE mission_payment_id = :payment_id)`. Previously-rejected/cancelled rows must be left untouched.
3. Creates a `conversations` row per newly-accepted candidature (`Conversation::firstOrCreate(['candidature_id' => ...])`, unique FK, idempotent).
4. Dispatches `mission_payment_confirmed` (producer), `mission_participation_confirmation_required` (per selected Face), and `candidature_rejected` (per newly-rejected Face — same filter as step 2).

A manual SQL fix that skips the service must reproduce all four steps, or it leaves the mission stuck in a half-healed state (paid but Faces still `pending`, no `Conversation` rows, no notifications). The rewrite recommends the self-heal path first and only falls back to manual SQL for the full four-step sweep.

### Prove It Pattern

This story is a cleanup + documentation pass, not a bug fix. Prove It guidance reduces to:

1. Record the baseline test count for `cd backend && php artisan test --filter=MissionPayment` *before* any code change.
2. After the code sweep in Task 3, the same filter must still be the same count, still green. No assertion changes expected under Option A — the partial mocks in the suite stub protected methods on concrete `MissionPayment` arguments and do not depend on the internal `$prepared` shape.
3. Runbook changes are content-only. Verify them by re-reading each touched section against the current `MissionPaymentService` behaviour.

### Non-Regression Checklist

After implementation, both of these must pass (run from repo root — frontend is out of scope for this story and intentionally not in the checklist):
- `cd backend && php artisan test --filter=MissionPayment`
- `cd backend && php artisan test --filter=Candidature`

### Project Structure Notes

- Backend: Laravel 12 in `backend/`. Services in `app/Services/`, runbooks in `docs/`.
- French accents required on user-facing strings (memory rule `feedback_accents_francais`). The runbook is operator-facing in English — existing convention, keep English.
- No comments unless explaining "why" (CLAUDE.md). The removal of dead plumbing should not introduce new comments.
- No Co-Authored-By Claude in commits (project memory rule).

### References

- [Source: _bmad-output/planning-artifacts/epics-postlaunch-fixes-8.md#FIX-20.4]
- [Source: _bmad-output/implementation-artifacts/fix-20-1-move-accepted-transition-to-payment-webhook.md#Dev Agent Record]
- [Source: _bmad-output/implementation-artifacts/fix-20-2-simplify-face-confirm-endpoint.md#Dev Agent Record]
- [Source: _bmad-output/implementation-artifacts/fix-20-3-remove-legacy-manual-accept-endpoint.md]
- [Source: _bmad-output/implementation-artifacts/deferred-work.md]
- [Source: backend/app/Services/MissionPaymentService.php — `confirmAndInitiatePayment`, `prepareSelectionForPayment`, `handleInitiationFailure`, `compensateFailedPreparation`, `applySelectionOutcomesOnPaid`]
- [Source: backend/database/migrations/2026_03_20_100002_create_mission_payment_candidatures_table.php — `cascadeOnDelete` on `mission_payment_id`]
- [Source: docs/runbook-mission-payment-recovery.md — §2 phase table, §3 verification procedure, §4.1, §4.5]

## Dev Agent Record

### Agent Model Used

claude-opus-4-7 (1M context)

### Debug Log References

- Baseline `cd backend && php artisan test --filter=MissionPayment` = 26 passed, 232 assertions before any change. Same 26 passed, 232 assertions after the code sweep — zero test modifications needed.
- Baseline `--filter=Candidature` = 235 passed, 790 assertions. Same after the change — no coupling detected.
- Partial mocks in `MissionPaymentInitiationTest` (`test_finalization_failure_after_checkout_...`, `test_compensation_failure_after_finalize_...`) stub `requestHostedCheckout` / `finalizePreparedPayment` / `compensateFailedPreparation` — none depend on the internal `$prepared` shape, so the Option A signature change did not force any fixture or assertion adjustments.

### Completion Notes List

- **Option A locked in and applied.** `prepareSelectionForPayment` now returns `MissionPayment`; `handleInitiationFailure` and `compensateFailedPreparation` take `MissionPayment`; local variable in `confirmAndInitiatePayment` renamed to `$preparedPayment` for clarity. The parameter name inside `compensateFailedPreparation` had to be `$preparedPayment` (rather than `$payment`) to avoid shadowing the locked row inside the DB transaction closure.
- **Verification checkpoint (Task 1) passed.** No candidature mutations in the compensation path; `dispatchSelectionNotifications` has exactly one caller (`applySelectionOutcomesOnPaid`).
- **Runbook §4.5 was the heart of this story's value-add.** The prior section only covered the payment/entry/mission flip and told operators to queue two notifications. Under FIX-20.1, `markAsPaid` → `applySelectionOutcomesOnPaid` also transitions candidatures, provisions conversations, and fires a third notification type. The rewrite reproduces every step in SQL (with the exact service filter for the rejection step) and lists all three notifications with explicit user-id mapping. Self-heal via resume checkout is now explicitly the preferred path; manual SQL is framed as last resort only.
- **Zero test changes.** The Option A signature cleanup was transparent to the test suite, confirming the shape decision was correct.

### File List

**Modified:**
- `backend/app/Services/MissionPaymentService.php`
- `docs/runbook-mission-payment-recovery.md`

**Verified no change needed:**
- `backend/tests/Feature/Mission/MissionPaymentInitiationTest.php`
- `backend/app/Jobs/HandleFedapayWebhook.php`
- `backend/app/Enums/CandidatureStatus.php`
- `backend/database/migrations/2026_03_20_100002_create_mission_payment_candidatures_table.php`
- Frontend (out of scope)

### Change Log

| Date | Summary |
|------|---------|
| 2026-04-18 | FIX-20.4 cleanup pass: removed the dead `selected_candidature_ids` plumbing from `MissionPaymentService::prepareSelectionForPayment` and simplified downstream signatures (`handleInitiationFailure`, `compensateFailedPreparation`) to take `MissionPayment` directly. Rewrote `docs/runbook-mission-payment-recovery.md` §2/§3/§4.1/§4.5 to match the FIX-20.1 contract: §4.5 now documents every mutation `applySelectionOutcomesOnPaid` performs, including candidature transitions, `Conversation` provisioning, and the full three-type notification list. 26 MissionPayment + 235 Candidature tests pass unchanged. |
| 2026-04-18 | Code-review follow-up: §2 `request_checkout` row now carries a ⚠️ remote-orphan warning and §4.4 adds a four-step FedaPay dashboard check covering the edge where `FedapayService::initiatePaymentForMission` throws between `Transaction::create()` and `generateToken()`. |
