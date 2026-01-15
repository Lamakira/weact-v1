# Story 3.6: Face Physical Characteristics

Status: done

## Story

As a **Face**,
I want **to enter my physical characteristics (height, weight)**,
So that **producers can filter and find talents matching their requirements**.

## Acceptance Criteria

1. **Given** I am on my profile edit page, **When** I enter my height (in cm) and weight (in kg), **Then** the values are saved and displayed on my profile

2. **Given** I enter invalid values (negative numbers), **When** I try to save, **Then** I see validation error "La valeur doit être positive"

3. **Given** I enter unrealistic height values (< 50cm or > 300cm), **When** I try to save, **Then** I see validation error "La taille doit être entre 50 et 300 cm"

4. **Given** I enter unrealistic weight values (< 20kg or > 500kg), **When** I try to save, **Then** I see validation error "Le poids doit être entre 20 et 500 kg"

5. **Given** I save my physical characteristics, **When** the save is successful, **Then** I see a success toast notification

6. **Given** I clear my physical characteristics, **When** I save, **Then** the values are cleared from my profile

7. **Given** I am viewing my profile, **When** the page loads, **Then** I see my physical characteristics displayed (if previously saved)

**(FR15)**

## Tasks / Subtasks

### Task 1: Update Face Model and Database Schema (AC: #1, #6, #7)

- [x] 1.1 Create migration `add_physical_characteristics_to_faces`:
  - `taille` (integer, nullable) - height in cm
  - `poids` (integer, nullable) - weight in kg
- [x] 1.2 Update Face model:
  - Add to fillable: `taille`, `poids`
- [x] 1.3 Run migration and verify schema

### Task 2: Create Physical Characteristics Form Request (AC: #1, #2, #3, #4)

- [x] 2.1 Create `app/Http/Requests/Face/UpdatePhysicalCharacteristicsRequest.php`
- [x] 2.2 Define validation rules:
  ```php
  return [
      'taille' => ['nullable', 'integer', 'min:50', 'max:300'],
      'poids' => ['nullable', 'integer', 'min:20', 'max:500'],
  ];
  ```
- [x] 2.3 Implement French error messages:
  - `taille.integer` → "La taille doit être un nombre entier"
  - `taille.min` → "La taille doit être entre 50 et 300 cm"
  - `taille.max` → "La taille doit être entre 50 et 300 cm"
  - `poids.integer` → "Le poids doit être un nombre entier"
  - `poids.min` → "Le poids doit être entre 20 et 500 kg"
  - `poids.max` → "Le poids doit être entre 20 et 500 kg"
- [x] 2.4 Implement authorization check (user must be Face owner)

### Task 3: Create Physical Characteristics Service (AC: #1, #5, #6)

- [x] 3.1 Create `app/Services/PhysicalCharacteristicsService.php`
- [x] 3.2 Implement `updatePhysicalCharacteristics(Face $face, array $data): Face`:
  - Update taille, poids fields
  - Return updated Face model
- [x] 3.3 Handle nullable fields (allow clearing values)

### Task 4: Create Physical Characteristics Controller (AC: #1, #5, #6, #7)

- [x] 4.1 Create `app/Http/Controllers/Api/V1/Face/PhysicalCharacteristicsController.php`
- [x] 4.2 Implement `show(): JsonResponse` - get current physical characteristics
- [x] 4.3 Implement `update(UpdatePhysicalCharacteristicsRequest $request): JsonResponse`:
  - Use PhysicalCharacteristicsService for business logic
  - Return standard API envelope with updated data
- [x] 4.4 Use private `getAuthenticatedFace()` method pattern from BioLocationController
- [x] 4.5 Use API envelope format for all responses

### Task 5: Update FaceResource API Resource (AC: #7)

- [x] 5.1 Update `app/Http/Resources/FaceResource.php` to include:
  - `taille`
  - `poids`

### Task 6: Add API Routes (AC: #1)

