# Story 5.2: Publish Mission

Status: completed

## Story

As a **Producer**,
I want **to publish a mission with all required details**,
so that **Faces can discover and apply to my casting call**.

## Acceptance Criteria

1. **Given** I am logged in as a Producer **When** I fill out the mission form with all required fields (titre, description, date_tournage, profil_recherche, budget, date_limite_candidature, nombre_faces_voulu, type_mission, genre_voulu, lieu, duree) **Then** the mission is created with status "published" **And** I am redirected to my missions list **And** the mission appears in public listings

2. **Given** I submit a mission form with missing required fields **When** validation runs **Then** I see appropriate error messages in French for each missing field

3. **Given** I submit a date_limite_candidature in the past **When** validation runs **Then** I see an error message "La date limite doit être dans le futur"

4. **Given** I submit a date_tournage before date_limite_candidature **When** validation runs **Then** I see an error message "La date de tournage doit être après la date limite de candidature"

5. **Given** I submit an invalid type_mission or genre_voulu value **When** validation runs **Then** I see an error message about invalid selection

6. **Given** the mission is successfully created **When** the API responds **Then** the response follows the envelope format: `{ data: MissionResource, message: "Mission publiée avec succès" }`

7. **Given** I am not logged in or not a Producer **When** I try to access the publish mission endpoint **Then** I receive a 401 Unauthorized or 403 Forbidden response

**(FR25)**

## Tasks / Subtasks

