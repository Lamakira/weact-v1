# Story 3.5: Face Bio and Location

Status: completed

## Story

As a **Face**,
I want **to add my bio and location information**,
So that **producers know who I am and where I'm based**.

## Acceptance Criteria

1. **Given** I am on my profile edit page, **When** I enter my bio (max 500 characters), **Then** the bio is saved and displayed on my profile

2. **Given** I enter a bio exceeding 500 characters, **When** I try to save, **Then** I see an error message "La bio ne peut pas dépasser 500 caractères"

3. **Given** I am on my profile edit page, **When** I select my location (ville, quartier, pays), **Then** my location is saved and displayed (e.g., "Cotonou, Akpakpa, Bénin")

4. **Given** the ville field is empty but quartier is filled, **When** I try to save, **Then** I see an error "La ville est requise pour enregistrer le quartier"

5. **Given** I save my bio and location, **When** the save is successful, **Then** I see a success toast notification

6. **Given** I clear my bio, **When** I save, **Then** the bio is cleared from my profile

7. **Given** I am viewing my profile, **When** the page loads, **Then** I see my bio and location displayed (if previously saved)

**(FR13, FR14)**

## Tasks / Subtasks

### Task 1: Update Face Model and Database Schema (AC: #1, #2, #3, #6, #7)

- [x] 1.1 Create migration `add_bio_and_location_to_faces`:
  - `bio` (text, nullable) - bio content
  - `ville` (string, max 100, nullable) - city
  - `quartier` (string, max 100, nullable) - district/neighborhood
  - `pays` (string, max 100, nullable, default 'Bénin') - country
- [x] 1.2 Update Face model:
  - Add to fillable: `bio`, `ville`, `quartier`, `pays`
  - Add accessor `formattedLocation()` returning "Ville, Quartier, Pays" format
  - Add to appends: `formatted_location`
- [x] 1.3 Run migration and verify schema

### Task 2: Create Bio and Location Form Request (AC: #1, #2, #3, #4)

- [x] 2.1 Create `app/Http/Requests/Face/UpdateBioLocationRequest.php`
- [x] 2.2 Define validation rules:
  ```php
  return [
      'bio' => ['nullable', 'string', 'max:500'],
      'ville' => ['nullable', 'string', 'max:100'],
      'quartier' => ['nullable', 'string', 'max:100', 'required_with:ville'],
      'pays' => ['nullable', 'string', 'max:100'],
  ];
  ```
- [x] 2.3 Add custom rule: quartier requires ville to be set
- [x] 2.4 Implement French error messages:
  - `bio.max` → "La bio ne peut pas dépasser 500 caractères"
  - `ville.max` → "La ville ne peut pas dépasser 100 caractères"
  - `quartier.required_with` → "La ville est requise pour enregistrer le quartier"
- [x] 2.5 Implement authorization check (user must be Face owner)

### Task 3: Create Bio/Location Service (AC: #1, #3, #5, #6)

- [x] 3.1 Create `app/Services/BioLocationService.php`
- [x] 3.2 Implement `updateBioLocation(Face $face, array $data): Face`:
  - Update bio, ville, quartier, pays fields
  - Return updated Face model
- [x] 3.3 Handle nullable fields (allow clearing bio/location)

### Task 4: Create Bio/Location Controller (AC: #1, #3, #5, #6, #7)

- [x] 4.1 Create `app/Http/Controllers/Api/V1/Face/BioLocationController.php`
- [x] 4.2 Implement `show(): JsonResponse` - get current bio and location
- [x] 4.3 Implement `update(UpdateBioLocationRequest $request): JsonResponse`:
  - Use BioLocationService for business logic
  - Return standard API envelope with updated data
- [x] 4.4 Use private `getAuthenticatedFace()` method pattern from ActingVideoController
- [x] 4.5 Use API envelope format for all responses

### Task 5: Update FaceResource API Resource (AC: #7)

