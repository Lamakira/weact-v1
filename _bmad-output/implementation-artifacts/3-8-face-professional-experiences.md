# Story 3.8: Face Professional Experiences

Status: done

## Story

As a **Face**,
I want **to list my professional experiences**,
So that **producers can see my track record**.

## Acceptance Criteria

1. **Given** I am on my profile edit page, **When** I add an experience (title, description, year), **Then** the experience is added to my list

2. **Given** I have multiple experiences, **When** I view my profile, **Then** experiences are displayed in chronological order (newest first)

3. **Given** I want to edit an experience, **When** I use the edit action, **Then** the experience is updated

4. **Given** I want to delete an experience, **When** I use the delete action, **Then** the experience is removed

5. **Given** I view my profile edit page, **When** the page loads, **Then** I see all my existing experiences

6. **Given** I add, edit, or delete an experience, **When** the action completes, **Then** I see a success toast notification

7. **Given** I submit an experience with invalid data, **When** the form is validated, **Then** I see appropriate French error messages

**(FR18)**

## Tasks / Subtasks

### Task 1: Create Experiences Database Schema (AC: #1, #5)

- [x] 1.1 Create migration `create_experiences_table`:
  - `id` (bigIncrements)
  - `face_id` (foreignId, constrained to faces, cascadeOnDelete)
  - `titre` (string, max 150)
  - `description` (text, nullable, max 500 characters)
  - `annee` (unsignedSmallInteger) - year of experience
  - `timestamps`
- [x] 1.2 Add index on `face_id` for query performance
- [x] 1.3 Add index on `annee` for ordering by year
- [x] 1.4 Run migration and verify schema

### Task 2: Create Experience Model (AC: #1, #2, #3, #4)

- [x] 2.1 Create `app/Models/Experience.php`
- [x] 2.2 Define fillable: `face_id`, `titre`, `description`, `annee`
- [x] 2.3 Define relationship: `face()` belongsTo Face
- [x] 2.4 Add scope `orderedByYear()` for chronological ordering (newest first)
- [x] 2.5 Update Face model: add `experiences()` hasMany relationship

### Task 3: Create Experience Form Requests (AC: #1, #3, #7)

- [x] 3.1 Create `app/Http/Requests/Face/StoreExperienceRequest.php`
- [x] 3.2 Create `app/Http/Requests/Face/UpdateExperienceRequest.php`
- [x] 3.3 Define validation rules:
  ```php
  return [
      'titre' => ['required', 'string', 'max:150'],
      'description' => ['nullable', 'string', 'max:500'],
      'annee' => ['required', 'integer', 'min:1950', 'max:' . date('Y')],
  ];
  ```
- [x] 3.4 Implement French error messages:
  - `titre.required` → "Le titre est requis"
  - `titre.max` → "Le titre ne doit pas dépasser 150 caractères"
  - `description.max` → "La description ne doit pas dépasser 500 caractères"
  - `annee.required` → "L'année est requise"
  - `annee.min` → "L'année doit être supérieure ou égale à 1950"
  - `annee.max` → "L'année ne peut pas être dans le futur"
- [x] 3.5 Implement authorization check (user must be Face owner)

### Task 4: Create Experience Service (AC: #1, #2, #3, #4)

- [x] 4.1 Create `app/Services/ExperienceService.php`
- [x] 4.2 Implement `getExperiences(Face $face): Collection`:
  - Return experiences ordered by year descending (newest first)
- [x] 4.3 Implement `createExperience(Face $face, array $data): Experience`:
  - Create new experience associated with face
  - Return created experience
- [x] 4.4 Implement `updateExperience(Experience $experience, array $data): Experience`:
  - Update experience fields
  - Return updated experience
- [x] 4.5 Implement `deleteExperience(Experience $experience): bool`:
  - Delete the experience
  - Return success status

### Task 5: Create Experience API Resource (AC: #2, #5)

- [x] 5.1 Create `app/Http/Resources/ExperienceResource.php`
- [x] 5.2 Include fields:
  - `id`
  - `titre`
  - `description`
  - `annee`
  - `created_at` (formatted)
  - `updated_at` (formatted)

