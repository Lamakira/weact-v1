# Story FIX-20.3: Supprimer le chemin legacy d'acceptation manuelle candidature Producer

Status: done

## Story

As a **Platform maintainer**,
I want **the legacy `POST /producer/candidatures/{id}/accept` endpoint removed entirely**,
so that **no code path can transition a candidature to `Accepted` without going through the paid selection flow, and the new FIX-20 contract (`Accepted` = paid + confirmable) cannot be violated**.

## Acceptance Criteria

1. **Given** a request to `POST /api/v1/producer/candidatures/{id}/accept` **When** any user calls it **Then** the server returns 404 (route removed).
2. **Given** the `Producer/CandidatureController` **When** inspecting it **Then** the `accept()` method no longer exists.
3. **Given** the backend test suite **When** running `php artisan test --filter=Candidature` **Then** all tests pass and `ProducerAcceptCandidatureTest.php` no longer exists.
4. **Given** the frontend composables **When** inspecting `candidature/composables/` **Then** `useAcceptCandidature.ts` no longer exists.
5. **Given** the frontend API service **When** inspecting `candidatureApi.ts` **Then** the `acceptCandidature` method no longer exists.
6. **Given** `ProducerCandidatureCard.vue` rendered outside selection mode **When** viewing a pending candidature **Then** no "Accepter" button is present.
7. **Given** `ProducerCandidaturesSection.vue` **When** inspecting its imports/handlers **Then** no reference to `acceptCandidature` remains.
8. **Given** the paid selection workflow (FIX-19) **When** running `php artisan test --filter=MissionPayment` **Then** all tests pass (non-regression).

## Tasks / Subtasks

- [x] Task 1: Caller audit — exhaustive grep for `acceptCandidature`/`useAcceptCandidature` across backend + frontend
  - [x] Expected callers found: `Producer/CandidatureController.php`, `routes/api/producer.php`, `ProducerAcceptCandidatureTest.php`, `useAcceptCandidature.ts`, `candidatureApi.ts`, `composables/index.ts`, `ProducerCandidatureCard.vue`, `ProducerCandidaturesSection.vue`, `ProducerCandidaturesSection.spec.ts`.
  - [x] **Unexpected callers found:**
    - `ConditionalChatUnlockTest.php` — 16 calls to the legacy endpoint as a test helper for promoting candidature status + provisioning conversation. Refactored: introduced `acceptCandidatureDirectly()` helper that sets status directly + `Conversation::firstOrCreate`. Dropped 2 tests that exclusively asserted legacy endpoint side-effects.
    - `FaceNotificationTest.php` — 1 test (`test_notification_created_when_producer_accepts_candidature`) calling the legacy endpoint. Deleted: notification on acceptance now emits via `MissionPaymentService`.