- [x] 5.1 Update `app/Http/Resources/FaceResource.php` to include:
  - `bio`
  - `ville`
  - `quartier`
  - `pays`
  - `formatted_location`

### Task 6: Add API Routes (AC: #1, #3)

- [x] 6.1 Add routes to `routes/api/face.php`:
  - `GET /api/v1/face/bio-location` - get current bio and location
  - `PUT /api/v1/face/bio-location` - update bio and location
- [x] 6.2 Apply `auth:sanctum` middleware
- [x] 6.3 Apply rate limiting: `throttle:60,1`

### Task 7: Backend Tests (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 7.1 Create `tests/Feature/Face/BioLocationTest.php`
- [x] 7.2 Test successful bio update
- [x] 7.3 Test successful location update (all fields)
- [x] 7.4 Test successful bio and location update together
- [x] 7.5 Test rejection of bio > 500 characters
- [x] 7.6 Test rejection of quartier without ville
- [x] 7.7 Test clearing bio (setting to null/empty)
- [x] 7.8 Test clearing location fields
- [x] 7.9 Test get bio/location info endpoint
- [x] 7.10 Test unauthorized access (non-owner)
- [x] 7.11 Test formatted_location accessor

### Task 8: Update Frontend Types (AC: #1, #3)

- [x] 8.1 Add to `frontend/src/features/face/types.ts`:
  ```typescript
  interface BioLocationInfo {
    bio: string | null;
    ville: string | null;
    quartier: string | null;
    pays: string | null;
    formatted_location: string | null;
  }

  interface BioLocationResponse {
    data: BioLocationInfo;
    message?: string;
  }

  interface BioLocationResult {
    success: boolean;
    data?: BioLocationInfo;
    errors?: Record<string, string[]>;
    message?: string;
  }
  ```

### Task 9: Create Bio/Location API Service (AC: #1, #3)

- [x] 9.1 Add to `frontend/src/features/face/services/faceApi.ts`:
  ```typescript
  getBioLocation(): Promise<BioLocationResponse>
  updateBioLocation(data: {
    bio?: string | null;
    ville?: string | null;
    quartier?: string | null;
    pays?: string | null;
  }): Promise<BioLocationResponse>
  ```

### Task 10: Create useBioLocation Composable (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 10.1 Create `frontend/src/features/face/composables/useBioLocation.ts`
- [x] 10.2 Implement reactive state:
  - `bioLocationInfo`, `isLoading`, `isSaving`, `error`
- [x] 10.3 Implement methods:
  - `fetchBioLocation()`, `updateBioLocation()`, `clearError()`
- [x] 10.4 Implement client-side validation:
  - Bio max 500 characters
  - Quartier requires ville
- [x] 10.5 Implement character counter helper: `bioCharactersRemaining`

### Task 11: Create BioLocationForm Component (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 11.1 Create `frontend/src/features/face/components/BioLocationForm.vue`
- [x] 11.2 Implement form with:
  - Textarea for bio with character counter (500 max)
  - Input for ville
  - Input for quartier
  - Dropdown/input for pays (default "Bénin")
  - Save button
  - Loading states
  - Error display
- [x] 11.3 Props: `bioLocationInfo`, `isSaving`, `error`
- [x] 11.4 Emits: `save`
- [x] 11.5 Show character count: "X / 500 caractères"
- [x] 11.6 Visual feedback at limit (red counter at exactly 500, enforced via HTML maxlength)

### Task 12: Integrate Bio/Location into ProfileEditPage (AC: #1, #3, #5, #7)

- [x] 12.1 Update `frontend/src/pages/face/ProfileEditPage.vue`
- [x] 12.2 Add "Bio et Localisation" section AFTER "Vidéo d'Acting" section
- [x] 12.3 Import and use `useBioLocation` composable
- [x] 12.4 Import and use `BioLocationForm` component
- [x] 12.5 Handle save event with toast notifications
- [x] 12.6 Fetch bio/location info on mount

