# Story 4.2: Producer Bio

Status: done

## Story

As a **Producer**,
I want **to write a bio describing myself or my agency**,
So that **Faces understand who they might work with**.

## Acceptance Criteria

1. **Given** I am logged in as a Producer on my profile page, **When** I enter my bio (max 500 characters), **Then** the bio is saved and displayed on my profile

2. **Given** I try to save a bio exceeding 500 characters, **When** I submit the form, **Then** I see a validation error message

3. **Given** I want to update my bio, **When** I edit and save a new bio, **Then** the old bio is replaced with the new one

4. **Given** I want to clear my bio, **When** I submit an empty bio, **Then** my bio is set to null

5. **Given** I am not logged in or not a Producer, **When** I try to update my bio, **Then** I receive a 401/403 error

**(FR23)**

## Tasks / Subtasks

### Task 1: Add Bio Column to Producers Table (AC: #1)

- [x] 1.1 Create migration `add_bio_to_producers_table`
- [x] 1.2 Add `bio` column: `text()->nullable()`
- [x] 1.3 Run migration and verify schema

### Task 2: Update Producer Model (AC: #1)

- [x] 2.1 Add `bio` to `$fillable` array
- [x] 2.2 Verify ProducerResource already includes `bio` field (add if missing)

### Task 3: Create UpdateBioRequest (AC: #1, #2, #5)

- [x] 3.1 Create `app/Http/Requests/Producer/UpdateBioRequest.php`
- [x] 3.2 Implement `authorize()` - must be authenticated Producer
- [x] 3.3 Implement validation rules: `bio` nullable, string, max:500
- [x] 3.4 Add French error messages

### Task 4: Add Bio Methods to ProfileController (AC: #1, #3, #4)

- [x] 4.1 Add `showBio(Request $request)` method - return current bio
- [x] 4.2 Add `updateBio(UpdateBioRequest $request)` method - update bio
- [x] 4.3 Use existing `getAuthenticatedProducer()` helper method

### Task 5: Add API Routes (AC: #1, #5)

- [x] 5.1 Add routes to `routes/api/producer.php`:
  ```php
  Route::get('/profile/bio', [ProfileController::class, 'showBio']);
  Route::put('/profile/bio', [ProfileController::class, 'updateBio']);
  ```

### Task 6: Create Backend Tests (AC: #1, #2, #3, #4, #5)

- [x] 6.1 Create `tests/Feature/Producer/BioTest.php`
- [x] 6.2 Test successful bio update returns 200 with updated bio
- [x] 6.3 Test bio exceeding 500 characters returns validation error
- [x] 6.4 Test clearing bio (empty string/null) works
- [x] 6.5 Test unauthenticated user gets 401
- [x] 6.6 Test Face user cannot access Producer endpoints (403)
- [x] 6.7 Run tests with `php artisan test --filter=Bio`

### Task 7: Update Frontend Types (AC: #1)

- [x] 7.1 Verify `Producer` interface in `frontend/src/features/producer/types.ts` includes `bio: string | null`
- [x] 7.2 Add `ProducerBioResponse` interface if needed

### Task 8: Add Bio API Methods to producerApi (AC: #1)

- [x] 8.1 Add `getBio()` method to `producerApi.ts`
- [x] 8.2 Add `updateBio(bio: string | null)` method to `producerApi.ts`

### Task 9: Create useProducerBio Composable (AC: #1, #3, #4)

- [x] 9.1 Create `frontend/src/features/producer/composables/useProducerBio.ts`
- [x] 9.2 Include refs: `bio`, `isLoading`, `isSaving`, `error`, `charCount`
- [x] 9.3 Implement `fetchBio()`, `saveBio(bio)` functions
- [x] 9.4 Add character count validation (500 max)
- [x] 9.5 Return success messages in French

### Task 10: Create ProducerBioEditor Component (AC: #1, #2)