### Task 6: Create Experience Controller (AC: #1, #2, #3, #4, #5, #6)

- [x] 6.1 Create `app/Http/Controllers/Api/V1/Face/ExperienceController.php`
- [x] 6.2 Implement `index(): JsonResponse` - list all experiences for authenticated Face
- [x] 6.3 Implement `store(StoreExperienceRequest $request): JsonResponse`:
  - Use ExperienceService for business logic
  - Return created experience with 201 status
- [x] 6.4 Implement `show(Experience $experience): JsonResponse`:
  - Verify ownership
  - Return single experience
- [x] 6.5 Implement `update(UpdateExperienceRequest $request, Experience $experience): JsonResponse`:
  - Verify ownership
  - Use ExperienceService for business logic
  - Return updated experience
- [x] 6.6 Implement `destroy(Experience $experience): JsonResponse`:
  - Verify ownership
  - Use ExperienceService for business logic
  - Return success message with 200 status
- [x] 6.7 Use private `getAuthenticatedFace()` method pattern from previous controllers
- [x] 6.8 Implement `verifyOwnership(Experience $experience)` for authorization
- [x] 6.9 Use API envelope format for all responses

### Task 7: Add Experience API Routes (AC: #1)

- [x] 7.1 Add RESTful routes to `routes/api/face.php`:
  - `GET /api/v1/face/experiences` - list experiences
  - `POST /api/v1/face/experiences` - create experience
  - `GET /api/v1/face/experiences/{experience}` - show single experience
  - `PUT /api/v1/face/experiences/{experience}` - update experience
  - `DELETE /api/v1/face/experiences/{experience}` - delete experience
- [x] 7.2 Apply `auth:sanctum` middleware
- [x] 7.3 Apply rate limiting: `throttle:60,1`

### Task 8: Update FaceResource (AC: #2)

- [x] 8.1 Update `app/Http/Resources/FaceResource.php` to conditionally include:
  - `experiences` (collection of ExperienceResource, when loaded)
  - `experiences_count` (integer)

### Task 9: Backend Tests (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 9.1 Create `tests/Feature/Face/ExperienceTest.php`
- [x] 9.2 Test list experiences - returns ordered by year descending
- [x] 9.3 Test create experience with valid data
- [x] 9.4 Test create experience without required title - fails
- [x] 9.5 Test create experience with title exceeding max length - fails
- [x] 9.6 Test create experience without required year - fails
- [x] 9.7 Test create experience with year before 1950 - fails
- [x] 9.8 Test create experience with future year - fails
- [x] 9.9 Test create experience with optional description
- [x] 9.10 Test create experience with description exceeding max length - fails
- [x] 9.11 Test show single experience
- [x] 9.12 Test show experience owned by another Face - fails (403)
- [x] 9.13 Test update experience with valid data
- [x] 9.14 Test update experience owned by another Face - fails (403)
- [x] 9.15 Test delete experience
- [x] 9.16 Test delete experience owned by another Face - fails (403)
- [x] 9.17 Test unauthenticated access - fails (401)
- [x] 9.18 Test experience factory and seeder

### Task 10: Create Frontend Types (AC: #1, #2, #3, #4)

- [x] 10.1 Add to `frontend/src/features/face/types.ts`:
  ```typescript
  interface Experience {
    id: number
    titre: string
    description: string | null
    annee: number
    created_at: string
    updated_at: string
  }

  interface ExperienceFormData {
    titre: string
    description?: string | null
    annee: number
  }

  interface ExperienceResponse {
    data: Experience
    message?: string
  }

  interface ExperiencesListResponse {
    data: Experience[]
    message?: string
  }

  interface ExperienceResult {
    success: boolean
    data?: Experience
    errors?: Record<string, string[]>
    message?: string
  }
  ```

### Task 11: Create Experience API Service (AC: #1, #2, #3, #4)

- [x] 11.1 Add to `frontend/src/features/face/services/faceApi.ts`:
  ```typescript
  getExperiences(): Promise<ExperiencesListResponse>
  createExperience(data: ExperienceFormData): Promise<ExperienceResponse>
  getExperience(id: number): Promise<ExperienceResponse>
  updateExperience(id: number, data: ExperienceFormData): Promise<ExperienceResponse>
  deleteExperience(id: number): Promise<{ message: string }>
  ```