### Task 13: Frontend Tests (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 13.1 Create `frontend/src/features/face/composables/__tests__/useBioLocation.spec.ts`
- [x] 13.2 Test initial state
- [x] 13.3 Test fetch bio/location
- [x] 13.4 Test update bio only
- [x] 13.5 Test update location only
- [x] 13.6 Test update both bio and location
- [x] 13.7 Test bio validation (max 500 chars)
- [x] 13.8 Test quartier without ville validation
- [x] 13.9 Test clear bio
- [x] 13.10 Test error handling

- [x] 13.11 Create `frontend/src/features/face/components/__tests__/BioLocationForm.spec.ts`
- [x] 13.12 Test form rendering with initial values
- [x] 13.13 Test character counter display
- [x] 13.14 Test character limit visual feedback
- [x] 13.15 Test save button disabled when saving
- [x] 13.16 Test error message display
- [x] 13.17 Test save event emission with form data

## Dev Notes

### Database Schema

```
faces table:
├── (existing columns)
├── bio (text, nullable)
├── ville (string, 100, nullable)
├── quartier (string, 100, nullable)
└── pays (string, 100, nullable, default 'Bénin')
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/face/bio-location` | Get current bio and location |
| PUT | `/api/v1/face/bio-location` | Update bio and location |

### Error Messages (French)

| Scenario | Message |
|----------|---------|
| Bio too long | "La bio ne peut pas dépasser 500 caractères" |
| Ville too long | "La ville ne peut pas dépasser 100 caractères" |
| Quartier without ville | "La ville est requise pour enregistrer le quartier" |
| Update failed | "Échec de la mise à jour du profil" |
| Update success | "Profil mis à jour avec succès" |

### Character Counter Logic

```typescript
// Composable provides remaining character count
const bioCharactersRemaining = computed(() => {
  const current = bioLocationInfo.value?.bio?.length ?? 0
  return 500 - current
})

// Component handles display with red at exactly 500 (enforced via HTML maxlength="500")
const isAtLimit = computed(() => bioCharactersRemaining.value === 0)
```

### Country List (MVP)

For MVP, pays is a simple text input with default "Bénin". Common values for the West African region:
- Bénin
- Togo
- Côte d'Ivoire
- Ghana
- Nigeria
- Burkina Faso
- Niger
- Sénégal

### Project Structure Notes

**Backend files to create:**
```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/Face/
│   │   │   └── BioLocationController.php
│   │   └── Requests/Face/
│   │       └── UpdateBioLocationRequest.php
│   └── Services/
│       └── BioLocationService.php
├── database/migrations/
│   └── xxxx_add_bio_and_location_to_faces.php
└── tests/Feature/Face/
    └── BioLocationTest.php
```

**Frontend files to create:**
```
frontend/src/
├── features/face/
│   ├── components/
│   │   ├── BioLocationForm.vue
│   │   └── __tests__/
│   │       └── BioLocationForm.spec.ts
│   ├── composables/
│   │   ├── useBioLocation.ts
│   │   └── __tests__/
│   │       └── useBioLocation.spec.ts
│   └── types.ts (update existing)
│   └── services/faceApi.ts (update existing)
└── pages/face/
    └── ProfileEditPage.vue (update existing)
```

### Learnings from Previous Stories (MUST FOLLOW)

1. **Authorization Pattern**: Use `getAuthenticatedFace()` private method pattern from ActingVideoController
2. **Form Request**: Always use Form Request for validation, never validate in controller
3. **API Envelope**: Follow standard `{data, message}` envelope format
4. **French Messages**: All user-facing messages must be in French
5. **Accessibility**: Add proper labels, aria attributes, and focus states
6. **Toast Notifications**: Use toast for success feedback
7. **Test Coverage**: Aim for comprehensive test coverage (composable + component)

