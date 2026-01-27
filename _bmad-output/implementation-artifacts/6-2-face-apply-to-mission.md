# Story 6.2: Face Apply to Mission

Status: done

## Story

As a **Face**,
I want **to apply to a mission with an optional motivation message**,
so that **I can express my interest and stand out to the Producer**.

## Acceptance Criteria

1. **Given** I am viewing a published mission detail **When** I click "Postuler" **Then** a modal/form appears allowing me to add an optional motivation message

2. **Given** I am in the apply modal **When** I submit with or without a motivation message **Then** a candidature record is created with status "pending"

3. **Given** my candidature is created successfully **When** the API returns success **Then** I see a success message "Candidature envoyée" and the "Postuler" button is disabled/replaced

4. **Given** I have already applied to a mission **When** I view that mission again **Then** the "Postuler" button shows "Candidature envoyée" (disabled) instead

5. **Given** the mission is not accepting candidatures (past deadline or closed) **When** I try to apply **Then** I receive an error and cannot submit

6. **Given** I try to apply to the same mission twice **When** the API validates **Then** I receive an error "Vous avez déjà postulé à cette mission"

7. **Given** I am not a Face (Producer or unauthenticated) **When** I try to apply **Then** I receive 401/403 error

**(FR34)**

## Tasks / Subtasks

