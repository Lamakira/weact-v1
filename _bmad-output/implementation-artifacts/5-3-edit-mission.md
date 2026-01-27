# Story 5.3: Edit Mission

Status: done

## Story

As a **Producer**,
I want **to modify a mission I have published**,
so that **I can update details or correct mistakes**.

## Acceptance Criteria

1. **Given** I am logged in as a Producer **And** I am viewing my own mission **When** I click "Modifier" **Then** I see the edit form pre-filled with current values

2. **Given** I update any field on the edit form **And** the data is valid **When** I submit the form **Then** the mission is updated **And** I see a success message "Mission modifiée avec succès" **And** I am redirected to the mission detail page

3. **Given** I submit the edit form with invalid data (missing required fields, invalid dates, etc.) **When** validation runs **Then** I see appropriate error messages in French (same validation rules as publish mission)

4. **Given** I submit a date_limite_candidature in the past **When** validation runs **Then** I see an error message "La date limite doit être dans le futur"

5. **Given** I submit a date_tournage before date_limite_candidature **When** validation runs **Then** I see an error message "La date de tournage doit être après la date limite de candidature"

6. **Given** I try to edit a mission that is not mine **When** the API request is made **Then** I receive a 403 Forbidden response with message "Cette action n'est pas autorisée"

7. **Given** I am not logged in **When** I try to access the edit mission endpoint **Then** I receive a 401 Unauthorized response

8. **Given** I try to edit a mission with status "closed" or "completed" **When** validation runs **Then** I see an error message "Une mission clôturée ou terminée ne peut pas être modifiée"

9. **Given** the mission is successfully updated **When** the API responds **Then** the response follows the envelope format: `{ data: MissionResource, message: "Mission modifiée avec succès" }`

**(FR26)**

## Tasks / Subtasks