- [x] Task 1: Create Mission Policy (AC: #7)
  - [x] Create `app/Policies/MissionPolicy.php`
  - [x] Implement `create(User $user): bool` - return true only if user is a Producer
  - [x] Implement `view(User $user, Mission $mission): bool` - public access
  - [x] Implement `update(User $user, Mission $mission): bool` - only mission owner
  - [x] Implement `delete(User $user, Mission $mission): bool` - only mission owner
  - [x] Laravel 12 auto-discovers policies (no manual registration needed)

- [x] Task 2: Create StoreMissionRequest Form Request (AC: #2, #3, #4, #5)
  - [x] Create `app/Http/Requests/Mission/StoreMissionRequest.php`
  - [x] Implement `authorize(): bool` - check user is Producer
  - [x] Implement validation rules for all mission fields
  - [x] Implement `messages(): array` with French error messages
  - [x] Implement `prepareForValidation()` to set default nombre_faces_voulu if not provided

- [x] Task 3: Create MissionResource API Resource (AC: #6)
  - [x] Create `app/Http/Resources/MissionResource.php`
  - [x] Implement `toArray()` method with all mission fields
  - [x] Format dates as ISO 8601 strings
  - [x] Include enum labels alongside values
  - [x] Include producer relationship (whenLoaded)
  - [x] Add computed fields: `is_accepting_candidatures`, `candidatures_count` (whenLoaded)

- [x] Task 4: Create MissionService (AC: #1)
  - [x] Create `app/Services/MissionService.php`
  - [x] Implement `createMission(Producer $producer, array $data): Mission`
  - [x] Set status to 'published' in the service
  - [x] Associate mission with producer

- [x] Task 5: Create MissionController (AC: #1, #6, #7)
  - [x] Create `app/Http/Controllers/Api/V1/Producer/MissionController.php`
  - [x] Inject MissionService via constructor
  - [x] Implement `store(StoreMissionRequest $request): JsonResponse`
  - [x] Get authenticated Producer from request
  - [x] Call MissionService to create mission
  - [x] Return MissionResource with 201 status and success message

- [x] Task 6: Add API routes (AC: #1, #7)
  - [x] Update `routes/api/producer.php`
  - [x] Add `POST /v1/producer/missions` route pointing to MissionController@store
  - [x] Ensure route is protected by `auth:sanctum` middleware

- [x] Task 7: Create backend feature tests (AC: #1, #2, #3, #4, #5, #6, #7)
  - [x] Create `tests/Feature/Mission/PublishMissionTest.php`
  - [x] 26 comprehensive tests covering all acceptance criteria
  - [x] All tests passing (156 assertions)

- [x] Task 8: Create frontend mission API service
  - [x] Create `frontend/src/features/mission/services/missionApi.ts`
  - [x] Implement `createMission(data: CreateMissionData): Promise<MissionResponse>`

- [x] Task 9: Create frontend mission types
  - [x] Create `frontend/src/features/mission/types/mission.ts`
  - [x] Define `Mission` interface matching API response
  - [x] Define `CreateMissionData` interface for form submission
  - [x] Define `MissionStatus`, `MissionType`, `MissionGender` enums with labels

- [x] Task 10: Create useMissionCreate composable
  - [x] Create `frontend/src/features/mission/composables/useMissionCreate.ts`
  - [x] Manage form state with reactive refs
  - [x] Handle API submission
  - [x] Manage loading and error states

- [x] Task 11: Create MissionForm component
  - [x] Create `frontend/src/features/mission/components/MissionForm.vue`
  - [x] Build form with all required fields
  - [x] Use appropriate input types (date pickers, selects for enums, number inputs)
  - [x] Display validation errors with VeeValidate + Zod
  - [x] Add submit button with loading state
  - [x] Premium UI design with Gemini MCP assistance

- [x] Task 12: Create PublishMissionPage view
  - [x] Create `frontend/src/pages/producer/mission/PublishMissionPage.vue`
  - [x] Import and use MissionForm component
  - [x] Add page header and navigation
  - [x] Handle success/cancel navigation

- [x] Task 13: Add frontend routes
  - [x] Add `/producer/missions/publish` route to router
  - [x] Protect route with Producer role guard
  - [x] Add lazy loading for the page component
  - [x] Add "Publier une mission" card to ProducerDashboard

- [x] Task 14: Type checking verification
  - [x] TypeScript type checking passes (vue-tsc --build)
  - [x] Backend tests pass (26 tests, 156 assertions)

## Dev Notes

### Architecture Patterns

- **Controllers**: `app/Http/Controllers/Api/V1/{Domain}/` - Controllers organized by domain [Source: project-context.md#Framework-Specific Rules]
- **Form Requests**: `app/Http/Requests/{Domain}/` - Validation with French messages [Source: project-context.md#Framework-Specific Rules]
- **API Resources**: `app/Http/Resources/` - Envelope format `{ data, meta, message }` [Source: project-context.md#API Response Format]
- **Services**: Business logic in Services, not Controllers [Source: project-context.md#Anti-Patterns to Avoid]
- **Policies**: Use Policies for resource authorization [Source: project-context.md#Framework-Specific Rules]

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

**MissionStatus**:
- `draft` - Brouillon (not used in this story - we publish directly)
- `published` - Publiée (set on creation)
- `closed` - Clôturée
- `completed` - Terminée

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
POST /api/v1/producer/missions
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "titre": "Recherche acteurs pour publicité Coca",
  "description": "Nous recherchons 3 acteurs pour une publicité...",
  "date_tournage": "2026-02-15",
  "profil_recherche": "Hommes 25-35 ans, bonne élocution...",
  "budget": 150000,
  "date_limite_candidature": "2026-02-01",
  "nombre_faces_voulu": 3,
  "type_mission": "publicite",
  "genre_voulu": "homme",
  "lieu": "Cotonou, Bénin",
  "duree": "1 journée"
}

Success Response (201):
{
  "data": {
    "id": 1,
    "titre": "Recherche acteurs pour publicité Coca",
    "description": "...",
    "date_tournage": "2026-02-15T00:00:00.000000Z",
    "profil_recherche": "...",
    "budget": 150000,
    "date_limite_candidature": "2026-02-01T00:00:00.000000Z",
    "nombre_faces_voulu": 3,
    "type_mission": "publicite",
    "type_mission_label": "Publicité",
    "genre_voulu": "homme",
    "genre_voulu_label": "Homme",
    "lieu": "Cotonou, Bénin",
    "duree": "1 journée",
    "status": "published",
    "status_label": "Publiée",
    "is_accepting_candidatures": true,
    "created_at": "2026-01-19T14:30:00.000000Z",
    "updated_at": "2026-01-19T14:30:00.000000Z"
  },
  "message": "Mission publiée avec succès"
}

Validation Error Response (422):
{
  "message": "Le titre est obligatoire. (et 2 autres erreurs)",
  "errors": {
    "titre": ["Le titre est obligatoire."],
    "budget": ["Le budget doit être un nombre positif."],
    "date_limite_candidature": ["La date limite doit être dans le futur."]
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
    "message": "Accès réservé aux Producteurs"
  }
}
```

### Project Structure Notes

**Files to create:**
```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/Producer/
│   │   │   └── MissionController.php
│   │   ├── Requests/Mission/
│   │   │   └── StoreMissionRequest.php
│   │   └── Resources/
│   │       └── MissionResource.php
│   ├── Policies/
│   │   └── MissionPolicy.php
│   └── Services/
│       └── MissionService.php
└── tests/
    └── Feature/Mission/
        └── PublishMissionTest.php

frontend/
└── src/
    └── features/
        └── mission/
            ├── components/
            │   └── MissionForm.vue
            ├── composables/
            │   └── useMissionCreate.ts
            ├── services/
            │   └── missionApi.ts
            ├── types/
            │   └── mission.ts
            └── views/
                └── PublishMissionPage.vue
```

**Files to modify:**
```
backend/
├── app/Providers/AuthServiceProvider.php  # Register MissionPolicy
└── routes/api/producer.php                 # Add mission routes

frontend/
└── src/router/index.ts                     # Add /producer/missions/new route
```

### Testing Standards

- **Backend**: Use Pest syntax, RefreshDatabase trait
- **Frontend**: Use Vitest + Vue Test Utils, mock API calls
- **Coverage**: Test all acceptance criteria
- **Patterns**: Follow existing test patterns from previous stories

### Validation Rules Summary

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

### French Error Messages

```php
[
    'titre.required' => 'Le titre est obligatoire.',
    'titre.max' => 'Le titre ne peut pas dépasser :max caractères.',
    'description.required' => 'La description est obligatoire.',
    'date_tournage.required' => 'La date de tournage est obligatoire.',
    'date_tournage.date' => 'La date de tournage doit être une date valide.',
    'date_tournage.after' => 'La date de tournage doit être après la date limite de candidature.',
    'profil_recherche.required' => 'Le profil recherché est obligatoire.',
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

### Previous Story Intelligence (Story 5-1)

From story 5-1 implementation:
- Mission model already exists with all fields and enum casts
- MissionStatus, MissionType, MissionGender enums exist with `values()` helper
- Producer→missions HasMany relationship exists
- MissionFactory with states for all statuses exists
- Scopes: draft(), published(), closed(), completed(), acceptingCandidatures()
- Indexes on: producer_id, status, date_tournage, date_limite_candidature, (status, date_limite_candidature) composite, type_mission

### Git Intelligence Summary

Recent commits show:
- Story 5-1 (mission schema) is complete with comprehensive tests
- Performance indexes were added in code review
- Factory uses realistic Benin cities
- All 323 backend tests passing

### Critical Implementation Notes

1. **Status on Creation**: Set status to `MissionStatus::Published` in the service, not draft
2. **Authentication**: User must be authenticated AND be a Producer (userable_type = Producer::class)
3. **Date Validation Order**: `date_limite_candidature` must be after today, `date_tournage` must be after `date_limite_candidature`
4. **Budget Format**: Store as integer (XOF), frontend formats with thousands separator
5. **Enum Validation**: Use `MissionType::values()` and `MissionGender::values()` for validation rules
6. **Response Format**: Follow envelope format with MissionResource, include enum labels

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 5.2]
- [Source: _bmad-output/project-context.md#Framework-Specific Rules]
- [Source: _bmad-output/implementation-artifacts/5-1-create-mission-database-schema.md]
- [Source: backend/app/Models/Mission.php]
- [Source: backend/app/Enums/MissionStatus.php]
- [Source: docs/planning-artifacts/architecture.md#API Response Format]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

N/A

### Completion Notes List

1. **Backend Implementation Complete**: All 7 backend tasks completed with 28 passing tests covering all acceptance criteria
2. **Frontend Implementation Complete**: Full mission creation feature with premium UI design using Gemini MCP
3. **Laravel 12 Auto-Discovery**: Policies are auto-discovered, no manual registration in AuthServiceProvider needed
4. **TypeScript Type Safety**: All frontend code passes vue-tsc type checking
5. **API Envelope Format**: Response follows `{ data: MissionResource, message: "..." }` format
6. **French Localization**: All validation messages and UI labels in French

### Code Review Fixes Applied

The following issues were identified and fixed during adversarial code review:

1. **Budget validation message alignment** - Frontend Zod schema message now matches backend ("Le budget doit être un nombre positif")
2. **Timezone-safe date validation** - Frontend date parsing now uses explicit `T00:00:00` suffix to avoid timezone issues
3. **Form disabled during submission** - Added `<fieldset :disabled="isSubmitting">` to prevent input modification while submitting
4. **Security: Max length validation** - Added `max:10000` to description and `max:5000` to profil_recherche to prevent abuse
5. **Test coverage** - Added 2 new tests for description and profil_recherche max length validation (28 tests total, 164 assertions)
7. **VeeValidate + Zod**: Frontend form validation with schema matching backend rules

### File List

**Backend Files Created:**
- `backend/app/Policies/MissionPolicy.php`
- `backend/app/Http/Requests/Mission/StoreMissionRequest.php`
- `backend/app/Http/Resources/MissionResource.php`
- `backend/app/Services/MissionService.php`
- `backend/app/Http/Controllers/Api/V1/Producer/MissionController.php`
- `backend/tests/Feature/Mission/PublishMissionTest.php`

**Backend Files Modified:**
- `backend/routes/api/producer.php` (added mission route)
- `backend/app/Models/Mission.php` (added isAcceptingCandidatures method)

**Frontend Files Created:**
- `frontend/src/features/mission/types/mission.ts`
- `frontend/src/features/mission/types/index.ts`
- `frontend/src/features/mission/services/missionApi.ts`
- `frontend/src/features/mission/services/index.ts`
- `frontend/src/features/mission/schemas/mission.ts`
- `frontend/src/features/mission/schemas/index.ts`
- `frontend/src/features/mission/composables/useMissionCreate.ts`
- `frontend/src/features/mission/composables/index.ts`
- `frontend/src/features/mission/components/MissionForm.vue`
- `frontend/src/features/mission/components/index.ts`
- `frontend/src/features/mission/index.ts`
- `frontend/src/pages/producer/mission/PublishMissionPage.vue`

**Frontend Files Modified:**
- `frontend/src/router/index.ts` (added publish-mission route)
- `frontend/src/pages/dashboard/ProducerDashboardPage.vue` (added publish mission card)

## Change Log

- 2026-01-19: Story created with comprehensive context for publish mission feature (Story 5.2)
- 2026-01-20: Story implementation completed - all backend and frontend tasks done, 26 backend tests passing