- [x] 6.1 Add routes to `routes/api/face.php`:
  - `GET /api/v1/face/physical-characteristics` - get current physical characteristics
  - `PUT /api/v1/face/physical-characteristics` - update physical characteristics
- [x] 6.2 Apply `auth:sanctum` middleware
- [x] 6.3 Apply rate limiting: `throttle:60,1`

### Task 7: Backend Tests (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 7.1 Create `tests/Feature/Face/PhysicalCharacteristicsTest.php`
- [x] 7.2 Test successful taille update
- [x] 7.3 Test successful poids update
- [x] 7.4 Test successful taille and poids update together
- [x] 7.5 Test rejection of negative taille
- [x] 7.6 Test rejection of negative poids
- [x] 7.7 Test rejection of unrealistic taille (< 50 or > 300)
- [x] 7.8 Test rejection of unrealistic poids (< 20 or > 500)
- [x] 7.9 Test clearing taille (setting to null)
- [x] 7.10 Test clearing poids (setting to null)
- [x] 7.11 Test get physical characteristics endpoint
- [x] 7.12 Test unauthorized access (non-owner)

### Task 8: Update Frontend Types (AC: #1)

- [x] 8.1 Add to `frontend/src/features/face/types.ts`:
  ```typescript
  interface PhysicalCharacteristicsInfo {
    taille: number | null;
    poids: number | null;
  }

  interface PhysicalCharacteristicsResponse {
    data: PhysicalCharacteristicsInfo;
    message?: string;
  }

  interface PhysicalCharacteristicsResult {
    success: boolean;
    data?: PhysicalCharacteristicsInfo;
    errors?: Record<string, string[]>;
    message?: string;
  }
  ```

### Task 9: Create Physical Characteristics API Service (AC: #1)

- [x] 9.1 Add to `frontend/src/features/face/services/faceApi.ts`:
  ```typescript
  getPhysicalCharacteristics(): Promise<PhysicalCharacteristicsResponse>
  updatePhysicalCharacteristics(data: {
    taille?: number | null;
    poids?: number | null;
  }): Promise<PhysicalCharacteristicsResponse>
  ```

### Task 10: Create usePhysicalCharacteristics Composable (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 10.1 Create `frontend/src/features/face/composables/usePhysicalCharacteristics.ts`
- [x] 10.2 Implement reactive state:
  - `physicalCharacteristicsInfo`, `isLoading`, `isSaving`, `error`
- [x] 10.3 Implement methods:
  - `fetchPhysicalCharacteristics()`, `updatePhysicalCharacteristics()`, `clearError()`
- [x] 10.4 Implement client-side validation:
  - Taille: min 50, max 300
  - Poids: min 20, max 500
- [x] 10.5 Implement validation helpers: `validateTaille()`, `validatePoids()`

### Task 11: Create PhysicalCharacteristicsForm Component (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 11.1 Create `frontend/src/features/face/components/PhysicalCharacteristicsForm.vue`
- [x] 11.2 Implement form with:
  - Number input for taille (cm) with min/max attributes
  - Number input for poids (kg) with min/max attributes
  - Save button
  - Loading states
  - Error display
- [x] 11.3 Props: `physicalCharacteristicsInfo`, `isSaving`, `error`
- [x] 11.4 Emits: `save`
- [x] 11.5 Show units: "cm" for taille, "kg" for poids
- [x] 11.6 Use HTML min/max attributes for basic validation (min="50" max="300" for taille, min="20" max="500" for poids)

### Task 12: Integrate Physical Characteristics into ProfileEditPage (AC: #1, #5, #7)

- [x] 12.1 Update `frontend/src/pages/face/ProfileEditPage.vue`
- [x] 12.2 Add "Caractéristiques physiques" section AFTER "Bio et Localisation" section
- [x] 12.3 Import and use `usePhysicalCharacteristics` composable
- [x] 12.4 Import and use `PhysicalCharacteristicsForm` component
- [x] 12.5 Handle save event with toast notifications
- [x] 12.6 Fetch physical characteristics on mount