- [x] Task 1: Create UpdateMissionRequest Form Request (AC: #3, #4, #5, #8)
  - [x] Create `app/Http/Requests/Mission/UpdateMissionRequest.php`
  - [x] Implement `authorize(): bool` - check user is Producer AND owns the mission AND mission is editable (not closed/completed)
  - [x] Implement validation rules (same as StoreMissionRequest)
  - [x] Implement `messages(): array` with French error messages (reuse from StoreMissionRequest)
  - [x] Add custom rule to check mission status is not 'closed' or 'completed'

- [x] Task 2: Extend MissionPolicy for update authorization (AC: #6, #7)
  - [x] Policy `update(User $user, Mission $mission)` already exists from Story 5-2
  - [x] Verify it checks: user is authenticated, user is Producer, user owns the mission
  - [x] Add status check: mission must be 'draft' or 'published' (not 'closed' or 'completed')

- [x] Task 3: Extend MissionService with update method (AC: #2)
  - [x] Add `updateMission(Mission $mission, array $data): Mission` method to `app/Services/MissionService.php`
  - [x] Update mission with validated data
  - [x] Return updated mission

- [x] Task 4: Add update endpoint to MissionController (AC: #1, #2, #6, #7, #9)
  - [x] Add `update(UpdateMissionRequest $request, Mission $mission): JsonResponse` method
  - [x] Use route model binding for mission
  - [x] Call MissionService to update mission
  - [x] Return MissionResource with 200 status and success message

- [x] Task 5: Add show endpoint to MissionController (needed for pre-filling form)
  - [x] Add `show(Mission $mission): JsonResponse` method
  - [x] Return MissionResource with mission details
  - [x] Authorize with MissionPolicy::view (already implemented - public access)

- [x] Task 6: Add API routes (AC: #1, #6, #7)
  - [x] Add `PUT /v1/producer/missions/{mission}` route for update
  - [x] Add `GET /v1/producer/missions/{mission}` route for show
  - [x] Routes protected by `auth:sanctum` middleware

- [x] Task 7: Create backend feature tests (AC: #1-#9)
  - [x] Create `tests/Feature/Mission/EditMissionTest.php`
  - [x] Test successful mission update
  - [x] Test validation errors (all fields)
  - [x] Test date validation (date_limite in past, date_tournage before date_limite)
  - [x] Test authorization (not owner, not logged in, not producer)
  - [x] Test status restriction (cannot edit closed/completed missions)
  - [x] Test response format

- [x] Task 8: Create useMissionEdit composable
  - [x] Create `frontend/src/features/mission/composables/useMissionEdit.ts`
  - [x] Load existing mission data on mount
  - [x] Manage form state with reactive refs (pre-filled with mission data)
  - [x] Handle API submission for update
  - [x] Manage loading and error states

- [x] Task 9: Create EditMissionPage view (USE GEMINI MCP: create_frontend)
  - [x] Use `create_frontend` MCP tool to create `frontend/src/pages/producer/mission/EditMissionPage.vue`
  - [x] Pass existing CSS/styling context to Gemini
  - [x] Reuse MissionForm component with edit mode prop
  - [x] Load mission data on mount using mission ID from route params
  - [x] Pass initial values to MissionForm
  - [x] Handle success/cancel navigation

- [x] Task 10: Update MissionForm component for edit mode (USE GEMINI MCP: modify_frontend)
  - [x] Use `modify_frontend` MCP tool to update `frontend/src/features/mission/components/MissionForm.vue`
  - [x] Pass existing CSS/styling context to Gemini
  - [x] Add `initialValues` prop for pre-filling form in edit mode
  - [x] Add `mode` prop: 'create' | 'edit'
  - [x] Update submit button text based on mode
  - [x] Emit event on successful submission

- [x] Task 11: Add frontend routes
  - [x] Add `/producer/missions/:id/edit` route to router
  - [x] Protect route with Producer role guard
  - [x] Add lazy loading for the page component

- [x] Task 12: Add missionApi methods for edit
  - [x] Add `getMission(id: number): Promise<MissionResponse>` to missionApi
  - [x] Add `updateMission(id: number, data: UpdateMissionData): Promise<MissionResponse>` to missionApi

- [x] Task 13: Type checking verification
  - [x] TypeScript type checking passes (vue-tsc --build)
  - [x] Backend tests pass (71 mission tests, 362 assertions)

## Dev Notes

### 🎨 CRITICAL: Frontend UI Implementation with Gemini MCP

**All frontend UI work MUST be done exclusively using the Gemini MCP tools:**

- **`modify_frontend`**: For updating existing components (e.g., adding edit mode to MissionForm.vue)
- **`snippet_frontend`**: For generating new UI snippets to insert into existing files
- **`create_frontend`**: For creating new page components (e.g., EditMissionPage.vue)

**DO NOT manually write frontend UI code.** Use Gemini MCP for:
- Adding edit mode props and logic to MissionForm.vue
- Creating the EditMissionPage.vue with proper layout
- Any UI modifications or additions

**You CAN write yourself:**
- TypeScript types and interfaces
- Composables (useMissionEdit.ts) - logic only
- API service methods (missionApi.ts)
- Router configuration
- Non-UI logic code

**Context to pass to Gemini MCP:**
Always pass the project's CSS/styling context. Read `frontend/src/assets/main.css` or relevant Tailwind config and pass it in the `context` parameter.

### Architecture Patterns (from Story 5-2)

- **Controllers**: `app/Http/Controllers/Api/V1/{Domain}/` - Controllers organized by domain
- **Form Requests**: `app/Http/Requests/{Domain}/` - Validation with French messages
- **API Resources**: `app/Http/Resources/` - Envelope format `{ data, meta, message }`
- **Services**: Business logic in Services, not Controllers
- **Policies**: Use Policies for resource authorization

### Database Schema Reference (from Story 5-1)

```php
// Mission table columns
$table->id();
$table->foreignId('producer_id')->constrained('producers')->cascadeOnDelete();
$table->string('titre', 150);
$table->text('description');
$table->date('date_tournage');
$table->text('profil_recherche');
$table->unsignedInteger('budget'); // XOF in integers
$table->date('date_limite_candidature');
$table->unsignedSmallInteger('nombre_faces_voulu')->default(1);
$table->string('type_mission', 50); // enum: publicite, film, court_metrage, clip_musical, autre
$table->string('genre_voulu', 20); // enum: homme, femme, tous
$table->string('lieu', 150);
$table->string('duree', 100); // e.g., "2 jours", "4 heures"
$table->string('status', 20)->default('draft'); // enum: draft, published, closed, completed
$table->timestamps();
```

### Enum Values Reference (from Story 5-1)

**MissionStatus** (`App\Enums\MissionStatus`):
- `draft` - Brouillon
- `published` - Publiée
- `closed` - Clôturée (cannot be edited)
- `completed` - Terminée (cannot be edited)

**MissionType** (`App\Enums\MissionType`):
- `publicite` - Publicité
- `film` - Film
- `court_metrage` - Court-métrage
- `clip_musical` - Clip musical
- `autre` - Autre

**MissionGender** (`App\Enums\MissionGender`):
- `homme` - Homme
- `femme` - Femme
- `tous` - Homme et Femme

### API Endpoint Specification

```
PUT /api/v1/producer/missions/{id}
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "titre": "Updated mission title",
  "description": "Updated description...",
  "date_tournage": "2026-03-01",
  "profil_recherche": "Updated profile requirements...",
  "budget": 200000,
  "date_limite_candidature": "2026-02-15",
  "nombre_faces_voulu": 5,
  "type_mission": "film",
  "genre_voulu": "tous",
  "lieu": "Abidjan, Côte d'Ivoire",
  "duree": "3 jours"
}

Success Response (200):
{
  "data": {
    "id": 1,
    "titre": "Updated mission title",
    "description": "...",
    "date_tournage": "2026-03-01T00:00:00.000000Z",
    "profil_recherche": "...",
    "budget": 200000,
    "date_limite_candidature": "2026-02-15T00:00:00.000000Z",
    "nombre_faces_voulu": 5,
    "type_mission": "film",
    "type_mission_label": "Film",
    "genre_voulu": "tous",
    "genre_voulu_label": "Homme et Femme",
    "lieu": "Abidjan, Côte d'Ivoire",
    "duree": "3 jours",
    "status": "published",
    "status_label": "Publiée",
    "is_accepting_candidatures": true,
    "created_at": "2026-01-19T14:30:00.000000Z",
    "updated_at": "2026-01-21T10:15:00.000000Z"
  },
  "message": "Mission modifiée avec succès"
}

GET /api/v1/producer/missions/{id}
Authorization: Bearer {token}

Success Response (200):
{
  "data": { /* MissionResource */ },
  "message": null
}

Validation Error Response (422):
{
  "message": "Le titre est obligatoire. (et 2 autres erreurs)",
  "errors": {
    "titre": ["Le titre est obligatoire."],
    "budget": ["Le budget doit être un nombre positif."]
  }
}

Status Restriction Error (422):
{
  "message": "Une mission clôturée ou terminée ne peut pas être modifiée",
  "errors": {
    "mission": ["Une mission clôturée ou terminée ne peut pas être modifiée"]
  }
}

Unauthorized Response (401):
{
  "error": {
    "code": "UNAUTHENTICATED",
    "message": "Unauthenticated"
  }
}

Forbidden Response (403):
{
  "error": {
    "code": "FORBIDDEN",
    "message": "Cette action n'est pas autorisée"
  }
}

Not Found Response (404):
{
  "error": {
    "code": "NOT_FOUND",
    "message": "Mission non trouvée"
  }
}
```

### Existing Files to Reuse/Extend

**From Story 5-2 (can be reused):**
- `backend/app/Policies/MissionPolicy.php` - update method already exists, needs status check
- `backend/app/Http/Resources/MissionResource.php` - no changes needed
- `backend/app/Services/MissionService.php` - add updateMission method
- `backend/app/Http/Controllers/Api/V1/Producer/MissionController.php` - add update & show methods
- `backend/app/Http/Requests/Mission/StoreMissionRequest.php` - reuse validation rules
- `frontend/src/features/mission/components/MissionForm.vue` - add edit mode support
- `frontend/src/features/mission/types/mission.ts` - add UpdateMissionData type
- `frontend/src/features/mission/schemas/mission.ts` - reuse validation schema

### Project Structure Notes

**Files to create:**
```
backend/
├── app/
│   └── Http/
│       └── Requests/Mission/
│           └── UpdateMissionRequest.php
└── tests/
    └── Feature/Mission/
        └── EditMissionTest.php

frontend/
└── src/
    ├── features/
    │   └── mission/
    │       └── composables/
    │           └── useMissionEdit.ts
    └── pages/
        └── producer/
            └── mission/
                └── EditMissionPage.vue
```

**Files to modify:**
```
backend/
├── app/Policies/MissionPolicy.php           # Add status check to update method
├── app/Services/MissionService.php          # Add updateMission method
├── app/Http/Controllers/Api/V1/Producer/MissionController.php  # Add update & show methods
└── routes/api/producer.php                  # Add PUT and GET routes

frontend/
├── src/features/mission/components/MissionForm.vue     # Add edit mode
├── src/features/mission/services/missionApi.ts         # Add getMission & updateMission
├── src/features/mission/types/mission.ts               # Add UpdateMissionData type
├── src/features/mission/composables/index.ts           # Export useMissionEdit
└── src/router/index.ts                                 # Add edit route
```

### Validation Rules (same as StoreMissionRequest)

| Field | Rules |
|-------|-------|
| titre | required, string, max:150 |
| description | required, string, max:10000 |
| date_tournage | required, date, after:date_limite_candidature |
| profil_recherche | required, string, max:5000 |
| budget | required, integer, min:1 |
| date_limite_candidature | required, date, after:today |
| nombre_faces_voulu | nullable, integer, min:1 |
| type_mission | required, in:publicite,film,court_metrage,clip_musical,autre |
| genre_voulu | required, in:homme,femme,tous |
| lieu | required, string, max:150 |
| duree | required, string, max:100 |

### French Error Messages (same as StoreMissionRequest)

```php
[
    'titre.required' => 'Le titre est obligatoire.',
    'titre.max' => 'Le titre ne peut pas dépasser :max caractères.',
    'description.required' => 'La description est obligatoire.',
    'description.max' => 'La description ne peut pas dépasser :max caractères.',
    'date_tournage.required' => 'La date de tournage est obligatoire.',
    'date_tournage.date' => 'La date de tournage doit être une date valide.',
    'date_tournage.after' => 'La date de tournage doit être après la date limite de candidature.',
    'profil_recherche.required' => 'Le profil recherché est obligatoire.',
    'profil_recherche.max' => 'Le profil recherché ne peut pas dépasser :max caractères.',
    'budget.required' => 'Le budget est obligatoire.',
    'budget.integer' => 'Le budget doit être un nombre entier.',
    'budget.min' => 'Le budget doit être un nombre positif.',
    'date_limite_candidature.required' => 'La date limite de candidature est obligatoire.',
    'date_limite_candidature.date' => 'La date limite doit être une date valide.',
    'date_limite_candidature.after' => 'La date limite doit être dans le futur.',
    'nombre_faces_voulu.integer' => 'Le nombre de Faces doit être un nombre entier.',
    'nombre_faces_voulu.min' => 'Le nombre de Faces doit être au moins 1.',
    'type_mission.required' => 'Le type de mission est obligatoire.',
    'type_mission.in' => 'Le type de mission sélectionné est invalide.',
    'genre_voulu.required' => 'Le genre recherché est obligatoire.',
    'genre_voulu.in' => 'Le genre sélectionné est invalide.',
    'lieu.required' => 'Le lieu est obligatoire.',
    'lieu.max' => 'Le lieu ne peut pas dépasser :max caractères.',
    'duree.required' => 'La durée est obligatoire.',
    'duree.max' => 'La durée ne peut pas dépasser :max caractères.',
]
```

### Previous Story Intelligence (Story 5-2)

From story 5-2 implementation:
- MissionPolicy already has `update(User $user, Mission $mission)` method checking ownership
- MissionResource already transforms all fields with enum labels
- MissionService exists - just need to add updateMission method
- MissionController exists - just need to add update & show methods
- StoreMissionRequest has all validation rules - can extract to shared trait or extend
- MissionForm component exists - add initialValues and mode props
- missionApi service exists - add getMission and updateMission methods
- mission types exist - add UpdateMissionData type (same as CreateMissionData)

### Critical Implementation Notes

1. **Status Check**: Must prevent editing of closed/completed missions - check in both Policy and FormRequest
2. **Ownership Check**: User must own the mission - Policy already handles this
3. **Reuse Validation**: Extract validation rules from StoreMissionRequest to a trait or reuse directly
4. **Date Validation Edge Case**: If date_limite_candidature is already in the past but mission is still published, allow keeping the same date or setting a future date (don't force changing valid historical data)
5. **MissionForm Reuse**: Add mode='create'|'edit' prop, initialValues prop, update button text
6. **Route Parameter**: Use `:id` in Vue Router, `{mission}` in Laravel (route model binding)
7. **Optimistic Update**: Consider showing loading state while updating, revert on error

### Testing Standards

**Backend Test Cases for EditMissionTest.php:**
1. `it_can_update_a_published_mission`
2. `it_returns_updated_mission_in_envelope_format`
3. `it_validates_required_fields`
4. `it_validates_titre_max_length`
5. `it_validates_description_max_length`
6. `it_validates_profil_recherche_max_length`
7. `it_validates_date_limite_candidature_is_in_future`
8. `it_validates_date_tournage_is_after_date_limite`
9. `it_validates_budget_is_positive_integer`
10. `it_validates_type_mission_enum_values`
11. `it_validates_genre_voulu_enum_values`
12. `it_prevents_editing_closed_mission`
13. `it_prevents_editing_completed_mission`
14. `it_prevents_non_owner_from_editing`
15. `it_prevents_face_from_editing_mission`
16. `it_requires_authentication`
17. `it_returns_404_for_nonexistent_mission`
18. `it_can_update_a_draft_mission`

### Git Intelligence Summary

Recent commits show:
- Story 5-2 (publish mission) is complete with 26 tests
- MissionController, MissionPolicy, MissionService, MissionResource already exist
- Frontend mission feature module is well-structured
- MissionForm component has premium UI design using Gemini MCP
- All 323+ backend tests are passing

### Security Considerations

1. **Authorization**: Double-check ownership in Policy AND Form Request
2. **Status Protection**: Prevent editing closed/completed missions (business rule)
3. **Input Validation**: All fields must be validated (reuse existing rules)
4. **SQL Injection**: Use Eloquent (already handled)
5. **Mass Assignment**: Use `$request->validated()` not `$request->all()`

### Performance Considerations

1. **Eager Loading**: Load producer relationship when returning mission
2. **Single Query**: Update should be a single query, not multiple
3. **Validation First**: Validate before any database operations

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 5.3]
- [Source: _bmad-output/project-context.md#Framework-Specific Rules]
- [Source: _bmad-output/implementation-artifacts/5-2-publish-mission.md]
- [Source: backend/app/Models/Mission.php]
- [Source: backend/app/Policies/MissionPolicy.php]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/MissionController.php]
- [Source: backend/app/Services/MissionService.php]
- [Source: frontend/src/features/mission/components/MissionForm.vue]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

None

### Completion Notes List

- Backend implementation complete with full test coverage (25 tests in EditMissionTest.php)
- Frontend implementation complete with edit mode support in MissionForm component
- Fixed Zod 4 + VeeValidate compatibility issues in mission schema (budget validation, default values)
- All acceptance criteria verified through tests
- Type checking passes

### Senior Developer Review (AI)

**Review Date:** 2026-01-26
**Reviewer:** Claude Opus 4.5

**Issues Found & Fixed:**
1. **[HIGH] Removed redundant Gate::authorize() call** - MissionController::update() had double authorization check (FormRequest + Gate). Removed Gate call to prevent 403 vs 422 response inconsistency.
2. **[HIGH] Authorization flow clarified** - FormRequest handles ownership check, withValidator handles status check with proper French error message.
3. **[MEDIUM] Extracted shared validation to trait** - Created `MissionValidationRules` trait to eliminate ~90 lines of duplicated code between StoreMissionRequest and UpdateMissionRequest.
4. **[MEDIUM] Added missing show endpoint tests** - Added 3 tests for show endpoint: other producer can view, face can view, unauthenticated returns 401.
5. **[MEDIUM] Fixed frontend date parsing** - Added safer date parsing using `substring()` instead of `split()[0]` to avoid TypeScript strict mode issues.
6. **[LOW] Added JSDoc return types** - Added `@return Mission` to MissionService methods.

**Outcome:** APPROVED - All HIGH and MEDIUM issues fixed. 25 tests passing (129 assertions).

### File List

**Created:**
- `backend/app/Http/Requests/Mission/UpdateMissionRequest.php` - Form request with validation
- `backend/app/Http/Requests/Mission/MissionValidationRules.php` - Shared validation trait
- `backend/tests/Feature/Mission/EditMissionTest.php` - Feature tests (25 tests)
- `frontend/src/features/mission/composables/useMissionEdit.ts` - Edit composable
- `frontend/src/pages/producer/mission/EditMissionPage.vue` - Edit page component

**Modified:**
- `backend/app/Http/Controllers/Api/V1/Producer/MissionController.php` - Added show/update methods, removed redundant Gate call
- `backend/app/Http/Requests/Mission/StoreMissionRequest.php` - Refactored to use MissionValidationRules trait
- `backend/app/Policies/MissionPolicy.php` - Added status check to update method
- `backend/app/Services/MissionService.php` - Added updateMission method with JSDoc
- `backend/routes/api/producer.php` - Added GET/PUT routes
- `frontend/src/features/mission/components/MissionForm.vue` - Added edit mode support
- `frontend/src/features/mission/composables/index.ts` - Export useMissionEdit
- `frontend/src/features/mission/services/missionApi.ts` - Added getMission/updateMission
- `frontend/src/features/mission/types/mission.ts` - Added UpdateMissionData type
- `frontend/src/router/index.ts` - Added edit-mission route
- `_bmad-output/implementation-artifacts/sprint-status.yaml` - Sprint tracking updates

