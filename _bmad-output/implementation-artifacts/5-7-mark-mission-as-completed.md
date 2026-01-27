# Story 5.7: Mark Mission as Completed

Status: done

## Story

As a **Producer**,
I want **to mark a mission as completed after the work is done**,
so that **the workflow is properly concluded and ratings can be submitted**.

## Acceptance Criteria

1. **Given** I am viewing my published or closed mission with at least one accepted candidature **When** I click "Marquer comme terminée" **Then** the mission status changes to "completed"

2. **Given** my mission status is "completed" **When** the page displays **Then** I see the status badge as "Terminée" (blue badge)

3. **Given** I am viewing my completed mission **When** the rating system checks **Then** rating is now enabled for this mission (prerequisite for Epic 8)

4. **Given** I am viewing my draft mission **When** I try to mark it as completed **Then** I see an error "Seules les missions publiées ou clôturées peuvent être marquées comme terminées"

5. **Given** I am viewing my published/closed mission with NO accepted candidatures **When** I try to mark it as completed **Then** I see an error "Cette mission n'a aucune candidature acceptée"

6. **Given** I am viewing my already completed mission **When** I try to mark it as completed again **Then** I see an error "Cette mission est déjà terminée"

7. **Given** I am another Producer **When** I try to mark a mission that doesn't belong to me as completed **Then** I receive a 403 Forbidden error

8. **Given** I am not logged in **When** I try to mark a mission as completed **Then** I receive a 401 Unauthorized error

9. **Given** I am a Face user **When** I try to mark a mission as completed **Then** I receive a 403 Forbidden error

10. **Given** I successfully mark a mission as completed **When** the response is returned **Then** I receive a success message "Mission marquée comme terminée" **And** the updated mission data

11. **Given** my mission is completed **When** I try to close, reopen, edit or delete it **Then** I receive appropriate error messages (completed is a FINAL state)

**(FR30)**

## Tasks / Subtasks