### Task 13: Frontend Tests (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 13.1 Create `frontend/src/features/face/composables/__tests__/usePhysicalCharacteristics.spec.ts`
- [x] 13.2 Test initial state
- [x] 13.3 Test fetch physical characteristics
- [x] 13.4 Test update taille only
- [x] 13.5 Test update poids only
- [x] 13.6 Test update both taille and poids
- [x] 13.7 Test taille validation (min 50, max 300)
- [x] 13.8 Test poids validation (min 20, max 500)
- [x] 13.9 Test clear taille
- [x] 13.10 Test clear poids
- [x] 13.11 Test error handling

- [x] 13.12 Create `frontend/src/features/face/components/__tests__/PhysicalCharacteristicsForm.spec.ts`
- [x] 13.13 Test form rendering with initial values
- [x] 13.14 Test form rendering with null values
- [x] 13.15 Test save button disabled when saving
- [x] 13.16 Test error message display
- [x] 13.17 Test save event emission with form data
- [x] 13.18 Test save event emission with null for empty values
- [x] 13.19 Test HTML min/max attributes on inputs

## Dev Notes

### Database Schema

```
faces table:
├── (existing columns)
├── taille (integer, nullable) - height in cm
└── poids (integer, nullable) - weight in kg
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/face/physical-characteristics` | Get current physical characteristics |
| PUT | `/api/v1/face/physical-characteristics` | Update physical characteristics |

### Error Messages (French)

| Scenario | Message |
|----------|---------|
| Taille not integer | "La taille doit être un nombre entier" |
| Taille too small (< 50) | "La taille doit être entre 50 et 300 cm" |
| Taille too large (> 300) | "La taille doit être entre 50 et 300 cm" |
| Poids not integer | "Le poids doit être un nombre entier" |
| Poids too small (< 20) | "Le poids doit être entre 20 et 500 kg" |
| Poids too large (> 500) | "Le poids doit être entre 20 et 500 kg" |
| Update failed | "Échec de la mise à jour du profil" |
| Update success | "Profil mis à jour avec succès" |

### Validation Ranges

| Field | Min | Max | Unit |
|-------|-----|-----|------|
| taille | 50 | 300 | cm |
| poids | 20 | 500 | kg |

### Project Structure Notes

**Backend files to create:**
```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/Face/
│   │   │   └── PhysicalCharacteristicsController.php
│   │   └── Requests/Face/
│   │       └── UpdatePhysicalCharacteristicsRequest.php
│   └── Services/
│       └── PhysicalCharacteristicsService.php
├── database/migrations/
│   └── xxxx_add_physical_characteristics_to_faces.php
└── tests/Feature/Face/
    └── PhysicalCharacteristicsTest.php
```

**Frontend files to create:**
```
frontend/src/
├── features/face/
│   ├── components/
│   │   ├── PhysicalCharacteristicsForm.vue
│   │   └── __tests__/
│   │       └── PhysicalCharacteristicsForm.spec.ts
│   ├── composables/
│   │   ├── usePhysicalCharacteristics.ts
│   │   └── __tests__/
│   │       └── usePhysicalCharacteristics.spec.ts
│   └── types.ts (update existing)
│   └── services/faceApi.ts (update existing)
└── pages/face/
    └── ProfileEditPage.vue (update existing)
```

### Learnings from Previous Stories (MUST FOLLOW)

1. **Authorization Pattern**: Use `getAuthenticatedFace()` private method pattern from BioLocationController
2. **Form Request**: Always use Form Request for validation, never validate in controller
3. **API Envelope**: Follow standard `{data, message}` envelope format
4. **French Messages**: All user-facing messages must be in French
5. **Accessibility**: Add proper labels, aria attributes, and focus states
6. **Toast Notifications**: Use toast for success feedback
7. **Test Coverage**: Aim for comprehensive test coverage (composable + component)
8. **Service Pattern**: Use `array_key_exists()` for nullable field updates (not null coalescing `??`)
9. **HTML Validation**: Use HTML attributes (min, max) as first line of defense

