# Story 5.6b: Reopen Mission

Status: done

## Story

As a **Producer**,
I want **to reopen a closed mission to accept new candidatures again**,
so that **I can continue receiving applications if I need more candidates**.

## Acceptance Criteria

1. **Given** I am viewing my closed mission **When** I click "Réouvrir" **Then** the mission status changes to "published"

2. **Given** my mission status is "published" (after reopen) **When** a Face views it **Then** they can apply to the mission

3. **Given** I am viewing my reopened mission **When** I look at the mission details **Then** I see the status badge as "Publiée" (green)

4. **Given** I am viewing my draft mission **When** I try to reopen it **Then** I see an error "Seules les missions clôturées peuvent être réouvertes"

5. **Given** I am viewing my published mission **When** I try to reopen it **Then** I see an error "Cette mission est déjà publiée"

6. **Given** I am viewing my completed mission **When** I try to reopen it **Then** I see an error "Cette mission est terminée et ne peut pas être réouverte"

7. **Given** I am another Producer **When** I try to reopen a mission that doesn't belong to me **Then** I receive a 403 Forbidden error

8. **Given** I am not logged in **When** I try to reopen a mission **Then** I receive a 401 Unauthorized error

9. **Given** I am a Face user **When** I try to reopen a mission **Then** I receive a 403 Forbidden error

10. **Given** I successfully reopen a mission **When** the response is returned **Then** I receive a success message "Mission réouverte avec succès" **And** the updated mission data

## Tasks / Subtasks

