# Story FIX-20.1: Déplacer la transition `Accepted` et le rejet en masse vers le webhook `Paid`

Status: ready-for-dev

## Story

As a **Face (talent)**,
I want **to be notified that my candidature is accepted only after the Producer's payment is confirmed**,
so that **when I see "Acceptée" I know for certain I can confirm my participation, with no ambiguous window where the status says accepted but I cannot act on it**.

## Acceptance Criteria

1. **Given** a Producer confirms a selection and initiates payment **When** `prepareSelectionForPayment` runs **Then** all candidature statuses remain `pending` — none transition to `accepted` or `rejected`.
2. **Given** a Producer confirms a selection and initiates payment **When** `prepareSelectionForPayment` runs **Then** no `candidature_accepted`, `candidature_rejected`, or `mission_participation_confirmation_required` notifications are dispatched. All notifications fire exclusively at Paid time (via `markAsPaid` + `applySelectionOutcomesOnPaid`).
3. **Given** FedaPay webhook fires with `transaction.approved` for a mission payment **When** `markAsPaid` processes it **Then** each selected candidature (from `MissionPaymentCandidature` entries) transitions to `accepted`, all other `pending` candidatures on the same mission transition to `rejected`.
4. **Given** FedaPay webhook fires with `transaction.approved` **When** `applySelectionOutcomesOnPaid` runs **Then** a `Conversation` record is created via `Conversation::firstOrCreate(['candidature_id' => $candidature->id])` for each newly-accepted candidature.
5. **Given** FedaPay webhook fires with `transaction.approved` **When** `applySelectionOutcomesOnPaid` runs **Then** `candidature_rejected` notifications are dispatched for each rejected Face. No separate `candidature_accepted` notification is needed because `markAsPaid` already dispatches `mission_participation_confirmation_required` to each selected Face (L586-597) — these two notifications would be redundant duplicates under the new contract.
6. **Given** the same FedaPay webhook fires twice for the same transaction **When** `markAsPaid` / `applySelectionOutcomesOnPaid` runs **Then** candidature statuses are not re-mutated and no duplicate notifications are dispatched (idempotence).
7. **Given** `prepareSelectionForPayment` succeeds but FedaPay checkout is never completed **When** inspecting candidature statuses **Then** all remain `pending` — no stale `accepted` or `rejected` states.
8. **Given** the existing `MissionPaymentInitiationTest` tests **When** running after the refactor **Then** all pass with updated assertions (status checks happen post-webhook, not post-prepare).
9. **Given** the FIX-19 compensation flow (`handleInitiationFailure`) **When** FedaPay initiation fails **Then** no candidature rollback is attempted (since no candidatures were mutated) — only `MissionPayment` + entries are cleaned up. *(Full cleanup of dead compensation code deferred to FIX-20.4.)*

## Tasks / Subtasks