- [x] Task 1: Create CandidatureController for Face (AC: #2, #5, #6, #7)
  - [x] Create `app/Http/Controllers/Api/V1/Face/CandidatureController.php`
  - [x] Add `store(StoreCandidatureRequest $request, Mission $mission)` method
  - [x] Verify mission is accepting candidatures (published + deadline not passed)
  - [x] Verify Face hasn't already applied (unique constraint check)
  - [x] Create candidature with face_id, mission_id, message_motivation, status='pending'
  - [x] Return CandidatureResource with success message

- [x] Task 2: Create StoreCandidatureRequest for validation (AC: #2)
  - [x] Create `app/Http/Requests/Face/StoreCandidatureRequest.php`
  - [x] Authorize: user is a Face
  - [x] Rules: `message_motivation` is nullable string, max 2000 characters

- [x] Task 3: Create CandidatureResource for API response (AC: #3)
  - [x] Create `app/Http/Resources/CandidatureResource.php`
  - [x] Return: id, mission (nested), status, status_label, message_motivation, created_at

- [x] Task 4: Add route for applying to mission (AC: #7)
  - [x] Add `POST /v1/face/missions/{mission}/apply` route to `routes/api/face.php`
  - [x] Apply `face` and `throttle:30,1` middleware

- [x] Task 5: Add endpoint to check if Face has applied (AC: #4)
  - [x] Add method to check candidature status in Face\MissionController show or separate endpoint
  - [x] Return `has_applied: boolean` and `candidature_status` in mission detail response

- [x] Task 6: Create backend feature tests (AC: #1-7)
  - [x] Create `tests/Feature/Candidature/FaceApplyToMissionTest.php`
  - [x] Test Face can apply to published mission with motivation
  - [x] Test Face can apply to published mission without motivation
  - [x] Test candidature is created with status 'pending'
  - [x] Test Face cannot apply to draft mission (404)
  - [x] Test Face cannot apply to closed mission (422)
  - [x] Test Face cannot apply to mission past deadline (422)
  - [x] Test Face cannot apply twice to same mission (422)
  - [x] Test Producer cannot apply (403)
  - [x] Test unauthenticated cannot apply (401)
  - [x] Test motivation message max length validation

- [x] Task 7: Add applyToMission to faceMissionApi (AC: #2)
  - [x] Add `applyToMission(missionId: number, motivation?: string)` method
  - [x] Return `CandidatureResponse` type

- [x] Task 8: Create useApplyToMission composable (AC: #2, #3, #6)
  - [x] Create `frontend/src/features/candidature/composables/useApplyToMission.ts`
  - [x] Manage loading, error, success states
  - [x] Handle duplicate application error gracefully
  - [x] Export from composables/index.ts

- [x] Task 9: Create ApplyToMissionModal component (AC: #1, #3)
  - [x] Create `frontend/src/features/candidature/components/ApplyToMissionModal.vue`
  - [x] Show textarea for optional motivation message (max 2000 chars)
  - [x] Show character count
  - [x] Show loading state during submission
  - [x] Show success message and close modal on success
  - [x] Use Gemini MCP for UI design

- [x] Task 10: Update FaceMissionDetailPage (AC: #1, #3, #4)
  - [x] Import and use ApplyToMissionModal
  - [x] Update "Postuler" button to open modal
  - [x] Check if user has already applied and show appropriate button state
  - [x] Update useMissionDetail to fetch candidature status

- [x] Task 11: TypeScript types and verification
  - [x] Add Candidature types to `frontend/src/types/`
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (no regressions)

## Dev Notes

### 🚨 CRITICAL: Use Gemini MCP for Frontend UI

**You MUST use the Gemini MCP tools for all frontend UI development:**
- `create_frontend` - For new components (ApplyToMissionModal)
- `modify_frontend` - For modifying existing components (FaceMissionDetailPage)
- `snippet_frontend` - For smaller UI pieces

**Always pass the existing CSS/theme files in the `context` parameter** to ensure design consistency.

### 🎯 This story connects Story 5-10 (View Mission Detail) with Story 6-1 (Candidature Schema)

The "Postuler" button from Story 5-10 now becomes functional, creating candidatures using the schema from Story 6-1.

### Architecture Patterns

**Backend:**
- Use Form Request for validation
- Use Resource for response transformation
- Check business rules in Controller (mission accepting candidatures)
- Catch unique constraint violation and return user-friendly error

**Frontend:**
- Modal pattern for apply form
- Composable for API interaction
- Update parent component state after successful apply

### API Endpoint

```
POST /api/v1/face/missions/{mission_id}/apply
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "message_motivation": "Je suis très motivée pour cette mission car..." // Optional
}

Response (201 Created):
{
  "data": {
    "id": 1,
    "mission_id": 5,
    "status": "pending",
    "status_label": "En attente",
    "message_motivation": "Je suis très motivée...",
    "created_at": "2026-01-27T10:00:00Z"
  },
  "message": "Candidature envoyée avec succès"
}

Response (422 Unprocessable):
{
  "error": {
    "code": "ALREADY_APPLIED",
    "message": "Vous avez déjà postulé à cette mission"
  }
}

Response (422 Unprocessable):
{
  "error": {
    "code": "MISSION_CLOSED",
    "message": "Cette mission n'accepte plus de candidatures"
  }
}

Response (404): Mission not found or not published
Response (401): Unauthenticated
Response (403): Not a Face user
```

### Controller Pattern

```php
// Face\CandidatureController
public function store(StoreCandidatureRequest $request, Mission $mission): JsonResponse
{
    // Check mission is published
    if ($mission->status !== MissionStatus::Published) {
        abort(404);
    }

    // Check mission is accepting candidatures
    if (!$mission->isAcceptingCandidatures()) {
        return response()->json([
            'error' => [
                'code' => 'MISSION_CLOSED',
                'message' => "Cette mission n'accepte plus de candidatures",
            ],
        ], 422);
    }

    // Get Face from authenticated user
    $face = $request->user()->userable;

    // Check if already applied (catch DB exception or check first)
    if (Candidature::where('face_id', $face->id)->where('mission_id', $mission->id)->exists()) {
        return response()->json([
            'error' => [
                'code' => 'ALREADY_APPLIED',
                'message' => 'Vous avez déjà postulé à cette mission',
            ],
        ], 422);
    }

    $candidature = Candidature::create([
        'face_id' => $face->id,
        'mission_id' => $mission->id,
        'message_motivation' => $request->validated('message_motivation'),
        // status defaults to 'pending'
    ]);

    return response()->json([
        'data' => new CandidatureResource($candidature),
        'message' => 'Candidature envoyée avec succès',
    ], 201);
}
```

### Form Request Pattern

```php
// StoreCandidatureRequest
public function authorize(): bool
{
    return $this->user()?->userable_type === 'App\\Models\\Face';
}

public function rules(): array
{
    return [
        'message_motivation' => ['nullable', 'string', 'max:2000'],
    ];
}
```

### Frontend Modal Design

```
┌─────────────────────────────────────────────────────────────────┐
│                      Postuler à cette mission                   │
│                              ✕                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Message de motivation (optionnel)                              │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                                                         │   │
│  │ Bonjour,                                                │   │
│  │ Je suis très intéressée par cette mission car...       │   │
│  │                                                         │   │
│  │                                                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                   125 / 2000    │
│                                                                 │
│  ┌─────────────────────┐  ┌─────────────────────────────────┐  │
│  │     Annuler         │  │        Envoyer ma candidature   │  │
│  └─────────────────────┘  └─────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Mission Detail Button States

```
State 1: Can Apply (mission open, not applied)
┌─────────────────────────────────┐
│           POSTULER              │  <- Primary button, clickable
└─────────────────────────────────┘

State 2: Already Applied
┌─────────────────────────────────┐
│    ✓ Candidature envoyée        │  <- Disabled, success style
└─────────────────────────────────┘

State 3: Mission Closed
┌─────────────────────────────────┐
│  Mission fermée aux candidatures │  <- Disabled, muted style
└─────────────────────────────────┘
```

### Checking if Face Has Applied

Update the mission detail API to include candidature status:

```php
// In Face\MissionController show method
$face = $request->user()->userable;
$candidature = $mission->candidatures()->where('face_id', $face->id)->first();

return response()->json([
    'data' => new MissionResource($mission),
    'candidature' => $candidature ? new CandidatureResource($candidature) : null,
]);
```

Or add to MissionResource:

```php
// MissionResource toArray()
'user_candidature' => $this->whenLoaded('candidatures', function () {
    $faceId = request()->user()?->userable_id;
    $candidature = $this->candidatures->firstWhere('face_id', $faceId);
    return $candidature ? new CandidatureResource($candidature) : null;
}),
```

### Existing Files Reference

**Backend files to create:**
- `app/Http/Controllers/Api/V1/Face/CandidatureController.php`
- `app/Http/Requests/Face/StoreCandidatureRequest.php`
- `app/Http/Resources/CandidatureResource.php`
- `tests/Feature/Candidature/FaceApplyToMissionTest.php`

**Backend files to modify:**
- `routes/api/face.php` - Add apply route
- `app/Http/Controllers/Api/V1/Face/MissionController.php` - Add candidature status to show

**Frontend files to create:**
- `src/features/candidature/` directory structure
- `src/features/candidature/components/ApplyToMissionModal.vue`
- `src/features/candidature/composables/useApplyToMission.ts`
- `src/features/candidature/composables/index.ts`
- `src/features/candidature/services/candidatureApi.ts`
- `src/types/candidature.ts`

**Frontend files to modify:**
- `src/features/mission/services/faceMissionApi.ts` - Or create new candidatureApi
- `src/pages/face/mission/FaceMissionDetailPage.vue` - Add modal and button state logic

### Previous Story Intelligence

**From Story 5-10 (FaceMissionDetailPage):**
- "Postuler" button already exists but has placeholder `handleApply` function
- Button is shown when `mission.is_accepting_candidatures` is true
- Page uses `useMissionDetail` composable for data fetching

**From Story 6-1 (Candidature Schema):**
- `Candidature` model with `face_id`, `mission_id`, `message_motivation`, `status`
- `CandidatureStatus` enum with `Pending` state
- `allowsChatAccess()` helper for future stories
- Unique constraint on `(face_id, mission_id)` - must handle gracefully

### Git Intelligence (Recent Commits)

```
0f7c57c docs: complete story 6-1 candidature database schema
bd138be test(candidature): add schema and relationship tests
e2e3723 feat(candidature): add database schema and model
```

Story 6-1 commits show the Candidature model and enum are ready.

### Test Scenarios

| Scenario | Input | Expected |
|----------|-------|----------|
| Apply with motivation | POST + message | 201, candidature created |
| Apply without motivation | POST, no message | 201, candidature created |
| Apply to draft mission | POST to draft | 404 |
| Apply to closed mission | POST to closed | 422, MISSION_CLOSED |
| Apply past deadline | POST after deadline | 422, MISSION_CLOSED |
| Apply twice | POST same mission | 422, ALREADY_APPLIED |
| Producer tries to apply | Producer token | 403 |
| Unauthenticated | No token | 401 |
| Message too long | >2000 chars | 422, validation error |
| View applied mission | GET detail | has_applied: true, candidature data |

### Dependencies

- **Depends on**: Story 5-10 (FaceMissionDetailPage), Story 6-1 (Candidature schema)
- **Blocks**: Story 6-3 (Face View My Candidatures), Story 6-4 (Producer View Candidatures)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.2 - Face Apply to Mission, FR34]
- [Source: _bmad-output/project-context.md#API Response Format]
- [Source: backend/app/Models/Candidature.php - Candidature model]
- [Source: backend/app/Enums/CandidatureStatus.php - Status enum]
- [Source: backend/app/Models/Mission.php - isAcceptingCandidatures() method]
- [Source: frontend/src/pages/face/mission/FaceMissionDetailPage.vue - Page to update]
- [Source: _bmad-output/implementation-artifacts/5-10-face-view-mission-detail.md - Previous story patterns]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

None - all tests passed on first run

### Completion Notes List

1. **Backend Implementation Complete**: Created CandidatureController, StoreCandidatureRequest, CandidatureResource, and added route with throttle middleware
2. **Mission Detail Enhanced**: Updated Face\MissionController to return candidature data in mission show response
3. **16 Backend Tests**: Full test coverage for all acceptance criteria including edge cases (401, 403, 404, 422 errors)
4. **Frontend Feature Module**: Created `features/candidature/` with types, API service, composable, and modal component
5. **ApplyToMissionModal**: Character counter, loading/success/error states, auto-close on success
6. **FaceMissionDetailPage Integration**: 3 button states (can apply, already applied, mission closed)
7. **TypeScript**: All types properly defined, `vue-tsc --noEmit` passes
8. **All 518 backend tests pass** (no regressions)

### Code Review Fixes Applied

1. **M1 Fixed**: Created `frontend/src/features/candidature/index.ts` barrel export for consistency with other features
2. **M2 Fixed**: Added 401 error handling in `useApplyToMission.ts` with message "Veuillez vous connecter pour postuler"
3. **M3 Fixed**: Added `prepareForValidation()` in `StoreCandidatureRequest.php` to convert empty string motivation to null
4. **M4 Fixed**: Added test `test_empty_string_motivation_is_stored_as_null()` for edge case coverage
5. **L1 Fixed**: Removed unused `MissionDetailWithCandidature` type from types/index.ts

### File List

**Backend Files Created:**
- `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php`
- `backend/app/Http/Requests/Face/StoreCandidatureRequest.php`
- `backend/app/Http/Resources/CandidatureResource.php`
- `backend/tests/Feature/Candidature/FaceApplyToMissionTest.php`

**Backend Files Modified:**
- `backend/routes/api/face.php` - Added apply route
- `backend/app/Http/Controllers/Api/V1/Face/MissionController.php` - Added candidature to show response

**Frontend Files Created:**
- `frontend/src/features/candidature/index.ts` (barrel export - added in code review)
- `frontend/src/features/candidature/types/index.ts`
- `frontend/src/features/candidature/services/candidatureApi.ts`
- `frontend/src/features/candidature/services/index.ts`
- `frontend/src/features/candidature/composables/useApplyToMission.ts`
- `frontend/src/features/candidature/composables/index.ts`
- `frontend/src/features/candidature/components/ApplyToMissionModal.vue`
- `frontend/src/features/candidature/components/index.ts`

**Frontend Files Modified:**
- `frontend/src/features/mission/types/mission.ts` - Added MissionCandidature type and updated MissionResponse
- `frontend/src/features/mission/composables/useMissionDetail.ts` - Added candidature ref and setCandidature method
- `frontend/src/pages/face/mission/FaceMissionDetailPage.vue` - Integrated modal and button states