- [x] Task 1: Add complete method to MissionController (AC: #1, #2, #7, #8, #9, #10)
  - [x] Add `complete(CompleteMissionRequest $request, Mission $mission): JsonResponse` method
  - [x] Authorization handled by FormRequest
  - [x] Use MissionService for business logic
  - [x] Return envelope format `{ data: MissionResource, message: "Mission marquée comme terminée" }`

- [x] Task 2: Create CompleteMissionRequest FormRequest (AC: #4, #5, #6, #7, #9)
  - [x] Create `app/Http/Requests/Mission/CompleteMissionRequest.php`
  - [x] `authorize()`: Check user is Producer AND owns the mission
  - [x] `withValidator()`: Check mission status is 'published' or 'closed' (not draft or completed)
  - [x] `withValidator()`: Check mission has at least one accepted/confirmed candidature (TODO: stubbed until Epic 6)
  - [x] Return proper French error messages for 422 validation errors

- [x] Task 3: Add completeMission method to MissionService (AC: #1)
  - [x] Add `completeMission(Mission $mission): Mission` method
  - [x] Update status to `MissionStatus::Completed`
  - [x] Save and return updated mission

- [x] Task 4: Add POST route for complete action (AC: #8)
  - [x] Add `POST /v1/producer/missions/{mission}/complete` route to `routes/api/producer.php`
  - [x] Route protected by `auth:sanctum` middleware
  - [x] Use route model binding for `{mission}`

- [x] Task 5: Create backend feature tests (AC: #1-#11)
  - [x] Create `tests/Feature/Mission/CompleteMissionTest.php`
  - [x] Test successful complete from published status (with accepted candidature)
  - [x] Test successful complete from closed status (with accepted candidature)
  - [x] Test cannot complete draft mission (422)
  - [x] Test cannot complete already completed mission (422)
  - [x] Test cannot complete mission with no accepted candidatures (422) - TODO: stubbed until Epic 6
  - [x] Test ownership check (403)
  - [x] Test Face user cannot complete (403)
  - [x] Test unauthenticated request (401)
  - [x] Test response format includes updated mission data
  - [x] Test status badge label is "Terminée"
  - [x] Test is_accepting_candidatures is false after completion

- [x] Task 6: Add completeMission method to missionApi (frontend) (AC: #1)
  - [x] Add `completeMission(id: number): Promise<MissionResponse>` to `missionApi.ts`

- [x] Task 7: Create useCompleteMission composable (AC: #1, #10)
  - [x] Create `frontend/src/features/mission/composables/useCompleteMission.ts`
  - [x] Manage completing state (isCompleting, error)
  - [x] Implement completeMission method
  - [x] Return success/error result

- [x] Task 8: Create CompleteMissionDialog component (AC: #1)
  - [x] Create `frontend/src/features/mission/components/CompleteMissionDialog.vue`
  - [x] Confirmation dialog with blue styling (matching "Terminée" status)
  - [x] Show mission title and warning about enabling ratings
  - [x] Mention that this action is FINAL and cannot be undone
  - [x] Add Escape key handler for keyboard accessibility
  - [x] Export from `components/index.ts`

- [x] Task 9: Add complete action to MissionCard component (AC: #1, #2)
  - [x] Add "Marquer comme terminée" button visible only when status is "closed"
  - [x] Button triggers confirmation dialog
  - [x] Blue color to match "Terminée" status badge
  - [x] Button should also be visible on "published" status if mission has accepted candidatures (edge case) - deferred to Epic 6

- [x] Task 10: Add complete action to MissionsListPage (AC: #1)
  - [x] Handle complete event from MissionCard
  - [x] Show confirmation dialog
  - [x] Show success toast on complete
  - [x] Refresh mission list after complete

- [x] Task 11: Export composable
  - [x] Export `useCompleteMission` from `composables/index.ts`

- [x] Task 12: Type checking and tests verification
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (427 tests, 1866 assertions - no regressions)
  - [x] Manual frontend verification

## Dev Notes

### 🔥 CRITICAL: Completed is a FINAL State

Once a mission is marked as completed, it CANNOT be:
- Reopened
- Closed
- Edited
- Deleted

This is intentional because:
1. Ratings depend on completed status
2. Historical data integrity must be preserved
3. Business workflow has concluded

### Architecture Patterns (from Story 5-6, 5-6b)

- **Controllers**: `app/Http/Controllers/Api/V1/{Domain}/` - Controllers organized by domain
- **Form Requests**: `app/Http/Requests/{Domain}/` - Validation with French messages
- **Services**: Business logic in Services, not Controllers
- **API Resources**: Use MissionResource for response transformation
- **Confirmation Dialogs**: Always use confirmation dialogs for state-changing actions

### Database Schema Reference

```php
// Mission table - relevant column for this story
$table->string('status', 20)->default('draft'); // draft, published, closed, completed

// Candidature table - needed for validation
// Status enum: pending, accepted, confirmed, in_progress, completed, rejected
```

### Enum Values Reference

**MissionStatus** (`App\Enums\MissionStatus`):
- `draft` - Brouillon (grey badge) - CANNOT be completed
- `published` - Publiée (green badge) - CAN be completed (if has accepted candidatures)
- `closed` - Clôturée (orange badge) - CAN be completed (if has accepted candidatures)
- `completed` - Terminée (blue badge) - Target state / FINAL

**CandidatureStatus** (`App\Enums\CandidatureStatus`):
- `pending` - En attente
- `accepted` - Acceptée
- `confirmed` - Confirmée
- `in_progress` - En cours
- `completed` - Terminée
- `rejected` - Refusée

### API Endpoint Specification

```
POST /api/v1/producer/missions/{id}/complete
Authorization: Bearer {token}

Request Body: (none required)

Response (Success - 200):
{
  "data": {
    "id": 1,
    "titre": "Tournage publicitaire Coca-Cola",
    "status": "completed",
    "status_label": "Terminée",
    "is_accepting_candidatures": false,
    ... // all mission fields
  },
  "message": "Mission marquée comme terminée"
}

Response (Validation Error - 422 - Wrong Status):
{
  "message": "Seules les missions publiées ou clôturées peuvent être marquées comme terminées",
  "errors": {
    "status": ["Seules les missions publiées ou clôturées peuvent être marquées comme terminées"]
  }
}

Response (Validation Error - 422 - No Candidatures):
{
  "message": "Cette mission n'a aucune candidature acceptée",
  "errors": {
    "candidatures": ["Cette mission n'a aucune candidature acceptée"]
  }
}

Response (Forbidden - 403):
{
  "message": "Cette action n'est pas autorisée"
}
```

### Status Transition Rules (Final)

```
                    close()              reopen()
    published ◄──────────────► closed
        │                         │
        │                         │
        │    complete()           │    complete()
        └─────────────────────────┴──────────► completed (FINAL)
```

**Valid complete transitions:**
- `published` → `completed` ✅ (requires accepted candidatures)
- `closed` → `completed` ✅ (requires accepted candidatures)

**Invalid complete transitions:**
- `draft` → `completed` ❌ (must publish first)
- `completed` → `completed` ❌ (already completed)

**From completed (NO transitions allowed):**
- `completed` → `published` ❌ (cannot reopen)
- `completed` → `closed` ❌ (cannot close)
- `completed` → edit ❌ (cannot modify)
- `completed` → delete ❌ (cannot delete)

### Button Visibility Rules on MissionCard (Final)

| Status | Edit | Delete | Close | Reopen | Mark Complete |
|--------|------|--------|-------|--------|---------------|
| draft | ✅ | ✅ | ❌ | ❌ | ❌ |
| published | ✅ | ✅ | ✅ | ❌ | ⚠️ (if has accepted candidatures) |
| closed | ❌ | ❌ | ❌ | ✅ | ✅ |
| completed | ❌ | ❌ | ❌ | ❌ | ❌ |

**Note:** The "Mark Complete" button on published missions is an edge case - normally a Producer would close first, then complete. But the workflow allows direct completion if work has started.

### Candidature Check Logic

To determine if a mission has accepted candidatures:

```php
// In CompleteMissionRequest.php withValidator()
$hasAcceptedCandidatures = $mission->candidatures()
    ->whereIn('status', [
        CandidatureStatus::Accepted,
        CandidatureStatus::Confirmed,
        CandidatureStatus::InProgress,
        CandidatureStatus::Completed,
    ])
    ->exists();

if (!$hasAcceptedCandidatures) {
    $validator->errors()->add('candidatures', 'Cette mission n\'a aucune candidature acceptée');
}
```

### 🚨 Important: Candidatures Table Not Yet Created

The candidatures table is part of Epic 6 (Story 6-1). For now, the validation check for accepted candidatures may need to be:
1. **Option A**: Stub the check to always pass (remove validation temporarily)
2. **Option B**: Create a minimal candidatures migration with just the fields needed

**Recommended: Option A** - Stub the check and add a TODO comment. The validation will be properly implemented when Epic 6 is completed.

```php
// TODO: Uncomment when candidatures table exists (Epic 6)
// For now, allow completion without candidature check
// $hasAcceptedCandidatures = $mission->candidatures()...
```

### Frontend Structure Reference

Existing files to modify:
- `features/mission/services/missionApi.ts` - Add completeMission method
- `features/mission/components/MissionCard.vue` - Add complete button (status-conditional)
- `features/mission/composables/index.ts` - Export new composable
- `features/mission/components/index.ts` - Export new dialog
- `pages/producer/mission/MissionsListPage.vue` - Handle complete event

New files to create:
- `features/mission/composables/useCompleteMission.ts`
- `features/mission/components/CompleteMissionDialog.vue`

### Backend Files Reference

Existing files to modify:
- `app/Http/Controllers/Api/V1/Producer/MissionController.php` - Add complete method
- `app/Services/MissionService.php` - Add completeMission method
- `routes/api/producer.php` - Add POST route

New files to create:
- `app/Http/Requests/Mission/CompleteMissionRequest.php`
- `tests/Feature/Mission/CompleteMissionTest.php`

### Previous Story Intelligence (5-6, 5-6b)

**Patterns to follow:**
1. FormRequest handles authorization AND status validation
2. `withValidator()` for status-based validation with custom French messages
3. Service method updates status and returns fresh model
4. Confirmation dialog with color matching status badge
5. Escape key handler for keyboard accessibility
6. MissionsListPage manages dialog state and refresh

**Code patterns established:**
- Use `$mission->update(['status' => MissionStatus::Completed]);`
- Return `$mission->fresh();` after update
- Use envelope format `{ data: MissionResource, message: "..." }`

### Git Intelligence (Recent Commits)

```
d2298b6 feat(mission): add reopen mission feature for producers
246ffa2 feat(mission): add close mission feature for producers
```

These commits show the established pattern for status transition features.

### References

- [Source: _bmad-output/planning-artifacts/epics.md - Story 5.7, FR30]
- [Source: _bmad-output/implementation-artifacts/5-6-close-mission.md - Close patterns]
- [Source: _bmad-output/implementation-artifacts/5-6b-reopen-mission.md - Reopen patterns]
- [Source: docs/planning-artifacts/architecture.md - API patterns, naming conventions]
- [Source: backend/app/Http/Requests/Mission/CloseMissionRequest.php - FormRequest pattern]
- [Source: frontend/src/features/mission/components/CloseMissionDialog.vue - Dialog pattern]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

### Completion Notes List

- Backend implementation complete with 11 tests (39 assertions) in CompleteMissionTest.php
- All 427 backend tests pass (1866 assertions) - no regressions
- Created CompleteMissionRequest FormRequest with authorization and status validation
- Candidature check stubbed with TODO comment until Epic 6 creates the candidatures table
- Added completeMission method to MissionService
- Added POST /producer/missions/{mission}/complete route
- Frontend composable useCompleteMission created following existing patterns
- CompleteMissionDialog with blue styling matching "Terminée" status and warning about irreversibility
- MissionCard updated with "Terminer" button visible only for closed missions
- MissionsListPage handles complete event with confirmation dialog and toast notifications
- TypeScript type checking passes
- Dialog includes Escape key handler for keyboard accessibility

### File List

**Created:**
- `backend/app/Http/Requests/Mission/CompleteMissionRequest.php` - FormRequest with authorization and status validation
- `backend/tests/Feature/Mission/CompleteMissionTest.php` - 11 feature tests (39 assertions)
- `frontend/src/features/mission/composables/useCompleteMission.ts` - Complete composable
- `frontend/src/features/mission/components/CompleteMissionDialog.vue` - Confirmation dialog with blue styling

**Modified:**
- `backend/app/Http/Controllers/Api/V1/Producer/MissionController.php` - Added complete method
- `backend/app/Services/MissionService.php` - Added completeMission method
- `backend/routes/api/producer.php` - Added POST /missions/{mission}/complete route
- `frontend/src/features/mission/services/missionApi.ts` - Added completeMission method
- `frontend/src/features/mission/composables/index.ts` - Export useCompleteMission
- `frontend/src/features/mission/components/index.ts` - Export CompleteMissionDialog
- `frontend/src/features/mission/components/MissionCard.vue` - Added complete button, canComplete computed, CheckCircle2 icon
- `frontend/src/pages/producer/mission/MissionsListPage.vue` - Handle complete event with dialog