- [x] Task 1: Add reopen method to MissionController (AC: #1, #3, #7, #8, #9, #10)
  - [x] Add `reopen(ReopenMissionRequest $request, Mission $mission): JsonResponse` method
  - [x] Authorization handled by FormRequest
  - [x] Use MissionService for business logic
  - [x] Return envelope format `{ data: MissionResource, message: "Mission réouverte avec succès" }`

- [x] Task 2: Create ReopenMissionRequest FormRequest (AC: #4, #5, #6, #7, #9)
  - [x] Create `app/Http/Requests/Mission/ReopenMissionRequest.php`
  - [x] `authorize()`: Check user is Producer AND owns the mission
  - [x] `withValidator()`: Check mission status is 'closed' (not draft, published, or completed)
  - [x] Return proper French error messages for 422 validation errors

- [x] Task 3: Add reopenMission method to MissionService (AC: #1)
  - [x] Add `reopenMission(Mission $mission): Mission` method
  - [x] Update status to `MissionStatus::Published`
  - [x] Save and return updated mission

- [x] Task 4: Add POST route for reopen action (AC: #8)
  - [x] Add `POST /v1/producer/missions/{mission}/reopen` route to `routes/api/producer.php`
  - [x] Route protected by `auth:sanctum` middleware
  - [x] Use route model binding for `{mission}`

- [x] Task 5: Create backend feature tests (AC: #1-#10)
  - [x] Create `tests/Feature/Mission/ReopenMissionTest.php`
  - [x] Test successful reopen from closed status
  - [x] Test cannot reopen draft mission (422)
  - [x] Test cannot reopen already published mission (422)
  - [x] Test cannot reopen completed mission (422)
  - [x] Test ownership check (403)
  - [x] Test Face user cannot reopen (403)
  - [x] Test unauthenticated request (401)
  - [x] Test response format includes updated mission data
  - [x] Test status badge label is "Publiée"
  - [x] Test reopened mission is accepting candidatures

- [x] Task 6: Add reopenMission method to missionApi (frontend) (AC: #1)
  - [x] Add `reopenMission(id: number): Promise<MissionResponse>` to `missionApi.ts`

- [x] Task 7: Create useReopenMission composable (AC: #1, #10)
  - [x] Create `frontend/src/features/mission/composables/useReopenMission.ts`
  - [x] Manage reopening state (isReopening, error)
  - [x] Implement reopenMission method
  - [x] Return success/error result

- [x] Task 8: Create ReopenMissionDialog component (AC: #1)
  - [x] Create `frontend/src/features/mission/components/ReopenMissionDialog.vue`
  - [x] Confirmation dialog with green styling (matching published status)
  - [x] Show mission title and warning about accepting new candidatures
  - [x] Export from `components/index.ts`

- [x] Task 9: Add reopen action to MissionCard component (AC: #1, #3)
  - [x] Add "Réouvrir" button visible only when status is "closed"
  - [x] Button triggers confirmation dialog
  - [x] Green color to match published status badge

- [x] Task 10: Add reopen action to MissionsListPage (AC: #1)
  - [x] Handle reopen event from MissionCard
  - [x] Show confirmation dialog
  - [x] Show success toast on reopen
  - [x] Refresh mission list after reopen

- [x] Task 11: Export composable
  - [x] Export `useReopenMission` from `composables/index.ts`

- [x] Task 12: Type checking and tests verification
  - [x] TypeScript type checking passes
  - [x] Backend tests pass (416 tests, 1827 assertions)
  - [x] Manual frontend verification

## Dev Notes

### Architecture Patterns (from Story 5-6)

- **Controllers**: `app/Http/Controllers/Api/V1/{Domain}/` - Controllers organized by domain
- **Form Requests**: `app/Http/Requests/{Domain}/` - Validation with French messages
- **Services**: Business logic in Services, not Controllers
- **API Resources**: Use MissionResource for response transformation

### Database Schema Reference

```php
// Mission table - relevant column for this story
$table->string('status', 20)->default('draft'); // draft, published, closed, completed
```

### Enum Values Reference

**MissionStatus** (`App\Enums\MissionStatus`):
- `draft` - Brouillon (grey badge) - CANNOT be reopened
- `published` - Publiée (green badge) - Target state / CANNOT be reopened
- `closed` - Clôturée (orange badge) - CAN be reopened
- `completed` - Terminée (blue badge) - CANNOT be reopened

### API Endpoint Specification

```
POST /api/v1/producer/missions/{id}/reopen
Authorization: Bearer {token}

Request Body: (none required)

Response (Success - 200):
{
  "data": {
    "id": 1,
    "titre": "Tournage publicitaire Coca-Cola",
    "status": "published",
    "status_label": "Publiée",
    "is_accepting_candidatures": true,
    ... // all mission fields
  },
  "message": "Mission réouverte avec succès"
}

Response (Validation Error - 422):
{
  "message": "Seules les missions clôturées peuvent être réouvertes",
  "errors": {
    "status": ["Seules les missions clôturées peuvent être réouvertes"]
  }
}

Response (Forbidden - 403):
{
  "message": "Cette action n'est pas autorisée"
}
```

### Status Transition Rules (Updated)

```
                    close()              reopen()
    published ◄──────────────► closed
        │                         │
        │                         │
        │    mark_complete()      │    mark_complete()
        └─────────────────────────┴──────────► completed
```

**Valid reopen transition:**
- `closed` → `published` ✅

**Invalid reopen transitions:**
- `draft` → `published` ❌ (use publish action instead)
- `published` → `published` ❌ (already published)
- `completed` → `published` ❌ (completed is final)

### Button Visibility Rules on MissionCard (Updated)

| Status | Edit | Delete | Close | Reopen | Mark Complete |
|--------|------|--------|-------|--------|---------------|
| draft | ✅ | ✅ | ❌ | ❌ | ❌ |
| published | ✅ | ✅ | ✅ | ❌ | ❌ |
| closed | ❌ | ❌ | ❌ | ✅ | ✅ (Story 5-7) |
| completed | ❌ | ❌ | ❌ | ❌ | ❌ |

### Frontend Structure Reference

Existing files to modify:
- `features/mission/services/missionApi.ts` - Add reopenMission method
- `features/mission/components/MissionCard.vue` - Add reopen button (status-conditional)
- `features/mission/composables/index.ts` - Export new composable
- `features/mission/components/index.ts` - Export new dialog
- `pages/producer/mission/MissionsListPage.vue` - Handle reopen event

New files to create:
- `features/mission/composables/useReopenMission.ts`
- `features/mission/components/ReopenMissionDialog.vue`

### Backend Files Reference

Existing files to modify:
- `app/Http/Controllers/Api/V1/Producer/MissionController.php` - Add reopen method
- `app/Services/MissionService.php` - Add reopenMission method
- `routes/api/producer.php` - Add POST route

New files to create:
- `app/Http/Requests/Mission/ReopenMissionRequest.php`
- `tests/Feature/Mission/ReopenMissionTest.php`

### References

- [Source: _bmad-output/implementation-artifacts/5-6-close-mission.md - Close Mission patterns]
- [Source: backend/app/Http/Requests/Mission/CloseMissionRequest.php - FormRequest pattern]
- [Source: frontend/src/features/mission/components/CloseMissionDialog.vue - Dialog pattern]
- [Source: frontend/src/features/mission/composables/useCloseMission.ts - Composable pattern]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

### Completion Notes List

- Backend implementation complete with 11 tests (41 assertions) in ReopenMissionTest.php
- All 416 backend tests pass (1827 assertions) - no regressions
- Created ReopenMissionRequest FormRequest with authorization and status validation
- Added reopenMission method to MissionService
- Added POST /producer/missions/{mission}/reopen route
- Frontend composable useReopenMission created following existing patterns
- ReopenMissionDialog with green styling matching "Publiée" status
- MissionCard updated with "Réouvrir" button visible only for closed missions
- MissionsListPage handles reopen event with confirmation dialog and toast notifications
- TypeScript type checking passes

**Code Review Fixes Applied:**
- Added Escape key handler to ReopenMissionDialog.vue for keyboard accessibility
- Added Escape key handler to CloseMissionDialog.vue for consistency
- Removed unused `use App\Enums\MissionStatus` import from ReopenMissionTest.php

### File List

**Created:**
- `backend/app/Http/Requests/Mission/ReopenMissionRequest.php` - FormRequest with authorization and status validation
- `backend/tests/Feature/Mission/ReopenMissionTest.php` - 11 feature tests
- `frontend/src/features/mission/composables/useReopenMission.ts` - Reopen composable
- `frontend/src/features/mission/components/ReopenMissionDialog.vue` - Confirmation dialog with green styling

**Modified:**
- `backend/app/Http/Controllers/Api/V1/Producer/MissionController.php` - Added reopen method
- `backend/app/Services/MissionService.php` - Added reopenMission method
- `backend/routes/api/producer.php` - Added POST /missions/{mission}/reopen route
- `frontend/src/features/mission/services/missionApi.ts` - Added reopenMission method
- `frontend/src/features/mission/composables/index.ts` - Export useReopenMission
- `frontend/src/features/mission/components/index.ts` - Export ReopenMissionDialog
- `frontend/src/features/mission/components/MissionCard.vue` - Added reopen button, canReopen computed
- `frontend/src/pages/producer/mission/MissionsListPage.vue` - Handle reopen event with dialog