- [x] Task 2: Write Prove It failing tests (AC: #1, #6)
  - [x] Backend: `LegacyManualAcceptRouteRemovedTest.php` — `postJson('/api/v1/producer/candidatures/{id}/accept')` asserts 404. Verified it fails pre-fix (returns 200).
  - [x] Frontend: `ProducerCandidatureCard.spec.ts` — renders card outside selection mode, asserts "Accepter" button does NOT exist. Verified it fails pre-fix (button found).
- [x] Task 3: Remove backend code (AC: #1, #2, #3)
  - [x] Deleted `accept()` method from `Producer/CandidatureController.php` (lines 63–133, including `Conversation` and `MissionStatus` imports now unused).
  - [x] Deleted route `POST /candidatures/{candidature}/accept` from `routes/api/producer.php`.
  - [x] Deleted `tests/Feature/Candidature/ProducerAcceptCandidatureTest.php` entirely.
- [x] Task 4: Remove frontend code (AC: #4, #5, #6, #7)
  - [x] Deleted `useAcceptCandidature.ts`.
  - [x] Removed `acceptCandidature` method from `candidatureApi.ts`.
  - [x] Removed `useAcceptCandidature` export from `composables/index.ts`.
  - [x] Removed from `ProducerCandidatureCard.vue`: `accept` emit, `handleAccept`, `isAccepting` state, `resetAccepting` defineExpose entry, Accept button template block, unused `Check` lucide import.
  - [x] Removed from `ProducerCandidaturesSection.vue`: `useAcceptCandidature` import + destructured returns, `handleAccept` function, `@accept` binding.
  - [x] Cleaned `ProducerCandidaturesSection.spec.ts`: removed `acceptCandidature` mock entry.
- [x] Task 5: Verify non-regression (AC: #8)
  - [x] `php artisan test --filter=MissionPayment` — 21 tests, 190 assertions, all pass.
  - [x] `php artisan test --filter=Candidature` — 232 tests, 773 assertions, all pass.
  - [x] `php artisan test --filter=ConditionalChatUnlock` — 23 tests, 38 assertions, all pass.
  - [x] `php artisan test --filter=FaceNotification` — 21 tests, 71 assertions, all pass.
  - [x] Frontend candidature suite — 5 files, 20 tests, all pass.
  - [x] Frontend ProducerMissionCandidaturesPage spec — 15 tests, all pass.
  - [x] TypeScript (`vue-tsc --build`) — clean.
  - [x] ESLint — clean.

### Review Findings

- [x] [Review][Defer → Resolved by FIX-20.1] Paid selection still does not provision conversations for accepted candidatures [backend/app/Services/MissionPaymentService.php:155] — at FIX-20.3 merge time the gap was deferred as pre-existing and tracked for FIX-20.1. **Resolved in FIX-20.1 (commit `eef57c5`, 2026-04-18):** `MissionPaymentService::applySelectionOutcomesOnPaid` now calls `Conversation::firstOrCreate(['candidature_id' => $candidatureId])` for each newly-accepted candidature, invoked inside `markAsPaid` after the FedaPay webhook confirms the payment.

## Dev Notes

### Critical finding: Conversation creation gap — resolved by FIX-20.1

**Historical context (kept for the audit trail):** at FIX-20.3 merge time, the legacy `accept()` method was the ONLY production code path that called `Conversation::firstOrCreate`. After this removal, no backend code created conversations for candidatures accepted via the paid selection flow. Verified by grep across all of `backend/app/`:
- `Face/ConversationController` + `Producer/ConversationController` — read-only (list, show).
- `Face/MessageController` + `Producer/MessageController` — store messages on existing conversations.
- No observers, listeners, or events related to `Conversation` creation.
- No lazy creation in send-message flow.

Since the DB audit confirmed the legacy endpoint was never used in production, **no conversation had ever been created in prod for any candidature accepted through the paid flow** up to that point. The chat Face–Producer feature was silently non-functional.

**This was a pre-existing production gap, not a regression introduced by FIX-20.3.** It was tracked as FIX-20.1 scope and is now **resolved**: as of commit `eef57c5` (FIX-20.1, 2026-04-18), `MissionPaymentService::applySelectionOutcomesOnPaid` calls `Conversation::firstOrCreate(['candidature_id' => $candidatureId])` for each newly-accepted candidature, invoked inside `markAsPaid` after the FedaPay webhook confirms the payment. Paid-flow acceptances now provision a `Conversation` row automatically.

## Dev Agent Record

### Implementation Plan

- Full grep audit of all `acceptCandidature`/`useAcceptCandidature` references before any code change.
- Prove It pattern: write backend 404 regression guard + frontend button-absence test, verify both fail on current code.
- Remove backend (controller → route → test), then frontend (composable → service → index → card → section → specs).
- Refactor surprise callers in test suite rather than leaving broken test code.
- Validate full test suite (Candidature + MissionPayment + ConditionalChatUnlock + FaceNotification + frontend).

### Completion Notes

- Prove It pattern followed: both tests failed pre-fix for correct reasons (`200 != 404` and "Accepter button found"), passed post-fix.
- Net code change: -676 lines, +157 lines across 13 files (2 deleted, 2 created, 9 modified).
- The `ConditionalChatUnlockTest` refactor introduced a `acceptCandidatureDirectly()` helper that promotes candidature status + creates conversation directly. This bypasses the HTTP transport layer (which was irrelevant to what the chat tests were verifying) and is cleaner than the original approach.
- `ProducerMissionCandidaturesPage.vue` checked via grep — no `acceptCandidature` or `@accept` references found (only unrelated `is_accepting_candidatures` mission flag).

## File List

### Deleted
- `backend/tests/Feature/Candidature/ProducerAcceptCandidatureTest.php`
- `frontend/src/features/candidature/composables/useAcceptCandidature.ts`

### Created
- `backend/tests/Feature/Candidature/LegacyManualAcceptRouteRemovedTest.php` (Prove It regression guard)
- `frontend/src/features/candidature/components/__tests__/ProducerCandidatureCard.spec.ts` (Prove It regression guard)

### Modified
- `backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php`
- `backend/routes/api/producer.php`
- `backend/tests/Feature/Messaging/ConditionalChatUnlockTest.php`
- `backend/tests/Feature/Notification/FaceNotificationTest.php`
- `frontend/src/features/candidature/components/ProducerCandidatureCard.vue`
- `frontend/src/features/candidature/components/ProducerCandidaturesSection.vue`
- `frontend/src/features/candidature/components/__tests__/ProducerCandidaturesSection.spec.ts`
- `frontend/src/features/candidature/composables/index.ts`
- `frontend/src/features/candidature/services/candidatureApi.ts`

## Change Log

- 2026-04-14: Full removal of legacy manual-accept endpoint + frontend chain. Refactored 2 unexpected test callers (ConditionalChatUnlockTest, FaceNotificationTest). Added 2 Prove It regression guards. Surfaced Conversation creation gap. Commit `ac2719d` on dev (cherry-picked from worktree branch `worktree-agent-afd1bb86` commit `50c37f2`).