- [x] 10.1 Create `frontend/src/features/producer/components/ProducerBioEditor.vue`
- [x] 10.2 Use textarea with character counter
- [x] 10.3 Props: `bio`, `isSaving`, `error`
- [x] 10.4 Events: `@save`
- [x] 10.5 Show character count (current/max) with warning when approaching limit
- [x] 10.6 Disable save button when over limit or saving

### Task 11: Integrate Bio Editor in ProfileEditPage (AC: #1)

- [x] 11.1 Import useProducerBio composable
- [x] 11.2 Add ProducerBioEditor section below photo section
- [x] 11.3 Fetch bio on mount
- [x] 11.4 Handle save with success toast

### Task 12: Create Frontend Tests (AC: #1, #2, #3, #4)

- [x] 12.1 Create `frontend/src/features/producer/composables/__tests__/useProducerBio.spec.ts`
- [x] 12.2 Create `frontend/src/features/producer/components/__tests__/ProducerBioEditor.spec.ts`
- [x] 12.3 Test save, validation, error states
- [x] 12.4 Test character count updates
- [x] 12.5 Run tests with `npm run test:run`

## Dev Notes

### Critical Implementation Patterns

This is the SECOND story for Producer profile features. Follow the patterns established in Story 4.1 (Producer Profile Photo).

### Backend Pattern - Follow Face Bio Implementation

The Face bio implementation (Story 3.5) is the reference:

```
app/
├── Http/
│   ├── Controllers/Api/V1/Face/BioLocationController.php  → Follow pattern
│   └── Requests/Face/UpdateBioLocationRequest.php         → Simplify for bio only
└── Models/Face.php                                         → bio field pattern
```

### Database Schema

Current `producers` table has:
- `id`, `type`, `agency_name`, `first_name`, `last_name`, `profile_photo`, `profile_photo_thumbnail`, `timestamps`

Add:
- `bio` (text, nullable)

### Producer Types Context

Remember the two producer types when writing placeholder text or hints:
- **Agency** → Bio describes the agency and its work
- **Particulier** → Bio describes the individual producer

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/producer/profile/bio` | Get current bio |
| PUT | `/api/v1/producer/profile/bio` | Update bio |

### Frontend Structure

```
frontend/src/
├── features/producer/
│   ├── services/producerApi.ts                     → ADD bio methods
│   ├── composables/
│   │   ├── useProducerBio.ts                       → CREATE
│   │   └── __tests__/useProducerBio.spec.ts        → CREATE
│   └── components/
│       ├── ProducerBioEditor.vue                   → CREATE
│       └── __tests__/ProducerBioEditor.spec.ts     → CREATE
└── pages/producer/
    └── ProfileEditPage.vue                         → MODIFY (add bio section)
```

### Existing Producer ProfileController

The `ProfileController` already exists at `app/Http/Controllers/Api/V1/Producer/ProfileController.php` with:
- `getAuthenticatedProducer()` helper method
- `show()` - get full profile
- `updatePhoto()` - photo upload
- `deletePhoto()` - photo delete

Add the bio methods to this existing controller.

### Existing ProducerResource

The `ProducerResource` at `app/Http/Resources/ProducerResource.php` should already include all producer fields. Verify it includes `bio`.

### Character Count UI Pattern

Follow the Face bio textarea pattern with character count:
```vue
<div class="relative">
  <textarea v-model="bio" maxlength="500" />
  <span :class="{ 'text-red-500': charCount > 450 }">
    {{ charCount }}/500
  </span>