### UI Guidelines

- **Use Gemini MCP** for all frontend UI component generation (snippet_frontend, create_frontend, modify_frontend)
- **Use shadcn/ui components** when available (Input, Textarea, Button, Label, etc.)
- Bio textarea should be visually distinct with proper sizing
- Character counter should be visible below textarea
- Character counter turns red when near/over limit
- Location fields should be in a logical order: Ville, Quartier, Pays
- Pays field defaults to "Bénin" but allows editing
- Single save button for the entire form
- Loading spinner on button during save
- Follow existing project design tokens and Tailwind classes from globals.css

### Test Count Expectations

Based on previous story patterns:
- Backend: ~11-15 tests (BioLocationTest.php)
- Frontend Composable: ~10-12 tests (useBioLocation.spec.ts)
- Frontend Component: ~7-10 tests (BioLocationForm.spec.ts)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.5 - FR13, FR14]
- [Source: _bmad-output/project-context.md#Technology Stack]
- [Source: _bmad-output/implementation-artifacts/3-4-face-acting-video-upload.md - Authorization pattern reference]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- Fixed BioLocationService: Changed from null coalescing (`??`) to `array_key_exists` to properly handle explicit null values for clearing fields
- Fixed test `formatted_location_with_only_ville`: Added explicit clearing of pays before test
- Fixed test `default_pays_is_benin`: Added `refresh()` call to get DB default value

### Completion Notes List

- All 13 tasks completed
- Backend tests: 19 tests passing in BioLocationTest.php
- Frontend tests: 22 tests in useBioLocation.spec.ts, 16 tests in BioLocationForm.spec.ts
- Full test suite: 137 backend tests - all passing
- Used Gemini MCP (snippet_frontend) for BioLocationForm component generation
- Followed existing patterns from ActingVideoController and useActingVideo
- Post-completion: Added HTML maxlength enforcement and simplified character counter logic
- Code review: Cleaned up unused composable exports (isNearLimit, isOverLimit)

### File List

**Backend files created/modified:**
- `database/migrations/2026_01_12_142321_add_bio_and_location_to_faces.php` (created)
- `app/Models/Face.php` (modified - added fillable, appends, formattedLocation accessor)
- `app/Http/Requests/Face/UpdateBioLocationRequest.php` (created)
- `app/Services/BioLocationService.php` (created)
- `app/Http/Controllers/Api/V1/Face/BioLocationController.php` (created)
- `app/Http/Resources/FaceResource.php` (modified)
- `routes/api/face.php` (modified)
- `tests/Feature/Face/BioLocationTest.php` (created)

**Frontend files created/modified:**
- `frontend/src/features/face/types.ts` (modified)
- `frontend/src/features/face/services/faceApi.ts` (modified)
- `frontend/src/features/face/composables/useBioLocation.ts` (created)
- `frontend/src/features/face/components/BioLocationForm.vue` (created)
- `frontend/src/pages/face/ProfileEditPage.vue` (modified)
- `frontend/src/features/face/composables/__tests__/useBioLocation.spec.ts` (created)
- `frontend/src/features/face/components/__tests__/BioLocationForm.spec.ts` (created)

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-01-12 | Story 3.5 created - Face Bio and Location | Claude Opus 4.5 |
| 2026-01-12 | Story 3.5 completed - All 13 tasks implemented | Claude Opus 4.5 |
| 2026-01-12 | Post-completion fix: Added maxlength="500" to bio textarea | Claude Opus 4.5 |
| 2026-01-12 | Post-completion fix: Changed counter to red only at exactly 500 (removed amber warning) | Claude Opus 4.5 |
| 2026-01-12 | Post-completion fix: Added px-3 py-2 padding to form inputs | Claude Opus 4.5 |
| 2026-01-12 | Code review cleanup: Removed unused isNearLimit/isOverLimit from composable | Claude Opus 4.5 |
