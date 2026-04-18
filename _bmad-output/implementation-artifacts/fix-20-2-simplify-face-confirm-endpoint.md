# Story FIX-20.2: `Accepted` intrinsèquement confirmable — simplifier `Face/CandidatureController::confirm`

Status: review

## Story

As a **Face (talent)**,
I want **the confirm-participation endpoint to trust a single source of truth (`candidature.status === Accepted`)**,
so that **the code path stays in sync with the FIX-20.1 contract, redundant checks are removed or downgraded to defensive guards, and the 422 `PAYMENT_NOT_CONFIRMED` error stops being a user-visible failure mode — the Face simply never reaches this state under the new contract**.

## Acceptance Criteria

1. **Given** FIX-20.1 has landed (candidatures only reach `Accepted` after `MissionPayment.status === Paid`) **When** `Face/CandidatureController::confirm` runs with a candidature whose status is `Accepted` **Then** the controller does NOT call the 422 `PAYMENT_NOT_CONFIRMED` branch under any normal flow — either the check is removed entirely or kept as a defensive guard that logs an invariant violation and never fires in practice.
2. **Given** the decision is *defensive conservation* (recommended in the epic) **When** a hypothetical out-of-contract row is detected (candidature `Accepted` with `MissionPayment.status !== Paid`) **Then** the controller emits `Log::warning('INVARIANT_VIOLATION: candidature accepted without paid payment', [candidature_id, mission_id, payment_status])` and still returns the 422 `PAYMENT_NOT_CONFIRMED` response. The warning payload must include enough context (candidature_id, mission_id, payment_id if present, payment_status) to pinpoint the broken row.
3. **Given** the existing `NOT_IN_FINAL_SELECTION` check (controller L194-201) **When** the confirm endpoint runs **Then** this branch is preserved unchanged — it protects a different invariant (`MissionPaymentCandidature` entry missing for the candidature), orthogonal to FIX-20.1.
4. **Given** the existing `INVALID_STATUS` check (controller L172-179) **When** a non-`Accepted` candidature is targeted **Then** this branch is preserved unchanged — it already covers the first-line status gate.
5. **Given** `FaceConfirmCandidatureTest::test_cannot_confirm_candidature_when_payment_is_not_confirmed` **When** run after the refactor **Then** it still passes: under defensive conservation the controller still returns 422 `PAYMENT_NOT_CONFIRMED` for the synthetic out-of-contract row, AND asserts that the invariant-violation warning was logged. Under pure removal the test must be deleted or repurposed (see Task 3 decision gate).
6. **Given** the existing happy-path and authorization tests in `FaceConfirmCandidatureTest` **When** run after the refactor **Then** all pass without modification (`test_face_can_confirm_accepted_candidature_after_payment_confirmation`, `test_cannot_confirm_candidature_not_in_final_selection`, `test_cannot_confirm_pending_candidature`, `test_cannot_confirm_already_confirmed_candidature`, `test_producer_cannot_confirm_candidature`, `test_face_cannot_confirm_another_face_candidature`).
7. **Given** the frontend already guards the "Confirmer ma participation" button on `candidature.status === 'accepted'` alone (`CandidatureCard.vue:32`) **When** auditing `FaceCandidaturesPage.vue` + `CandidatureCard.vue` **Then** confirm that no composite condition references `mission_payment`, `payment_status`, or `Paid` — if any such reference is found (currently none per `rg`/`mgrep` audit at story creation time), it must be removed. No other frontend changes are expected.
8. **Given** the full Candidature + Messaging + MissionPayment regression suites **When** run after the refactor **Then** all pass with no new failures: `cd backend && php artisan test --filter=Candidature`, `--filter=MissionPayment`, `--filter=ConditionalChatUnlock`, `--filter=FaceNotification`, `cd frontend && npx vitest run src/features/candidature`, `cd frontend && npm run type-check`.

## Tasks / Subtasks