</div>
```

### Test Patterns

Use these established patterns from Story 4.1:
- `RefreshDatabase` trait
- Create test users via factory: `User::factory()->forProducer()->create()`
- Test authorization: Face user → 403, unauthenticated → 401
- French error messages in validation

### Previous Story Intelligence (4.1)

From Story 4.1 (Producer Profile Photo):
- Producer routes are in `routes/api/producer.php`
- Producer controller uses `getAuthenticatedProducer()` helper
- ProducerResource wraps all responses
- Frontend composables follow `useProducerProfilePhoto.ts` pattern
- Tests use `data-testid` for reliable selection
- French messages for all user-facing text

### Git Commit Pattern

Follow the established commit message format:
- `feat(producer): add bio field to database`
- `feat(producer): add bio API endpoints`
- `feat(producer): add bio editor component`
- `test(producer): add bio tests`

### Alignment with Project Patterns

- **Controller Pattern**: Add methods to existing `Producer/ProfileController`
- **Request Pattern**: Follow `UpdateBioLocationRequest` but simpler (bio only)
- **Composable Pattern**: Follow `useProducerProfilePhoto.ts` structure
- **Component Pattern**: Props-based with events for save action
- **API Response**: Use envelope format `{data, message}` for success

### References

- [Source: epics.md#Story 4.2 - Producer Bio]
- [Source: app/Http/Controllers/Api/V1/Face/BioLocationController.php - Bio controller pattern]
- [Source: app/Http/Requests/Face/UpdateBioLocationRequest.php - Bio validation pattern]
- [Source: app/Http/Controllers/Api/V1/Producer/ProfileController.php - Existing Producer controller]
- [Source: 4-1-producer-profile-photo.md - Previous story learnings]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

None

### Completion Notes List

- All 12 tasks and 31 subtasks implemented and verified
- Backend: Migration for bio column, UpdateBioRequest, ShowBioRequest, ProfileController methods (showBio, updateBio)
- Frontend: useProducerBio composable, ProducerBioEditor component, integrated into ProfileEditPage
- Character limit: 500 characters with visual warning at 450+
- French error messages for validation
- All tests pass: 10 backend bio tests, 40 frontend tests (19 composable + 21 component)
- Full backend test suite: 278 tests pass with no regressions

**Code Review Fixes (2026-01-16):**
- H1: Added toast.success() notifications in ProfileEditPage for bio/photo operations
- H2: Replaced hardcoded #e88b51 colors with weact-* Tailwind classes in ProducerBioEditor
- M1: Created ShowBioRequest for showBio endpoint to comply with Form Request pattern
- M3: Added bio loading state indicator in ProfileEditPage
- M4: Added prepareForValidation() to convert empty string to null for consistency

### File List

**Backend:**
- `backend/database/migrations/2026_01_15_161359_add_bio_to_producers_table.php` (NEW)
- `backend/app/Http/Requests/Producer/UpdateBioRequest.php` (NEW)
- `backend/app/Http/Requests/Producer/ShowBioRequest.php` (NEW - code review)
- `backend/app/Http/Controllers/Api/V1/Producer/ProfileController.php` (MODIFIED)
- `backend/app/Http/Resources/ProducerResource.php` (MODIFIED)
- `backend/app/Models/Producer.php` (MODIFIED)
- `backend/routes/api/producer.php` (MODIFIED)
- `backend/tests/Feature/Producer/BioTest.php` (NEW)

**Frontend:**
- `frontend/src/features/producer/types.ts` (MODIFIED)
- `frontend/src/features/producer/services/producerApi.ts` (MODIFIED)
- `frontend/src/features/producer/composables/useProducerBio.ts` (NEW)
- `frontend/src/features/producer/composables/__tests__/useProducerBio.spec.ts` (NEW)
- `frontend/src/features/producer/components/ProducerBioEditor.vue` (NEW, updated in code review)
- `frontend/src/features/producer/components/__tests__/ProducerBioEditor.spec.ts` (NEW)
- `frontend/src/pages/producer/ProfileEditPage.vue` (MODIFIED, updated in code review)

## Change Log

- 2026-01-16: Story 4.2 implementation verified complete - all tasks, tests pass, ready for review
- 2026-01-16: Code review complete - fixed 2 HIGH, 4 MEDIUM issues, status → done
