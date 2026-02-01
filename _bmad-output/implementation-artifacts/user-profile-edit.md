# Story: User Profile Edit (Name/Username)

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a **Face or Producer**,
I want **to edit my basic profile information (name, username for Faces, agency name for Producers)**,
So that **I can update my information after registration if I made a mistake or need to change it**.

## Acceptance Criteria

1. **Given** I am logged in as a Face **When** I visit my profile edit page **Then** I see my current nom, prenom, and username in editable fields

2. **Given** I am logged in as a Face **When** I submit changes to my nom, prenom, or username **Then** my profile is updated and I see a success message in French

3. **Given** I am editing my Face profile **When** I enter a username that already exists **Then** I see an error message "Ce nom d'utilisateur est déjà pris"

4. **Given** I am logged in as a Producer (type: agency) **When** I visit my profile edit page **Then** I see my current agency_name in an editable field

5. **Given** I am logged in as a Producer (type: particulier) **When** I visit my profile edit page **Then** I see my current first_name and last_name in editable fields

6. **Given** I am editing my Producer profile **When** I submit changes to my name fields **Then** my profile is updated and I see a success message

7. **Given** the API request fails **When** I try to save my changes **Then** I see an appropriate error message with the ability to retry

8. **Given** I leave a required field empty **When** I try to submit **Then** validation prevents submission and shows the error

---

## ⚠️ MANDATORY: Reuse Existing Patterns

**This story MUST follow patterns from existing profile edit functionality:**

1. **BioLocationController** - Pattern for controller structure with `getAuthenticatedFace()` method
2. **useBioLocation composable** - Pattern for frontend composable with loading/saving states
3. **Form Request pattern** - UpdateBioLocationRequest as example

---

## Tasks / Subtasks

### Backend Tasks - Face