### UI Guidelines

- **Use Gemini MCP** for all frontend UI component generation (snippet_frontend, create_frontend, modify_frontend)
- **Use shadcn/ui components** when available (Input, Label, Button, etc.)
- Number inputs should have proper step="1" for integer values
- Display units (cm, kg) adjacent to inputs or as placeholders
- Input fields should have proper padding (px-3 py-2) like BioLocationForm
- Single save button for the entire form
- Loading spinner on button during save
- Follow existing project design tokens and Tailwind classes from globals.css

### Test Count Expectations

Based on previous story patterns:
- Backend: ~12-15 tests (PhysicalCharacteristicsTest.php)
- Frontend Composable: ~11-14 tests (usePhysicalCharacteristics.spec.ts)
- Frontend Component: ~7-10 tests (PhysicalCharacteristicsForm.spec.ts)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.6 - FR15]
- [Source: _bmad-output/project-context.md#Technology Stack]
- [Source: _bmad-output/implementation-artifacts/3-5-face-bio-and-location.md - Pattern reference]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5

### Debug Log References

N/A - No debug issues encountered

### Completion Notes List

- All 13 tasks completed successfully
- Backend: 23 tests passing (PhysicalCharacteristicsTest.php)
- Full backend test suite: 160 tests passing
- Frontend composable: 32 tests passing (usePhysicalCharacteristics.spec.ts)
- Frontend component: 16 tests passing (PhysicalCharacteristicsForm.spec.ts)
- Full frontend test suite: 319 tests passing

### Code Review Fixes Applied

**Issue 1 - AC #2 Compliance (Negative Number Validation):**
- Added closure validation rule in `UpdatePhysicalCharacteristicsRequest.php` to return "La valeur doit être positive" for negative values
- Added `bail` rule to stop validation chain on first error
- Updated frontend `usePhysicalCharacteristics.ts` to match backend validation

**Issue 2 - Added Missing Tests:**
- Backend: Added `test_rejects_negative_taille()` and `test_rejects_negative_poids()`
- Frontend: Added 4 new tests for negative value validation in composable

### File List

**Backend files created:**
- `backend/database/migrations/2026_01_12_220856_add_physical_characteristics_to_faces.php`
- `backend/app/Http/Requests/Face/UpdatePhysicalCharacteristicsRequest.php`
- `backend/app/Services/PhysicalCharacteristicsService.php`
- `backend/app/Http/Controllers/Api/V1/Face/PhysicalCharacteristicsController.php`
- `backend/tests/Feature/Face/PhysicalCharacteristicsTest.php`

**Backend files modified:**
- `backend/app/Models/Face.php` - added taille, poids to fillable
- `backend/app/Http/Resources/FaceResource.php` - added taille, poids
- `backend/routes/api/face.php` - added physical-characteristics routes

**Frontend files created:**
- `frontend/src/features/face/composables/usePhysicalCharacteristics.ts`
- `frontend/src/features/face/composables/__tests__/usePhysicalCharacteristics.spec.ts`
- `frontend/src/features/face/components/PhysicalCharacteristicsForm.vue`
- `frontend/src/features/face/components/__tests__/PhysicalCharacteristicsForm.spec.ts`

**Frontend files modified:**
- `frontend/src/features/face/types.ts` - added PhysicalCharacteristicsInfo, PhysicalCharacteristicsResponse, PhysicalCharacteristicsResult
- `frontend/src/features/face/services/faceApi.ts` - added getPhysicalCharacteristics, updatePhysicalCharacteristics
- `frontend/src/pages/face/ProfileEditPage.vue` - integrated PhysicalCharacteristicsForm component

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-01-12 | Story 3.6 created - Face Physical Characteristics | Claude Opus 4.5 |
| 2026-01-12 | Story 3.6 implemented - All tasks completed | Claude Opus 4.5 |
| 2026-01-12 | Code review fixes - AC #2 negative validation, added tests | Claude Opus 4.5 |
