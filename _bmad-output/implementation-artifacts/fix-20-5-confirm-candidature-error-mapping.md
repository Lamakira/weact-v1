# Story FIX-20.5: Filet de sécurité — mapping des erreurs de confirmation Face

Status: review

## Story

As a **Face**,
I want **to see a clear, actionable error message when my confirmation of participation fails**,
so that **I understand what happened and what to do next instead of seeing a generic "Une erreur est survenue"**.

## Acceptance Criteria

1. **Given** the backend returns a 422 with `error.message` **When** the Face clicks "Confirmer ma participation" **Then** the toast displays the backend message verbatim (e.g. "Le paiement de la mission doit être confirmé avant de pouvoir confirmer votre participation").
2. **Given** the backend returns a 422 without `error.message` **When** the Face clicks "Confirmer ma participation" **Then** the toast displays "Cette candidature ne peut pas être confirmée dans son état actuel."
3. **Given** the backend returns a 429 **When** the Face clicks "Confirmer ma participation" **Then** the toast displays "Trop de tentatives. Veuillez réessayer dans quelques instants."
4. **Given** the backend returns a 500/502/503/504 **When** the Face clicks "Confirmer ma participation" **Then** the toast displays "Le serveur a rencontré une erreur. Veuillez réessayer plus tard."
5. **Given** the request fails without a response (network error, timeout) **When** the Face clicks "Confirmer ma participation" **Then** the toast displays "Impossible de contacter le serveur. Vérifiez votre connexion et réessayez."
6. **Given** the backend returns a 400, 403, or 404 **When** the Face clicks "Confirmer ma participation" **Then** the existing mapping is preserved (non-regression): 400 reads backend message or fallback, 403 reads backend message or fallback, 404 always "Candidature introuvable".
7. **Given** any error response with a `message` field at `response.data.error.message` or `response.data.message` **When** the composable processes the error **Then** the backend message takes priority over the hardcoded fallback (for 400, 403, 422, and default branches).

## Tasks / Subtasks

- [x] Task 1: Extract `resolveConfirmErrorMessage` helper from inline catch block (AC: all)
  - [x] Replace the `if/else-if/else` chain in `useConfirmCandidature.ts` catch block with a single `resolveConfirmErrorMessage(err)` call.
  - [x] New helper reads `response.data.error.message` then `response.data.message` for backend payloads.
  - [x] Switch-case covers 400, 403, 404, 422, 429, 500/502/503/504, and default branch.
  - [x] Network error (no `response` field) handled as first guard clause.
- [x] Task 2: Write Prove It failing test (AC: #1)
  - [x] Mock a 422 with `PAYMENT_NOT_CONFIRMED` code + message. Assert `error.value` equals the backend message.
  - [x] Verified test fails against old code (generic fallback wins).
- [x] Task 3: Write remaining unit tests (AC: #2–#7)
  - [x] 422 without message → fallback "Cette candidature ne peut pas être confirmée dans son état actuel."
  - [x] 400 with backend message → backend message surfaced.
  - [x] 403 with backend message → backend message surfaced.
  - [x] 404 → always "Candidature introuvable".
  - [x] 429 → "Trop de tentatives. Veuillez réessayer dans quelques instants."
  - [x] 500 → "Le serveur a rencontré une erreur. Veuillez réessayer plus tard."
  - [x] Network error (no response) → "Impossible de contacter le serveur. Vérifiez votre connexion et réessayez."

## Dev Notes

- The fix is entirely localized to the composable. `FaceCandidaturesPage.vue` reads `confirmError.value` via `displayToast(confirmError.value || ...)` — no change needed there.
- The `ConfirmErrorPayload` and `ConfirmAxiosError` types are local to the composable (not exported) to avoid coupling with Axios internals.
- French accents verified on all UI strings.
- `FaceCandidaturesPage.vue` not modified — the `displayToast` call already reads from `error.value`.

## Dev Agent Record

### Implementation Plan

- Extract a `resolveConfirmErrorMessage(err: unknown): string` helper that replaces the inline `if/else-if/else` chain in the catch block.
- The helper checks `'response' in err` first (network error guard), then reads `response.status` via a switch-case, with backend message extraction (`error.message` then `message`) for branches that support it.
- Prove It: write the 422 PAYMENT_NOT_CONFIRMED test first, verify it fails on old code, then apply the fix.

### Completion Notes

- Prove It pattern followed: the 422 PAYMENT_NOT_CONFIRMED test was written and verified to fail against the old code (generic fallback "Une erreur est survenue" was returned), then the fix was applied and all 8 tests passed.
- The composable went from 66 lines (inline catch with `if/else-if/else`) to 85 lines (extracted helper with switch-case). Net clarity improvement.
- 400/403 branches now also read backend messages when provided (improvement over baseline which only read backend messages for 400, not 403 at top-level `message` key). Non-breaking.
- TypeScript type-check and ESLint passed after the change.

## File List

- `frontend/src/features/candidature/composables/useConfirmCandidature.ts` (modified)
- `frontend/src/features/candidature/composables/__tests__/useConfirmCandidature.spec.ts` (created)

## Change Log

- 2026-04-14: Extracted `resolveConfirmErrorMessage` helper, added 422/429/500-504/network mappings, wrote 8 unit tests covering every error branch. Commit `9bca244` on dev.