- [x] Task 1: Create UpdateBasicInfoRequest for Face (AC: #1, #2, #3, #8)
  - [x] Create `UpdateBasicInfoRequest.php` in `app/Http/Requests/Face/`
  - [x] Validate: nom (string, max:100), prenom (string, max:100), username (string, max:50, unique:faces,username with ignore current)
  - [x] French validation messages

- [x] Task 2: Create BasicInfoController for Face (AC: #1, #2, #3)
  - [x] Create `BasicInfoController.php` in `app/Http/Controllers/Api/V1/Face/`
  - [x] Add `show()` method to return current nom, prenom, username
  - [x] Add `update()` method following BioLocationController pattern
  - [x] Use internal authorization (check `userable_type === Face::class`)

- [x] Task 3: Add API routes for Face basic info (AC: #1, #2)
  - [x] Add route `GET /api/v1/face/basic-info` in `routes/api/face.php`
  - [x] Add route `PUT /api/v1/face/basic-info` in `routes/api/face.php`
  - [x] Apply `auth:sanctum` middleware

- [x] Task 4: Write Face basic info backend tests (AC: #1-3, #8)
  - [x] Test authenticated Face can GET basic info
  - [x] Test authenticated Face can UPDATE basic info
  - [x] Test username uniqueness validation
  - [x] Test unauthenticated user gets 401
  - [x] Test Producer user gets 403

### Backend Tasks - Producer

- [x] Task 5: Create UpdateBasicInfoRequest for Producer (AC: #4, #5, #6, #8)
  - [x] Create `UpdateBasicInfoRequest.php` in `app/Http/Requests/Producer/`
  - [x] Validate conditionally based on producer type:
    - Agency: agency_name (string, max:100)
    - Particulier: first_name (string, max:100), last_name (string, max:100)
  - [x] French validation messages

- [x] Task 6: Create BasicInfoController for Producer (AC: #4, #5, #6)
  - [x] Create `BasicInfoController.php` in `app/Http/Controllers/Api/V1/Producer/`
  - [x] Add `show()` method to return current name fields based on type
  - [x] Add `update()` method following existing pattern
  - [x] Use internal authorization (check `userable_type === Producer::class`)

- [x] Task 7: Add API routes for Producer basic info (AC: #4, #5)
  - [x] Add route `GET /api/v1/producer/basic-info` in `routes/api/producer.php`
  - [x] Add route `PUT /api/v1/producer/basic-info` in `routes/api/producer.php`
  - [x] Apply `auth:sanctum` middleware

- [x] Task 8: Write Producer basic info backend tests (AC: #4-6, #8)
  - [x] Test authenticated Producer (agency) can GET/UPDATE agency_name
  - [x] Test authenticated Producer (particulier) can GET/UPDATE first_name, last_name
  - [x] Test unauthenticated user gets 401
  - [x] Test Face user gets 403

### Frontend Tasks - Face

- [x] Task 9: Create Face basic info types (AC: #1, #2)
  - [x] Add `BasicInfo` interface to `frontend/src/features/face/types/index.ts`
  - [x] Add `BasicInfoResult` type for API responses

- [x] Task 10: Add Face basic info to faceApi service (AC: #1, #2)
  - [x] Add `getBasicInfo()` method
  - [x] Add `updateBasicInfo()` method

- [x] Task 11: Create useBasicInfo composable for Face (AC: #1, #2, #3, #7, #8)
  - [x] Create `useBasicInfo.ts` in `frontend/src/features/face/composables/`
  - [x] Follow useBioLocation pattern exactly
  - [x] Implement loading, saving, error states
  - [x] Add username validation (unique check via API)

- [x] Task 12: Create BasicInfoSection component for Face profile (AC: #1, #2, #3)
  - [x] Create `BasicInfoSection.vue` in `frontend/src/features/face/components/`
  - [x] Three inputs: nom, prenom, username
  - [x] Submit button with loading state
  - [x] Error and success feedback

- [x] Task 13: Integrate BasicInfoSection into Face ProfileEditPage (AC: #1, #2)
  - [x] Add BasicInfoSection as first section in profile edit
  - [x] Ensure proper data loading on mount

### Frontend Tasks - Producer

- [x] Task 14: Create Producer basic info types (AC: #4, #5)
  - [x] Add `ProducerBasicInfo` interface to `frontend/src/features/producer/types/index.ts`
  - [x] Handle type-dependent fields (agency vs particulier)

- [x] Task 15: Add Producer basic info to producerApi service (AC: #4, #5)
  - [x] Add `getBasicInfo()` method
  - [x] Add `updateBasicInfo()` method

- [x] Task 16: Create useProducerBasicInfo composable (AC: #4, #5, #6, #7, #8)
  - [x] Create `useProducerBasicInfo.ts` in `frontend/src/features/producer/composables/`
  - [x] Follow existing producer composable patterns
  - [x] Handle type-conditional fields

- [x] Task 17: Create BasicInfoSection component for Producer profile (AC: #4, #5, #6)
  - [x] Create `BasicInfoSection.vue` in `frontend/src/features/producer/components/`
  - [x] Conditional rendering based on producer type
  - [x] Submit button with loading state

- [x] Task 18: Integrate BasicInfoSection into Producer ProfileEditPage (AC: #4, #5)
  - [x] Add BasicInfoSection as first section in profile edit
  - [x] Ensure proper data loading on mount

### Testing

- [x] Task 19: Write frontend tests for Face BasicInfoSection (AC: #1-3, #7-8)
  - [x] Test renders current values
  - [x] Test form submission
  - [x] Test error states
  - [x] Test loading states

- [x] Task 20: Write frontend tests for Producer BasicInfoSection (AC: #4-6, #7-8)
  - [x] Test renders based on producer type
  - [x] Test form submission
  - [x] Test error states

- [x] Task 21: Update sprint-status.yaml
  - [x] Mark story user-profile-edit as "review" for code review

## Dev Notes

### Why This Story Matters

**CRITICAL GAP IDENTIFIED IN EPIC 9 RETROSPECTIVE:** Users cannot edit their basic registration information (name, username) after account creation. This was identified as a significant usability gap.

**Scope Decision:** This story covers name/username editing only. Email change requires re-verification and will be a separate story (as noted in retrospective action items).

### API Response Format

**GET /api/v1/face/basic-info:**
```json
{
  "data": {
    "nom": "Dupont",
    "prenom": "Jean",
    "username": "jeandupont"
  }
}
```

**PUT /api/v1/face/basic-info:**
```json
{
  "data": {
    "nom": "Dupont",
    "prenom": "Jean",
    "username": "jeandupont_updated"
  },
  "message": "Informations mises à jour avec succès"
}
```

**GET /api/v1/producer/basic-info (agency):**
```json
{
  "data": {
    "type": "agency",
    "agency_name": "Production ABC"
  }
}
```

**GET /api/v1/producer/basic-info (particulier):**
```json
{
  "data": {
    "type": "particulier",
    "first_name": "Marie",
    "last_name": "Martin"
  }
}
```

### Username Uniqueness Validation

**Backend validation rule:**
```php
'username' => [
    'sometimes',
    'string',
    'max:50',
    Rule::unique('faces', 'username')->ignore($face->id),
]
```

**Frontend debounced check (optional enhancement):**
Consider adding real-time username availability check similar to registration, but this is not required for MVP.

### Files to Create

**Backend:**
```
backend/
├── app/Http/Controllers/Api/V1/Face/
│   └── BasicInfoController.php (CREATE)
├── app/Http/Controllers/Api/V1/Producer/
│   └── BasicInfoController.php (CREATE)
├── app/Http/Requests/Face/
│   └── UpdateBasicInfoRequest.php (CREATE)
├── app/Http/Requests/Producer/
│   └── UpdateBasicInfoRequest.php (CREATE)
├── routes/api/
│   ├── face.php (MODIFY - add routes)
│   └── producer.php (MODIFY - add routes)
└── tests/Feature/
    ├── Face/BasicInfoTest.php (CREATE)
    └── Producer/BasicInfoTest.php (CREATE)
```

**Frontend:**
```
frontend/src/
├── features/face/
│   ├── types/index.ts (MODIFY - add BasicInfo types)
│   ├── services/faceApi.ts (MODIFY - add methods)
│   ├── composables/
│   │   └── useBasicInfo.ts (CREATE)
│   └── components/
│       └── BasicInfoSection.vue (CREATE)
├── features/producer/
│   ├── types/index.ts (MODIFY - add ProducerBasicInfo types)
│   ├── services/producerApi.ts (MODIFY - add methods)
│   ├── composables/
│   │   └── useProducerBasicInfo.ts (CREATE)
│   └── components/
│       └── BasicInfoSection.vue (CREATE)
├── pages/face/
│   └── ProfileEditPage.vue (MODIFY - add section)
├── pages/producer/
│   └── ProfileEditPage.vue (MODIFY - add section)
└── (test files colocated with components)
```

### Pattern Reference

**Follow BioLocationController pattern exactly:**
```php
class BasicInfoController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $face = $result;

        return response()->json([
            'data' => [
                'nom' => $face->nom,
                'prenom' => $face->prenom,
                'username' => $face->username,
            ],
        ]);
    }

    public function update(UpdateBasicInfoRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $face = $result;

        $face->update($request->validated());

        return response()->json([
            'data' => [
                'nom' => $face->nom,
                'prenom' => $face->prenom,
                'username' => $face->username,
            ],
            'message' => 'Informations mises à jour avec succès',
        ]);
    }

    private function getAuthenticatedFace(Request $request): Face|JsonResponse
    {
        // Same pattern as BioLocationController
    }
}
```

**Follow useBioLocation composable pattern:**
```typescript
export function useBasicInfo(): UseBasicInfoReturn {
  const basicInfo = ref<BasicInfo | null>(null)
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  async function fetchBasicInfo(): Promise<void> { /* ... */ }
  async function updateBasicInfo(data: BasicInfoUpdate): Promise<BasicInfoResult> { /* ... */ }

  return {
    basicInfo,
    isLoading,
    isSaving,
    error,
    fetchBasicInfo,
    updateBasicInfo,
    clearError,
  }
}
```

### UI Design

**BasicInfoSection placement in ProfileEditPage:**
```
┌─────────────────────────────────────────────────────────────────────┐
│ Mes informations personnelles (NEW SECTION - TOP)                   │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ Nom: [Dupont        ]                                           │ │
│ │ Prénom: [Jean       ]                                           │ │
│ │ Nom d'utilisateur: [@jeandupont]                                │ │
│ │                                          [Enregistrer]          │ │
│ └─────────────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────────────┤
│ Photo de profil (existing section)                                  │
├─────────────────────────────────────────────────────────────────────┤
│ Bio et localisation (existing section)                              │
└─────────────────────────────────────────────────────────────────────┘
```

### Edge Cases

1. **Username already taken**: Show French error "Ce nom d'utilisateur est déjà pris"
2. **Empty required fields**: Validation error before submit
3. **No changes made**: Allow submit (idempotent update)
4. **API timeout**: Show error with retry option
5. **Concurrent edit**: Last write wins (standard behavior)

### Security Considerations

- Endpoint protected with `auth:sanctum` middleware
- Controller checks `userable_type` for role-based access
- Only update own profile (enforced by controller logic)
- Validate and sanitize all inputs via Form Request

### Testing Standards

**Backend (PHPUnit/Pest):**
- Use `RefreshDatabase` trait
- Test happy path and error cases
- Test authorization (401, 403)

**Frontend (Vitest):**
- Mock API calls with `vi.mock()`
- Test loading, saving, error states
- Use `data-testid` for selectors

### References

- [Source: backend/app/Http/Controllers/Api/V1/Face/BioLocationController.php - Pattern to follow]
- [Source: frontend/src/features/face/composables/useBioLocation.ts - Composable pattern]
- [Source: _bmad-output/implementation-artifacts/epic-9-retro-2026-02-01.md - Gap identification]
- [Source: _bmad-output/project-context.md - Critical rules and patterns]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- Backend tests: 36 tests passing (19 Face + 17 Producer)
- Frontend tests: 70 tests passing (BasicInfo composables + components)

### Completion Notes List

1. Followed BioLocationController pattern exactly for controller structure
2. Used conditional validation in Producer FormRequest based on ProducerType enum
3. Username uniqueness validation uses `Rule::unique()->ignore($faceId)` to allow keeping same username
4. Frontend composables follow useBioLocation pattern with loading/saving/error states
5. BasicInfoSection components are self-contained with internal state management
6. All French validation messages implemented as per story requirements

### File List

**Backend - Created:**
- `backend/app/Http/Controllers/Api/V1/Face/BasicInfoController.php`
- `backend/app/Http/Controllers/Api/V1/Producer/BasicInfoController.php`
- `backend/app/Http/Requests/Face/UpdateBasicInfoRequest.php`
- `backend/app/Http/Requests/Producer/UpdateBasicInfoRequest.php`
- `backend/tests/Feature/Face/BasicInfoTest.php`
- `backend/tests/Feature/Producer/BasicInfoTest.php`

**Backend - Modified:**
- `backend/routes/api/face.php` (added basic-info routes)
- `backend/routes/api/producer.php` (added basic-info routes)

**Frontend - Created:**
- `frontend/src/features/face/composables/useBasicInfo.ts`
- `frontend/src/features/face/components/BasicInfoSection.vue`
- `frontend/src/features/face/composables/__tests__/useBasicInfo.spec.ts`
- `frontend/src/features/face/components/__tests__/BasicInfoSection.spec.ts`
- `frontend/src/features/producer/composables/useProducerBasicInfo.ts`
- `frontend/src/features/producer/components/BasicInfoSection.vue`
- `frontend/src/features/producer/composables/__tests__/useProducerBasicInfo.spec.ts`
- `frontend/src/features/producer/components/__tests__/BasicInfoSection.spec.ts`

**Frontend - Modified:**
- `frontend/src/features/face/types.ts` (added BasicInfo types)
- `frontend/src/features/face/services/faceApi.ts` (added getBasicInfo, updateBasicInfo)
- `frontend/src/features/producer/types.ts` (added ProducerBasicInfo types)
- `frontend/src/features/producer/services/producerApi.ts` (added getBasicInfo, updateBasicInfo)
- `frontend/src/pages/face/ProfileEditPage.vue` (integrated BasicInfoSection)
- `frontend/src/pages/producer/ProfileEditPage.vue` (integrated BasicInfoSection)