- [x] Task 1: Write Prove It failing test (AC: #2)
  - [x] Added `test_defensive_check_logs_invariant_violation_when_accepted_candidature_has_unpaid_payment` asserting both the 422 response and a `Log::warning` call with the full context payload.
  - [x] Red phase confirmed — test failed on the pre-refactor controller with `Method warning(<Any Arguments>) ... should be called at least 1 times but called 0 times`.
- [x] Task 2: Decide on strategy — defensive conservation vs pure removal (AC: #1, #2)
  - [x] **Decision: defensive conservation with log.** Matches the epic recommendation; zero user-visible change (same 422 + same message), gives ops a greppable canary for any future regression, and costs only ~6 lines.
- [x] Task 3: Apply the refactor to `Face/CandidatureController::confirm` (AC: #1, #2)
  - [x] Kept the payment check and added `Log::warning('INVARIANT_VIOLATION: candidature accepted without paid payment', [...])` immediately before the existing 422 response. Context includes `candidature_id`, `mission_id`, `payment_id` (nullable via `?->`), `payment_status` (nullable via `?->status?->value`).
  - [x] Other branches untouched (`INVALID_STATUS`, ownership checks, `NOT_IN_FINAL_SELECTION`, confirmation-in-progress cascade).
- [x] Task 4: Re-run the Prove It test (AC: #2)
  - [x] Test passes — controller emits the warning and returns 422.
- [x] Task 5: Update `FaceConfirmCandidatureTest::test_cannot_confirm_candidature_when_payment_is_not_confirmed` (AC: #5)
  - [x] Extended to call `Log::spy()` and assert the `INVARIANT_VIOLATION:` warning fired exactly once. Existing 422 + `PAYMENT_NOT_CONFIRMED` assertions preserved.
- [x] Task 6: Frontend audit (AC: #7)
  - [x] Ran `rg` across `frontend/src/features/candidature` and `frontend/src/pages/face/candidature`. All matches are producer-side selection/payment UI (`ProducerCandidaturesSection.vue`, its `.spec.ts`) — none gate the Face "Confirmer" button. `CandidatureCard.vue:32` still guards on `candidature.status === 'accepted'` alone. **No frontend changes needed.**
- [x] Task 7: Non-regression validation (AC: #6, #8)
  - [x] `cd backend && php artisan test --filter=FaceConfirmCandidature` — 8 passed (7 existing + new Prove It).
  - [x] `cd backend && php artisan test --filter=Candidature` — 235 passed.
  - [x] `cd backend && php artisan test --filter=MissionPayment` — 26 passed.
  - [x] `cd backend && php artisan test --filter=ConditionalChatUnlock` — 23 passed.
  - [x] `cd backend && php artisan test --filter=FaceNotification` — 21 passed.
  - [x] `cd frontend && npx vitest run src/features/candidature` — 20 passed.
  - [x] `cd frontend && npm run type-check` — clean.

## Dev Notes

### Primary Files (verify line numbers via Read before editing)

| File | Action | Key Lines |
|------|--------|-----------|
| `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php` | MODIFY — either log+keep or delete the payment check | L155-246 (`confirm` method); L181-192 (payment check block targeted by this story); L194-201 (unchanged `NOT_IN_FINAL_SELECTION`); L172-179 (unchanged `INVALID_STATUS`) |
| `backend/tests/Feature/Candidature/FaceConfirmCandidatureTest.php` | MODIFY — extend or delete `test_cannot_confirm_candidature_when_payment_is_not_confirmed`; add Prove It test | L110-122 (target test); full file is 191 lines |
| `backend/app/Services/MissionPaymentService.php` | READ-ONLY — verify FIX-20.1 refactor landed and `Accepted` transition now lives in `applySelectionOutcomesOnPaid` | L463-538 (`markAsPaid`), L540+ (`applySelectionOutcomesOnPaid`) |
| `frontend/src/features/candidature/components/CandidatureCard.vue` | READ-ONLY audit — verify confirm button guard stays `candidature.status === 'accepted'` | L32 (`canConfirm` computed) |
| `frontend/src/pages/face/candidature/FaceCandidaturesPage.vue` | READ-ONLY audit — verify no payment-status composite condition | whole file |

### Architecture Patterns to Follow

- **Defensive log pattern**: use `Log::warning(...)` with a structured context array. Follow the existing MissionPaymentService style (`['payment_id' => $payment->id, 'mission_id' => ..., 'phase' => ..., 'error_class' => ...]`). The key name `INVARIANT_VIOLATION:` (with colon) makes the log line easy to `grep` in production.
- **Controller error response format**: the `{ error: { code, message } }` envelope is mandatory (see `project-context.md` → API Response Format). Preserve it.
- **Single responsibility**: this story touches exactly one branch in one method. No refactor of neighbouring logic, no "while we're here" cleanups. The `dispatchSelectionNotifications`-style internal plumbing touched by FIX-20.4 is out of scope here.
- **Test conventions**: Laravel 12 + Pest-compatible PHPUnit, `use RefreshDatabase`, `actingAs($user)->postJson(...)`, `$this->assertJsonPath(...)`, `Log::spy()` for log assertions (already used in `MissionPaymentInitiationTest`).

### Previous Story Intelligence

**FIX-20.1 (just moved to review, commit `eef57c5`)** — the refactor this story depends on:
- `MissionPaymentService::prepareSelectionForPayment` no longer mutates candidature statuses.
- `MissionPaymentService::applySelectionOutcomesOnPaid` fires at webhook `Paid` time: transitions selected candidatures `Pending → Accepted`, non-selected → `Rejected`, provisions `Conversation` via `firstOrCreate`, dispatches `candidature_rejected` notifications.
- `markAsPaid` early-returns when the payment is already `Paid`, providing webhook-replay idempotence.
- **Direct consequence for this story:** by the time a Face's candidature row reads `status = 'accepted'`, the corresponding `MissionPayment.status` *must* be `Paid`. The check in `confirm` at L185-192 becomes a tautology under normal flow — it can only fire if (a) someone reintroduces a bad transition, or (b) data is corrupted out-of-band. Defensive conservation turns this tautology into a detection signal instead of dead weight.

**FIX-20.3 (done, commit `ac2719d`)** — removed the legacy manual accept endpoint that was the only path that set `Accepted` without creating a `MissionPayment`. Any latent "Accepted-without-Paid" rows on production therefore come from the historical bug rather than a live code path.

**FIX-20.5 (done, commit `9bca244`)** — `useConfirmCandidature.ts` now maps 422/429/500/network errors and surfaces the backend `error.message`. Consequence: whatever the decision taken in Task 2, the Face will see either the backend `PAYMENT_NOT_CONFIRMED` message directly (defensive conservation) or the `NOT_IN_FINAL_SELECTION` message (pure removal — since the next check in line would fire for the same out-of-contract row). Either way the Face gets an actionable message; the choice is about operator observability, not user experience.

**FIX-20.6 (backlog)** — not a prerequisite here. The audit found 4 Accepted + 177 Rejected rows from the historical bug; FIX-20.6 will reconcile them before FIX-20.1 is deployed to prod. By the time this story lands in review, the DB should already be clean. If it is not yet, pick **defensive conservation** without hesitation — the log becomes a canary that tells ops whether any stale `Accepted-without-Paid` row is still lurking.

### Deferred Work Awareness

From `deferred-work.md` — no open items intersect this story's scope. FIX-20.4 will later strip the now-redundant candidature rollback from `MissionPaymentService::handleInitiationFailure`, but that touches the *payment initiation* path, not the *confirm participation* path addressed here.

### What NOT to Touch

- **`dispatchSelectionNotifications` and the notifications plumbing** — FIX-20.1 already moved the relevant notifications; FIX-20.4 will finish that cleanup. Out of scope.
- **`Conversation::firstOrCreate`** — FIX-20.1 already hooks this into `applySelectionOutcomesOnPaid`. Do not replicate it in `confirm` or anywhere else.
- **Frontend `useConfirmCandidature.ts`** — FIX-20.5 already surfaces backend messages. Do not change error-mapping logic.
- **`CandidatureStatus` enum** — no new values, no changes to `allowsChatAccess()` or `allowsRatings()`.
- **Runbook `docs/runbook-mission-payment-recovery.md`** — FIX-20.4 will update it. Leave it alone here.

### Decision Gate — Defensive Conservation vs Pure Removal

The epic's explicit recommendation is **defensive conservation with log** and the default choice for this story is the same. The tradeoffs:

| Criterion | Defensive conservation | Pure removal |
|-----------|------------------------|--------------|
| LOC delta | +3 lines (log call + context array) | -10 lines (whole `if` block) |
| Operator observability | Invariant violation is logged and greppable → canary for any regression | Silent under regression; detection would rely on downstream `NOT_IN_FINAL_SELECTION` firing with a misleading message |
| Test impact | Extend 1 test to also assert the log | Delete 1 test |
| Alignment with CLAUDE.md "no comments, no error handling for impossible scenarios" rule | Minor tension — epic classifies the log as a legit canary, not noise | Aligns more strictly |
| Future FIX-20 refactors that might reintroduce a bad transition | Detected by log within minutes | Invisible until a user reports a 422 on `NOT_IN_FINAL_SELECTION` |

Pick **defensive conservation** unless Amakira explicitly overrides. Record the choice and reasoning in Dev Agent Record → Completion Notes.

### Prove It Pattern

The CLAUDE.md convention requires a failing test before any fix:

1. **Write first**: `test_defensive_check_logs_invariant_violation_when_accepted_candidature_has_unpaid_payment` — forces `Accepted` + `MissionPayment.status = Pending`, calls `confirm`, asserts the 422 AND asserts `Log::warning` was called with the expected context. (Under pure removal, rewrite to assert a 422 `NOT_IN_FINAL_SELECTION` or drop the Prove It step since the change becomes a deletion.)
2. **Run**: the test MUST FAIL on current code — the controller does not call `Log::warning`.
3. **Apply the change** (Task 3).
4. **Run again**: MUST PASS.
5. **Run the rest of `FaceConfirmCandidatureTest`** — all other tests must continue to pass.

### Non-Regression Checklist

After implementation, ALL of these must pass (run from repo root — each command `cd`s into the right subdirectory; there is no root-level `type-check` script, `frontend/package.json:9` is the only definition):
- `cd backend && php artisan test --filter=FaceConfirmCandidature`
- `cd backend && php artisan test --filter=Candidature`
- `cd backend && php artisan test --filter=MissionPayment`
- `cd backend && php artisan test --filter=ConditionalChatUnlock`
- `cd backend && php artisan test --filter=FaceNotification`
- `cd frontend && npx vitest run src/features/candidature`
- `cd frontend && npm run type-check`

### Project Structure Notes

- Backend follows Laravel 12 conventions: controllers in `app/Http/Controllers/Api/V1/{Domain}/`, services in `app/Services/`, enums in `app/Enums/`.
- Tests in `tests/Feature/Candidature/` mirror the domain structure.
- French accents required on all user-facing strings (memory rule `feedback_accents_francais`). The string `'Le paiement de la mission doit être confirmé avant de pouvoir confirmer votre participation'` is already correct under defensive conservation and stays untouched.
- No comments unless explaining "why" (CLAUDE.md). The `Log::warning` line is self-explanatory; no surrounding comment required.
- No Co-Authored-By Claude in commits (project memory rule).

### References

- [Source: _bmad-output/planning-artifacts/epics-postlaunch-fixes-8.md#FIX-20.2]
- [Source: _bmad-output/implementation-artifacts/fix-20-1-move-accepted-transition-to-payment-webhook.md#Dev Agent Record]
- [Source: _bmad-output/implementation-artifacts/fix-20-5-confirm-candidature-error-mapping.md]
- [Source: _bmad-output/implementation-artifacts/fix-20-3-remove-legacy-manual-accept-endpoint.md]
- [Source: backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php — L155-246]
- [Source: backend/tests/Feature/Candidature/FaceConfirmCandidatureTest.php — 7 tests, 191 lines]
- [Source: backend/app/Services/MissionPaymentService.php — post-FIX-20.1, markAsPaid + applySelectionOutcomesOnPaid]
- [Source: frontend/src/features/candidature/components/CandidatureCard.vue — L32 `canConfirm` computed]

## Dev Agent Record

### Agent Model Used

claude-opus-4-7 (1M context)

### Debug Log References

- Red phase confirmed on the new Prove It test: `Method warning(<Any Arguments>) from Mockery_2_Illuminate_Log_LogManager should be called at least 1 times but called 0 times.` Matches the predicted failure mode in the story — the controller had no `Log::warning` call before the refactor.
- Green phase: all 8 tests in `FaceConfirmCandidatureTest` pass. Full regression matrix green (Candidature 235, MissionPayment 26, ConditionalChatUnlock 23, FaceNotification 21, frontend candidature 20, type-check clean).

### Completion Notes List

- **Task 2 decision — defensive conservation with log.** Rationale: the 422 response and French message are preserved byte-for-byte, so no user-visible change. The added `Log::warning('INVARIANT_VIOLATION: candidature accepted without paid payment', …)` acts as a greppable canary. If FIX-20.6 misses a stale row or a future refactor reintroduces a bad transition in `MissionPaymentService`, this line appears in production logs with enough context (`candidature_id`, `mission_id`, `payment_id`, `payment_status`) to act on it. Cost: ~6 lines, two nullable chained accesses (`?->id`, `?->status?->value`) for the `$missionPayment === null` edge case.
- **Frontend audit confirmed no changes needed.** `rg` over `frontend/src/features/candidature/` and `frontend/src/pages/face/candidature/` showed that every payment-related reference lives inside the Producer selection/payment flow (`ProducerCandidaturesSection.vue` + its spec). The Face confirm button still reads `canConfirm = computed(() => props.candidature.status === 'accepted')` in `CandidatureCard.vue:32`.
- Controller's existing null-safety on the joined eager-loaded mission payment (`$mission->payment`) is handled by the chained `?->` operators on the new `Log::warning` call. No new edge case introduced.

### File List

**Modified:**
- `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php`
- `backend/tests/Feature/Candidature/FaceConfirmCandidatureTest.php`

**Verified no change needed:**
- `backend/app/Services/MissionPaymentService.php`
- `backend/app/Enums/CandidatureStatus.php`
- `frontend/src/features/candidature/components/CandidatureCard.vue`
- `frontend/src/pages/face/candidature/FaceCandidaturesPage.vue`

### Change Log

| Date | Summary |
|------|---------|
| 2026-04-18 | FIX-20.2 implementation (defensive conservation): added an `INVARIANT_VIOLATION:` `Log::warning` canary to `Face/CandidatureController::confirm` before the existing 422 `PAYMENT_NOT_CONFIRMED` response; extended the corresponding test to assert the log; added one Prove It test covering the happy path of the defensive guard. No frontend changes. |