### Task 12: Create useExperiences Composable (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 12.1 Create `frontend/src/features/face/composables/useExperiences.ts`
- [x] 12.2 Implement reactive state:
  - `experiences` (Experience[])
  - `isLoading`, `isSaving`, `isDeleting`
  - `error`, `validationErrors`
- [x] 12.3 Implement methods:
  - `fetchExperiences()` - load all experiences
  - `addExperience(data: ExperienceFormData)` - create new
  - `editExperience(id: number, data: ExperienceFormData)` - update existing
  - `removeExperience(id: number)` - delete
  - `clearError()`
- [x] 12.4 Handle API errors and validation errors appropriately
- [x] 12.5 Return experiences sorted by year (newest first)

### Task 13: Create ExperienceCard Component (AC: #2, #3, #4)

- [x] 13.1 Create `frontend/src/features/face/components/ExperienceCard.vue`
- [x] 13.2 Display experience info: titre, description, annee
- [x] 13.3 Props: `experience`, `isDeleting`
- [x] 13.4 Emits: `edit`, `delete`
- [x] 13.5 Edit button (pencil icon) triggers edit mode
- [x] 13.6 Delete button (trash icon) with confirmation
- [x] 13.7 Apply Tailwind styling with project design tokens

### Task 14: Create ExperienceForm Component (AC: #1, #3, #6, #7)

- [x] 14.1 Create `frontend/src/features/face/components/ExperienceForm.vue`
- [x] 14.2 Implement form with:
  - Input for titre (required, max 150)
  - Textarea for description (optional, max 500)
  - Number input for annee (required, 1950-current year)
  - Submit button
  - Cancel button (when editing)
  - Loading states
  - Validation error display
- [x] 14.3 Props: `experience` (optional, for edit mode), `isSaving`, `error`, `validationErrors`
- [x] 14.4 Emits: `submit`, `cancel`
- [x] 14.5 Include French labels and placeholders:
  - Titre: "Titre de l'expérience *"
  - Description: "Description (optionnel)"
  - Année: "Année *"
- [x] 14.6 Reset form after successful submission
- [x] 14.7 Pre-fill form when editing existing experience

### Task 15: Create ExperiencesList Component (AC: #1, #2, #3, #4, #5, #6)

- [x] 15.1 Create `frontend/src/features/face/components/ExperiencesList.vue`
- [x] 15.2 Implement:
  - List of ExperienceCard components
  - "Add experience" button to show form
  - Empty state when no experiences
  - Loading skeleton while fetching
- [x] 15.3 Props: `experiences`, `isLoading`, `isSaving`, `isDeleting`, `error`, `validationErrors`
- [x] 15.4 Emits: `add`, `edit`, `delete`
- [x] 15.5 Handle inline editing (show form in place of card when editing)
- [x] 15.6 Confirmation dialog for delete action

### Task 16: Integrate Experiences into ProfileEditPage (AC: #1, #5, #6)

- [x] 16.1 Update `frontend/src/pages/face/ProfileEditPage.vue`
- [x] 16.2 Add "Expériences professionnelles" section AFTER "Catégorie et Niche" section
- [x] 16.3 Import and use `useExperiences` composable
- [x] 16.4 Import and use `ExperiencesList` component
- [x] 16.5 Handle add, edit, delete events with toast notifications
- [x] 16.6 Fetch experiences on mount

### Task 17: Frontend Composable Tests (AC: #1, #2, #3, #4, #5, #6, #7)

- [x] 17.1 Create `frontend/src/features/face/composables/__tests__/useExperiences.spec.ts`
- [x] 17.2 Test initial state
- [x] 17.3 Test fetchExperiences - success
- [x] 17.4 Test fetchExperiences - error handling
- [x] 17.5 Test addExperience - success
- [x] 17.6 Test addExperience - validation error
- [x] 17.7 Test editExperience - success
- [x] 17.8 Test editExperience - validation error
- [x] 17.9 Test removeExperience - success
- [x] 17.10 Test removeExperience - error
- [x] 17.11 Test experiences are sorted by year (newest first)
- [x] 17.12 Test clearError

