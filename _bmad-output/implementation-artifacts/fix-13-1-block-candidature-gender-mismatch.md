# Story FIX-13.1: Bloquer candidature si genre Face ≠ genre requis par la mission

Status: done

## Story

As a **Producer**,
I want **only Faces matching the required gender to be able to apply to my mission**,
so that **I don't receive irrelevant candidatures and the casting process is more efficient**.

## Acceptance Criteria

1. **Given** a mission with `genre_voulu = 'homme'` **When** a Face with `sexe = 'femme'` tries to apply **Then** the candidature is rejected with a clear error message
2. **Given** a mission with `genre_voulu = 'femme'` **When** a Face with `sexe = 'homme'` tries to apply **Then** the candidature is rejected with a clear error message
3. **Given** a mission with `genre_voulu = 'tous'` **When** any Face applies **Then** the candidature is accepted regardless of the Face's gender
4. **Given** a Face with `sexe = 'autre'` **When** they try to apply to a mission with `genre_voulu = 'homme'` or `'femme'` **Then** the candidature is rejected (only `tous` missions accept `autre`)
5. **Given** a Face with `sexe = null` (not yet filled in profile) **When** they try to apply to any mission with a specific gender requirement **Then** the candidature is rejected with a message asking them to complete their profile
6. **Given** a mission detail page viewed by a Face whose gender doesn't match **Then** the "Postuler" button is disabled with an explanation tooltip/message
7. **Given** the backend rejects a candidature for gender mismatch **Then** the API returns a 422 with a specific error code (`gender_mismatch`) and a user-friendly French message

## Tasks / Subtasks

### Backend Tasks