- [ ] Task 1: Write Prove It failing tests (AC: #1, #2)
  - [ ] Test that after `prepareSelectionForPayment`, all selected candidatures remain `Pending` (currently fails: they're `Accepted`).
  - [ ] Test that after `prepareSelectionForPayment`, no `candidature_accepted` notification exists (currently fails: notifications are dispatched in `dispatchSelectionNotifications`).
- [ ] Task 2: Remove candidature mutations from `prepareSelectionForPayment` (AC: #1, #2, #7)
  - [ ] Remove `$candidature->update(['status' => CandidatureStatus::Accepted])` at `MissionPaymentService.php:165`.
  - [ ] Remove the rejected candidatures mass-update block at `MissionPaymentService.php:180-201`.
  - [ ] Remove `candidature_accepted` and `candidature_rejected` notification entries from the `$notifications` array built in `prepareSelectionForPayment` (they will move to `applySelectionOutcomesOnPaid`).
  - [ ] Keep: `MissionPayment` creation (L142-153), `MissionPaymentCandidature` entry creation (L157-163), `requestHostedCheckout`, `finalizePreparedPayment`.
- [ ] Task 3: Create `applySelectionOutcomesOnPaid` method (AC: #3, #4, #5, #6)
  - [ ] New method `MissionPaymentService::applySelectionOutcomesOnPaid(MissionPayment $payment)`.
  - [ ] In a single DB transaction:
    - Retrieve selected candidature IDs from `$payment->entries->pluck('candidature_id')`.
    - Transition selected candidatures `Pending → Accepted` (guard: skip if already `Accepted` for idempotence).
    - Transition remaining `Pending` candidatures on the same `mission_id` to `Rejected`.
    - Create `Conversation::firstOrCreate(['candidature_id' => $candidature->id])` for each newly-accepted candidature.
    - Build and dispatch `candidature_rejected` notifications for each rejected Face (type `candidature_rejected`, message "Votre candidature n'a pas été retenue.", same payload shape as current `prepareSelectionForPayment:191-200`).
    - **Do NOT dispatch a separate `candidature_accepted` notification.** `markAsPaid` already sends `mission_participation_confirmation_required` to each selected Face (L586-597, message "Vous avez été sélectionné(e)... Confirmez votre participation."). Under the new contract both fire at Paid time, so `candidature_accepted` would be a redundant duplicate. The existing `mission_participation_confirmation_required` is more actionable and already covers the acceptance signal.
  - [ ] Idempotence: if candidatures are already `Accepted`, skip mutations and notifications.
- [ ] Task 4: Wire `applySelectionOutcomesOnPaid` into `markAsPaid` (AC: #3, #6)
  - [ ] In `MissionPaymentService::markAsPaid` (L534-604), after the payment transitions to `Paid` (L545), call `$this->applySelectionOutcomesOnPaid($payment)`.
  - [ ] Verify that `markAsPaid` already has an early return when payment is already `Paid` (L540) — this provides idempotence for the entire chain.
- [ ] Task 5: Update `handleInitiationFailure` / `compensateFailedPreparation` (AC: #9)
  - [ ] In `compensateFailedPreparation` (L413-444): remove the candidature rollback lines (L431-434 for `Accepted → Pending`, L436-440 for `Rejected → Pending`) since these mutations no longer happen in `prepareSelectionForPayment`.
  - [ ] Keep: `MissionPayment`/`MissionPaymentCandidature` deletion, mission status restore (`PendingPayment → Published`), logging.
  - [ ] *(Note: full cleanup of now-dead compensation code is FIX-20.4's scope — this task only removes the lines that would throw errors or produce incorrect behavior.)*
- [ ] Task 6: Refactor `MissionPaymentInitiationTest` helper + update ALL dependent tests (AC: #8)
  - [ ] **Refactor `createPendingMissionPayment` helper (L876-911):** this helper simulates the old post-prepare state by forcing candidatures to `Accepted` (L904) and `Rejected` (L907). Under the new contract, candidatures must remain `Pending` after prepare. Remove the `$candidature->update(['status' => CandidatureStatus::Accepted])` (L904) and `$this->rejectedCandidate->update(['status' => CandidatureStatus::Rejected])` (L907) lines. Keep: `MissionPayment` creation, `MissionPaymentCandidature` entries, `mission→PendingPayment` (L908).
  - [ ] **9 tests call this helper** — ALL need review after the helper refactor (not just status assertions, but also notification counts and types):
    - L146: `test_retry_without_reselecting_faces_reuses_existing_checkout_url`
    - L177: (verify test name at this line)
    - L290: (verify test name)
    - L314: `test_retry_after_terminal_remote_failure_reuses_same_payment_record_with_new_checkout`
    - L356: (verify test name — likely webhook/self-healing test)
    - L412: `test_webhook_and_producer_retry_race_do_not_double_credit`
    - L459: (verify test name)
    - L744: `test_transient_resume_failure_keeps_payment_row_resumable_on_the_next_attempt`
    - L806: webhook decline test
  - [ ] **6 tests assert `CandidatureStatus::Accepted`** after `prepareSelectionForPayment` — change to assert `Pending`:
    - L119-121, L169-171, L349-351, L587-589, L720-722, L784-786
  - [ ] **Notification count assertions must be reviewed.** Current self-healing tests assert `assertDatabaseCount('notifications', 3)` (L435) and specific types `mission_payment_confirmed` + `mission_participation_confirmation_required` (L394-407). Under the new contract, `applySelectionOutcomesOnPaid` will also dispatch `candidature_rejected` notifications for non-selected Faces. Total notification count will change from 3 to 3 + N_rejected. Update all `assertDatabaseCount('notifications', ...)` assertions accordingly.
  - [ ] **`candidature_accepted` notification assertions**: any test that checks for `candidature_accepted` dispatched during prep must be updated — these notifications are no longer dispatched (replaced by the existing `mission_participation_confirmation_required` in `markAsPaid`).
- [ ] Task 7: Write webhook acceptance tests (AC: #3, #4, #5, #6)
  - [ ] New test: simulate full flow (prep → webhook `approved`) → assert selected candidatures are `Accepted`, others are `Rejected`, `Conversation` records exist, notifications dispatched.
  - [ ] New test: simulate webhook replay → assert no duplicate mutations or notifications.
  - [ ] New test: prep without webhook (abandoned checkout) → assert all candidatures remain `Pending`, no conversations created.
- [ ] Task 8: Non-regression validation (AC: #8, #9)
  - [ ] `php artisan test --filter=MissionPayment` — all pass.
  - [ ] `php artisan test --filter=Candidature` — all pass.
  - [ ] `php artisan test --filter=ConditionalChatUnlock` — all pass (the `acceptCandidatureDirectly()` helper in this test already follows the new pattern: sets status + creates Conversation).
  - [ ] `php artisan test --filter=FaceNotification` — all pass.
  - [ ] Frontend: `npx vitest run src/features/candidature` — all pass (frontend doesn't change in this story).
  - [ ] TypeScript check: `npm run type-check` — clean.

## Dev Notes

### Primary Files (verify line numbers via Read before editing — they may shift after FIX-20.3/20.5 cherry-picks)

| File | Action | Key Lines |
|------|--------|-----------|
| `backend/app/Services/MissionPaymentService.php` | MODIFY — core refactor | L92-213 (`prepareSelectionForPayment`), L534-604 (`markAsPaid`), L413-444 (`compensateFailedPreparation`), L327-395 (`handleInitiationFailure`) |
| `backend/app/Jobs/HandleFedapayWebhook.php` | READ-ONLY audit — webhook calls `markAsPaid` at L80, no change needed if wiring goes through `markAsPaid` | L76-80 |
| `backend/tests/Feature/Mission/MissionPaymentInitiationTest.php` | MODIFY — 6 assertion updates + 3 new tests | 17 existing tests |
| `backend/app/Enums/CandidatureStatus.php` | READ-ONLY audit | `allowsChatAccess()` returns true for Accepted/Confirmed/InProgress/Completed |
| `backend/app/Models/Conversation.php` | READ for import | `Conversation::firstOrCreate(['candidature_id' => ...])` |

### Architecture Patterns to Follow

- **Transaction wrapping**: `applySelectionOutcomesOnPaid` must run inside `DB::transaction()` — same pattern as `prepareSelectionForPayment`.
- **Notification dispatch**: Use the same `$notifications[]` array + `$this->dispatchSelectionNotifications()` pattern already in `MissionPaymentService`.
- **Idempotence pattern**: `markAsPaid` already returns early at L540 for `Paid` payments. Lean on this for webhook replay safety. Additionally, in `applySelectionOutcomesOnPaid`, guard candidature mutations with `->where('status', CandidatureStatus::Pending->value)` so replays don't re-mutate `Accepted` candidatures.
- **Lock pattern**: `markAsPaid` uses `lockForUpdate()` at its own transaction boundary. `applySelectionOutcomesOnPaid` should run INSIDE that same transaction or immediately after, not in a separate deferred job.

### Previous Story Intelligence

**FIX-20.3** (done): removed the legacy `accept()` endpoint. Key finding: this endpoint was the **only** code that called `Conversation::firstOrCreate` in production. The `acceptCandidatureDirectly()` test helper introduced in `ConditionalChatUnlockTest` (L77-83) mirrors the expected post-FIX-20.1 behavior: it promotes status + creates a Conversation in one step. Use this as a reference pattern.

**FIX-20.5** (done): added `resolveConfirmErrorMessage` in `useConfirmCandidature.ts`. Frontend now surfaces 422 error messages from the backend — when the Face clicks "Confirmer" on a non-confirmable candidature, she sees the backend message. This means FIX-20.1 doesn't need to touch the frontend.

**FIX-19.1** (done): introduced `compensateFailedPreparation` which rolls back `Accepted → Pending` and `Rejected → Pending` on FedaPay initiation failure. After FIX-20.1, these rollback lines become dead code (no mutations to undo). Remove them in Task 5, but leave the MissionPayment cleanup logic intact (that's still live). Full dead-code removal is FIX-20.4's scope.

**FIX-19.2** (done): introduced resume checkout logic (`resolveResumablePayment`, `resumeCheckout`). These methods are unaffected by FIX-20.1 — they don't touch candidature status.

### Deferred Work Awareness

From `deferred-work.md`:
- `$payment` may be read without definition when `handleInitiationFailure()` returns instead of throwing (FIX-19.1 scope) — do NOT fix in this story, but be aware of it when modifying `handleInitiationFailure`.
- `fedapay_transaction_id` int/string casting is unverified — pre-existing, do not fix here.

### Conversation Creation — Critical Requirement

**This is the single most important non-obvious AC in this story.** FIX-20.3 revealed that no production code creates `Conversation` records for candidatures accepted via the paid flow. After FIX-20.1:

```php
// Inside applySelectionOutcomesOnPaid, for each selected candidature:
Conversation::firstOrCreate(['candidature_id' => $candidature->id]);
```

Without this, the chat feature between Face and Producer remains silently broken — no `Conversation` row means no messages can be sent (the MessageController takes a Conversation as route-model-binding).

### What NOT to Touch

- **Frontend**: no changes needed. The frontend already displays the correct candidature status from the API. Faces will simply see `pending` until payment is confirmed, then `accepted`. The confirmation button only renders on `accepted`.
- **`CandidatureStatus` enum**: no new values, no changes to `allowsChatAccess()` or `allowsRatings()`.
- **FIX-20.6 migration data**: the data reconciliation runs BEFORE FIX-20.1 lands. By the time this story ships, the 4 stale payments are already cleaned up.
- **`dispatchSelectionNotifications()` helper method**: keep the method itself (it's reusable), just stop calling it from `prepareSelectionForPayment` and start calling it from `applySelectionOutcomesOnPaid` instead.
- **Runbook** (`docs/runbook-mission-payment-recovery.md`): update deferred to FIX-20.4.

### Prove It Pattern

The CLAUDE.md project convention requires a failing test before any fix:

1. **Write first**: a test that calls `prepareSelectionForPayment` and asserts all candidatures remain `Pending` + no `candidature_accepted` notification dispatched.
2. **Run**: the test MUST FAIL on current code (candidatures transition to `Accepted` at L165).
3. **Apply the refactor** (Tasks 2–5).
4. **Run again**: the test MUST PASS.
5. **Add remaining tests** (webhook acceptance, idempotence, abandoned checkout).

### Non-Regression Checklist

After implementation, ALL of these must pass:
- `php artisan test --filter=MissionPayment`
- `php artisan test --filter=Candidature`
- `php artisan test --filter=ConditionalChatUnlock`
- `php artisan test --filter=FaceNotification`
- `npx vitest run src/features/candidature` (frontend unchanged but verify)
- Note: webhook coverage lives inside `MissionPaymentInitiationTest`, not a dedicated `HandleFedapayWebhook` test class. Running `--filter=MissionPayment` covers it.
- `npm run type-check`

### Project Structure Notes

- Backend follows Laravel 12 conventions: services in `app/Services/`, jobs in `app/Jobs/`, models in `app/Models/`, enums in `app/Enums/`.
- Tests in `tests/Feature/` mirror the service/domain structure.
- No comments unless explaining "why" (CLAUDE.md convention).
- French accents required on all user-facing strings (memory rule).
- No Co-Authored-By Claude in commits (project memory rule).

### References

- [Source: _bmad-output/planning-artifacts/epics-postlaunch-fixes-8.md#FIX-20.1]
- [Source: _bmad-output/implementation-artifacts/fix-20-3-remove-legacy-manual-accept-endpoint.md#Conversation creation gap]
- [Source: _bmad-output/implementation-artifacts/deferred-work.md#Deferred from: code review of fix-20-3]
- [Source: backend/app/Services/MissionPaymentService.php — L92-213, L327-444, L534-604]
- [Source: backend/app/Jobs/HandleFedapayWebhook.php — L76-80]
- [Source: backend/tests/Feature/Mission/MissionPaymentInitiationTest.php — 17 tests]
- [Source: backend/tests/Feature/Messaging/ConditionalChatUnlockTest.php — acceptCandidatureDirectly() L77-83]

## Dev Agent Record

### Agent Model Used

_(to be filled during implementation)_

### Debug Log References

_(to be filled during implementation)_

### Completion Notes List

_(to be filled during implementation)_

### File List

_(to be filled during implementation — expected files below)_

**Expected modifications:**
- `backend/app/Services/MissionPaymentService.php`
- `backend/tests/Feature/Mission/MissionPaymentInitiationTest.php`

**Expected no-change (verify only):**
- `backend/app/Jobs/HandleFedapayWebhook.php`
- `backend/app/Enums/CandidatureStatus.php`
- Frontend files (no changes expected)