### Task 18: Frontend Component Tests (AC: #1, #2, #3, #4, #6, #7)

- [x] 18.1 Create `frontend/src/features/face/components/__tests__/ExperienceCard.spec.ts`
- [x] 18.2 Test rendering with experience data
- [x] 18.3 Test edit button emits event
- [x] 18.4 Test delete button emits event
- [x] 18.5 Test loading state on delete

- [x] 18.6 Create `frontend/src/features/face/components/__tests__/ExperienceForm.spec.ts`
- [x] 18.7 Test form rendering empty (add mode)
- [x] 18.8 Test form rendering with experience (edit mode)
- [x] 18.9 Test form validation (required fields)
- [x] 18.10 Test form submission
- [x] 18.11 Test cancel button
- [x] 18.12 Test error display

- [x] 18.13 Create `frontend/src/features/face/components/__tests__/ExperiencesList.spec.ts`
- [x] 18.14 Test rendering list of experiences
- [x] 18.15 Test empty state
- [x] 18.16 Test loading state
- [x] 18.17 Test add button shows form
- [x] 18.18 Test edit flow
- [x] 18.19 Test delete flow

## Dev Notes

### Database Schema

```
experiences table:
├── id (bigIncrements)
├── face_id (foreignId → faces, cascadeOnDelete)
├── titre (string, max 150)
├── description (text, nullable)
├── annee (unsignedSmallInteger)
├── created_at (timestamp)
└── updated_at (timestamp)

Indexes:
├── experiences_face_id_foreign (face_id)
└── experiences_annee_index (annee)
```

### Model Relationships

```
Face (1) ←→ (N) Experience

Face model:
  - experiences() → hasMany(Experience::class)

Experience model:
  - face() → belongsTo(Face::class)
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/face/experiences` | List all experiences (ordered by year desc) |
| POST | `/api/v1/face/experiences` | Create new experience |
| GET | `/api/v1/face/experiences/{id}` | Get single experience |
| PUT | `/api/v1/face/experiences/{id}` | Update experience |
| DELETE | `/api/v1/face/experiences/{id}` | Delete experience |

### API Response Examples

**List Experiences:**
```json
{
  "data": [
    {
      "id": 3,
      "titre": "Figurant dans série TV",
      "description": "Participation à une série télévisée locale",
      "annee": 2025,
      "created_at": "2026-01-14T10:00:00Z",
      "updated_at": "2026-01-14T10:00:00Z"
    },
    {
      "id": 1,
      "titre": "Publicité Coca-Cola",
      "description": "Rôle principal dans une publicité nationale",
      "annee": 2024,
      "created_at": "2026-01-10T08:00:00Z",
      "updated_at": "2026-01-10T08:00:00Z"
    }
  ],
  "message": "Expériences récupérées avec succès"
}
```

**Create Experience:**
```json
// Request
{
  "titre": "Film court-métrage",
  "description": "Rôle secondaire dans un court-métrage indépendant",
  "annee": 2023
}

// Response (201)
{
  "data": {
    "id": 4,
    "titre": "Film court-métrage",
    "description": "Rôle secondaire dans un court-métrage indépendant",
    "annee": 2023,
    "created_at": "2026-01-14T12:00:00Z",
    "updated_at": "2026-01-14T12:00:00Z"
  },
  "message": "Expérience ajoutée avec succès"
}
```

### Error Messages (French)

| Scenario | Message |
|----------|---------|
| Missing title | "Le titre est requis" |
| Title too long | "Le titre ne doit pas dépasser 150 caractères" |
| Description too long | "La description ne doit pas dépasser 500 caractères" |
| Missing year | "L'année est requise" |
| Year too old | "L'année doit être supérieure ou égale à 1950" |
| Future year | "L'année ne peut pas être dans le futur" |
| Not found | "Expérience non trouvée" |
| Unauthorized | "Vous n'êtes pas autorisé à modifier cette expérience" |
| Create success | "Expérience ajoutée avec succès" |
| Update success | "Expérience mise à jour avec succès" |
| Delete success | "Expérience supprimée avec succès" |
| Fetch error | "Échec du chargement des expériences" |