- [x] Task 1: Add gender validation in `CandidatureController::store()` (AC: #1, #2, #3, #4, #5, #7)
  - [x] In `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php`, `store()` method (around line 80, after existing checks):
    - Load the Face's `sexe` from the authenticated user's `userable` relation
    - Load the Mission's `genre_voulu` (already available via route model binding)
    - Add validation logic:
      - If `mission.genre_voulu === 'tous'` → allow (skip check)
      - If `face.sexe === null` → reject with message "Veuillez compléter votre profil (genre) avant de postuler"
      - If `face.sexe !== mission.genre_voulu` → reject with message "Cette mission recherche un profil {genre_voulu_label}. Votre profil ne correspond pas au genre requis."
    - Return 422 JSON: `{ "error": { "code": "gender_mismatch", "message": "..." } }`
  - [x] Do NOT add this to `StoreCandidatureRequest` — it requires loading the mission relation, which is controller-level logic

### Backend Tests

- [x] Task 2: Write tests for gender validation (AC: #1-#5, #7)
  - [x] Add tests to `backend/tests/Feature/Candidature/CandidatureTest.php` or create a new test file
  - [x] Test cases:
    - Face (homme) can apply to mission with genre_voulu = 'homme' → 201
    - Face (homme) cannot apply to mission with genre_voulu = 'femme' → 422 with `gender_mismatch`
    - Face (femme) can apply to mission with genre_voulu = 'femme' → 201
    - Face (femme) cannot apply to mission with genre_voulu = 'homme' → 422
    - Any Face can apply to mission with genre_voulu = 'tous' → 201
    - Face (autre) cannot apply to mission with genre_voulu = 'homme' → 422
    - Face (autre) cannot apply to mission with genre_voulu = 'femme' → 422
    - Face (autre) can apply to mission with genre_voulu = 'tous' → 201
    - Face with sexe = null cannot apply to gendered mission → 422 with profile completion message
    - Face with sexe = null can apply to mission with genre_voulu = 'tous' → 201
  - [x] Use existing test patterns: `actingAs($user)->withApiToken()->postJson('/api/v1/face/missions/{id}/apply', ...)`

### Frontend Tasks

- [x] Task 3: Disable "Postuler" button on mission detail when gender mismatch (AC: #6)
  - [x] In `frontend/src/pages/face/mission/FaceMissionDetailPage.vue`:
    - The mission data includes `genre_voulu` (from `MissionResource`)
    - The auth store provides `user.userable.sexe` (Face gender)
    - Add a computed `isGenderMismatch` that checks:
      - `mission.genre_voulu !== 'tous' && authStore.user?.userable?.sexe !== mission.genre_voulu`
    - When `isGenderMismatch` is true: disable the "Postuler" button and show an inline message like "Cette mission recherche un profil {label}. Votre genre ne correspond pas."
    - When `face.sexe` is null: show "Complétez votre profil pour postuler"

- [x] Task 4: Handle 422 gender_mismatch error in apply composable (AC: #7)
  - [x] In `frontend/src/features/candidature/composables/useApplyToMission.ts`:
    - The composable already handles error responses
    - Add specific handling for `error.code === 'gender_mismatch'` to show a toast with the server's French message
    - This is a safety net — the frontend button should already be disabled (Task 3), but the backend is the source of truth

### Frontend Tests

- [x] Task 5: Update FaceMissionDetailPage tests (AC: #6)
  - [x] Add tests verifying:
    - "Postuler" button is disabled when Face gender doesn't match mission's genre_voulu
    - Warning message is shown when gender mismatch
    - "Postuler" button is enabled when genre_voulu = 'tous'
    - "Postuler" button is enabled when genders match

### Review Findings

- [x] [Review][Patch] Auth refresh can falsely lock out eligible Faces after email verification refresh [backend/routes/api.php:80]
- [x] [Review][Patch] Mission detail replaces the apply CTA instead of rendering it disabled with the mismatch explanation [frontend/src/pages/face/mission/FaceMissionDetailPage.vue:530]
- [x] [Review][Patch] `gender_mismatch` safety net does not show the required toast message from the composable [frontend/src/features/candidature/composables/useApplyToMission.ts:47]
- [x] [Review][Patch] Mission detail now fails open for restored sessions whose cached auth payload still lacks `userable.sexe`, so mismatch CTA blocking is skipped until a refresh [frontend/src/pages/face/mission/FaceMissionDetailPage.vue:78]

## Dev Notes

### What Already Exists (REUSE — DO NOT RECREATE)

- **`CandidatureController::store()`** at `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php:59-122` — already has checks for published status, deadline, and duplicate application. Add gender check after these existing checks (around line 80).
- **`MissionGender` enum** at `backend/app/Enums/MissionGender.php` — values: `Homme = 'homme'`, `Femme = 'femme'`, `Tous = 'tous'`. Has `label()` method for display.
- **`FaceGender` enum** at `backend/app/Enums/FaceGender.php` — values: `HOMME = 'homme'`, `FEMME = 'femme'`, `AUTRE = 'autre'`.
- **`Mission.genre_voulu`** — string column cast to `MissionGender` enum in `Mission.php:118`.
- **`Face.sexe`** — string column cast to `FaceGender` enum in `Face.php:65`. Can be null if not yet filled.
- **`MissionResource`** at `backend/app/Http/Resources/MissionResource.php` — already returns `genre_voulu` and `genre_voulu_label`.
- **`FaceMissionDetailPage.vue`** at `frontend/src/pages/face/mission/FaceMissionDetailPage.vue` — shows mission details with genre info and "Postuler" button.
- **`useApplyToMission`** at `frontend/src/features/candidature/composables/useApplyToMission.ts` — composable for applying to a mission.
- **Auth store** provides `user.userable.sexe` for the logged-in Face.

### CRITICAL: Gender Matching Logic

The matching logic must handle the asymmetry between `MissionGender` and `FaceGender`:

| Mission `genre_voulu` | Face `sexe` | Result |
|----------------------|-------------|--------|
| `tous`               | any (homme/femme/autre/null) | ✅ Allow |
| `homme`              | `homme`     | ✅ Allow |
| `homme`              | `femme`     | ❌ Block |
| `homme`              | `autre`     | ❌ Block |
| `homme`              | `null`      | ❌ Block (profile incomplete) |
| `femme`              | `femme`     | ✅ Allow |
| `femme`              | `homme`     | ❌ Block |
| `femme`              | `autre`     | ❌ Block |
| `femme`              | `null`      | ❌ Block (profile incomplete) |

Key insight: `autre` Faces can ONLY apply to `tous` missions. This is because the Producer explicitly chose a gender for their casting, and `autre` doesn't match either specific requirement.

### CRITICAL: Error Response Format

Follow the project's error response convention:
```json
{
  "error": {
    "code": "gender_mismatch",
    "message": "Cette mission recherche un profil Homme. Votre profil ne correspond pas au genre requis."
  }
}
```

Use `abort(422, ...)` or return a JSON response directly — check the existing pattern in the controller for the published/deadline/duplicate checks.

### CRITICAL: Backend Is Source of Truth

The frontend disabling the button is a UX convenience. The backend MUST enforce the rule regardless, because:
1. The user could manipulate the frontend
2. API could be called directly
3. Race conditions (gender changed after page load)

### Scope Boundaries

- DO NOT add gender filtering to the mission list API — this story only blocks the candidature submission
- DO NOT modify the mission creation form — genre_voulu options are already correct
- DO NOT add a new status or enum value — use existing enums
- DO NOT store gender info on the candidature — just validate at submission time
- DO NOT modify `StoreCandidatureRequest` — the gender check requires the mission relation, do it in the controller

### Previous Story Intelligence

- Tests use `actingAs($user)->withApiToken()` pattern for API endpoint tests
- Error responses follow `{ "error": { "code": "...", "message": "..." } }` format
- Frontend uses `data-testid` attributes for test element selection
- Vue components use `<script setup lang="ts">` with Composition API

### References

- [Source: backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php:59-122 — store() method]
- [Source: backend/app/Http/Requests/Face/StoreCandidatureRequest.php — current validation rules]
- [Source: backend/app/Enums/MissionGender.php — mission gender enum]
- [Source: backend/app/Enums/FaceGender.php — face gender enum]
- [Source: backend/app/Models/Mission.php:118 — genre_voulu cast]
- [Source: backend/app/Models/Face.php:65 — sexe cast]
- [Source: backend/app/Http/Resources/MissionResource.php — genre_voulu + genre_voulu_label]
- [Source: frontend/src/pages/face/mission/FaceMissionDetailPage.vue — mission detail + apply button]
- [Source: frontend/src/features/candidature/composables/useApplyToMission.ts — apply composable]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6 (1M context)

### Debug Log References

- Existing `FaceApplyToMissionTest` setUp needed update: Face factory creates faces without `sexe` (null), mission factory picks random `genre_voulu`. Fixed by giving test face `sexe='homme'` and using `forAll()` on default mission.

### Completion Notes List

- Task 1: Added gender validation in `CandidatureController::store()` after the duplicate-application check. Uses `MissionGender::Tous` bypass, null sexe detection with profile-completion message, and value comparison for mismatch. Returns 422 with `gender_mismatch` error code.
- Task 2: Created `CandidatureGenderValidationTest.php` with 11 tests covering all combinations: homme/femme/autre/null faces against homme/femme/tous missions. All pass. Updated existing `FaceApplyToMissionTest` setUp to be compatible with new validation.
- Task 3: Added `isGenderMismatch` and `genderMismatchMessage` computed properties to `FaceMissionDetailPage.vue`. Added new UI state (amber warning block with `data-testid="gender-mismatch-block"`) between email-verification check and apply button.
- Task 4: Added explicit `gender_mismatch` handling in `useApplyToMission.ts` composable (safety net — frontend button is already disabled).
- Task 5: Added 4 frontend tests to `FaceMissionDetailPage.spec.ts` covering: gender mismatch block shown, profile completion message, apply button visible for `tous`, apply button visible when genders match. All 17 tests pass.

### File List

- `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php` (modified)
- `backend/tests/Feature/Candidature/CandidatureGenderValidationTest.php` (new)
- `backend/tests/Feature/Candidature/FaceApplyToMissionTest.php` (modified)
- `frontend/src/pages/face/mission/FaceMissionDetailPage.vue` (modified)
- `frontend/src/features/candidature/composables/useApplyToMission.ts` (modified)
- `frontend/src/pages/face/mission/__tests__/FaceMissionDetailPage.spec.ts` (modified)

## Change Log

- 2026-04-08: Implemented gender validation for candidature submission (FIX-13.1). Backend enforces gender matching at API level (422 with `gender_mismatch`). Frontend disables apply button with warning message when gender mismatch. 11 backend tests + 4 frontend tests added.