### Project Structure Notes

**Backend files to create:**
```
backend/
├── app/
│   ├── Models/
│   │   └── Experience.php
│   ├── Http/
│   │   ├── Controllers/Api/V1/Face/
│   │   │   └── ExperienceController.php
│   │   ├── Requests/Face/
│   │   │   ├── StoreExperienceRequest.php
│   │   │   └── UpdateExperienceRequest.php
│   │   └── Resources/
│   │       └── ExperienceResource.php
│   └── Services/
│       └── ExperienceService.php
├── database/
│   ├── migrations/
│   │   └── xxxx_create_experiences_table.php
│   └── factories/
│       └── ExperienceFactory.php
└── tests/Feature/Face/
    └── ExperienceTest.php
```

**Frontend files to create:**
```
frontend/src/
├── features/face/
│   ├── components/
│   │   ├── ExperienceCard.vue
│   │   ├── ExperienceForm.vue
│   │   ├── ExperiencesList.vue
│   │   └── __tests__/
│   │       ├── ExperienceCard.spec.ts
│   │       ├── ExperienceForm.spec.ts
│   │       └── ExperiencesList.spec.ts
│   ├── composables/
│   │   ├── useExperiences.ts
│   │   └── __tests__/
│   │       └── useExperiences.spec.ts
│   ├── types.ts (update existing)
│   └── services/faceApi.ts (update existing)
└── pages/face/
    └── ProfileEditPage.vue (update existing)
```

**Files to modify:**
- `backend/app/Models/Face.php` - Add experiences relationship
- `backend/app/Http/Resources/FaceResource.php` - Add experiences conditionally
- `backend/routes/api/face.php` - Add experience routes
- `frontend/src/features/face/types.ts` - Add Experience types
- `frontend/src/features/face/services/faceApi.ts` - Add API methods
- `frontend/src/pages/face/ProfileEditPage.vue` - Integrate ExperiencesList

### Learnings from Previous Stories (MUST FOLLOW)

1. **Authorization Pattern**: Use `getAuthenticatedFace()` private method pattern from previous controllers (BioLocation, PhysicalCharacteristics, CategoryNiche)
2. **Ownership Verification**: For update/delete operations, verify the experience belongs to the authenticated Face
3. **Form Request**: Always use Form Request for validation, never validate in controller
4. **Service Layer**: All business logic in Service class, controller just orchestrates
5. **API Envelope**: Follow standard `{data, message}` envelope format
6. **French Messages**: All user-facing messages must be in French
7. **Accessibility**: Add proper labels, aria attributes, and focus states
8. **Toast Notifications**: Use toast for success/error feedback
9. **Test Coverage**: Comprehensive tests for composable + components
10. **Rate Limiting**: Apply `throttle:60,1` to all routes
11. **Cascade Delete**: When Face is deleted, experiences should be deleted too
12. **Form novalidate**: Add `novalidate` attribute to forms for custom validation
13. **Loading States**: Show skeletons/spinners during data fetching

### UI Guidelines

- **Use Gemini MCP** for all frontend UI component generation
- **Section Title**: "Expériences professionnelles"
- **Add Button**: "Ajouter une expérience" with + icon
- **Card Layout**: Each experience as a card with title, description, year
- **Year Display**: Show just the year number (e.g., "2024")
- **Edit/Delete Icons**: Pencil and trash icons, visible on hover
- **Empty State**: "Aucune expérience ajoutée" with CTA to add first
- **Confirmation Dialog**: "Êtes-vous sûr de vouloir supprimer cette expérience ?"
- **Form Layout**: Vertical stack with proper spacing
- **Year Input**: Number input with min/max constraints
- **Textarea**: Auto-resize for description
- **Follow existing project design tokens** from globals.css

### Test Count Expectations

Based on previous story patterns:
- Backend: ~18-20 tests (ExperienceTest.php)
- Frontend Composable: ~12-15 tests (useExperiences.spec.ts)
- Frontend Card Component: ~5-6 tests (ExperienceCard.spec.ts)
- Frontend Form Component: ~8-10 tests (ExperienceForm.spec.ts)
- Frontend List Component: ~7-8 tests (ExperiencesList.spec.ts)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 3.8 - FR18]
- [Source: _bmad-output/project-context.md#Technology Stack]
- [Source: _bmad-output/implementation-artifacts/3-7-face-category-and-niche-selection.md - Pattern reference]
- [Source: backend/app/Http/Controllers/Api/V1/Face/CategoryNicheController.php - Controller pattern]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

None

### Completion Notes List

1. **Backend Implementation Complete**:
   - Migration creates `experiences` table with face_id FK, titre, description, annee
   - Experience model with Face relationship and orderedByYear scope
   - Form Requests with French validation messages
   - ExperienceService with CRUD operations
   - ExperienceResource for API response transformation
   - ExperienceController with full CRUD endpoints
   - Routes added with auth:sanctum and throttle middleware
   - FaceResource updated to conditionally include experiences
   - 21 backend tests all passing (197 total tests)

2. **Frontend Implementation Complete**:
   - Experience types added to types.ts
   - API methods added to faceApi.ts
   - useExperiences composable with reactive state and CRUD methods
   - ExperienceCard component for displaying individual experiences
   - ExperienceForm component for add/edit with validation
   - ExperiencesList component with loading, empty state, and inline editing
   - Integrated into ProfileEditPage after Category and Niche section
   - 76 frontend tests all passing

3. **Key Features**:
   - Experiences sorted by year descending (newest first)
   - Inline editing with form replacing card
   - Delete confirmation dialog with Teleport
   - Toast notifications for success/error
   - Loading skeletons while fetching
   - Validation errors displayed per field
   - Cascade delete when Face is deleted

### File List

**Backend Files Created:**
- `backend/database/migrations/2026_01_14_120000_create_experiences_table.php`
- `backend/app/Models/Experience.php`
- `backend/app/Http/Requests/Face/StoreExperienceRequest.php`
- `backend/app/Http/Requests/Face/UpdateExperienceRequest.php`
- `backend/app/Services/ExperienceService.php`
- `backend/app/Http/Resources/ExperienceResource.php`
- `backend/app/Http/Controllers/Api/V1/Face/ExperienceController.php`
- `backend/database/factories/ExperienceFactory.php`
- `backend/tests/Feature/Face/ExperienceTest.php`

**Backend Files Modified:**
- `backend/app/Models/Face.php` - Added experiences() relationship
- `backend/app/Http/Resources/FaceResource.php` - Added experiences conditionally
- `backend/routes/api/face.php` - Added 5 experience routes

**Frontend Files Created:**
- `frontend/src/features/face/composables/useExperiences.ts`
- `frontend/src/features/face/components/ExperienceCard.vue`
- `frontend/src/features/face/components/ExperienceForm.vue`
- `frontend/src/features/face/components/ExperiencesList.vue`
- `frontend/src/features/face/composables/__tests__/useExperiences.spec.ts`
- `frontend/src/features/face/components/__tests__/ExperienceCard.spec.ts`
- `frontend/src/features/face/components/__tests__/ExperienceForm.spec.ts`
- `frontend/src/features/face/components/__tests__/ExperiencesList.spec.ts`

**Frontend Files Modified:**
- `frontend/src/features/face/types.ts` - Added Experience types
- `frontend/src/features/face/services/faceApi.ts` - Added experience API methods
- `frontend/src/pages/face/ProfileEditPage.vue` - Integrated ExperiencesList

## Change Log

| Date       | Change                                          | Author          |
|------------|-------------------------------------------------|-----------------|
| 2026-01-14 | Story 3.8 created - Face Professional Experiences | Claude Opus 4.5 |
| 2026-01-14 | Story 3.8 implementation complete - All 18 tasks done | Claude Opus 4.5 |
| 2026-01-14 | Code review complete - Fixed 1 major + 3 minor issues | Claude Opus 4.5 |
