# Story 5.6: Close Mission

Status: ready-for-dev

## Story

As a **Producer**,
I want **to close a mission to stop accepting new candidatures**,
so that **I can focus on reviewing existing applicants**.

## Acceptance Criteria

1. **Given** I am viewing my published mission **When** I click "Clôturer les candidatures" **Then** the mission status changes to "closed"

2. **Given** my mission status is "closed" **When** a Face tries to apply **Then** they see an error message "Cette mission n'accepte plus de candidatures"

3. **Given** I am viewing my closed mission **When** I look at the mission details **Then** I see the status badge as "Clôturée" (orange)

4. **Given** I am viewing my draft mission **When** I try to close it **Then** I see an error "Seules les missions publiées peuvent être clôturées"

5. **Given** I am viewing my already closed mission **When** I try to close it again **Then** I see an error "Cette mission est déjà clôturée"

6. **Given** I am viewing my completed mission **When** I try to close it **Then** I see an error "Cette mission est déjà terminée"

7. **Given** I am another Producer **When** I try to close a mission that doesn't belong to me **Then** I receive a 403 Forbidden error

8. **Given** I am not logged in **When** I try to close a mission **Then** I receive a 401 Unauthorized error

9. **Given** I am a Face user **When** I try to close a mission **Then** I receive a 403 Forbidden error

10. **Given** I successfully close a mission **When** the response is returned **Then** I receive a success message "Mission clôturée avec succès" **And** the updated mission data

**(FR29)**

## Tasks / Subtasks

- [ ] Task 1: Add close method to MissionController (AC: #1, #3, #7, #8, #9, #10)
  - [ ] Add `close(CloseMissionRequest $request, Mission $mission): JsonResponse` method
  - [ ] Authorization handled by FormRequest
  - [ ] Use MissionService for business logic
  - [ ] Return envelope format `{ data: MissionResource, message: "Mission clôturée avec succès" }`

- [ ] Task 2: Create CloseMissionRequest FormRequest (AC: #4, #5, #6, #7, #9)
  - [ ] Create `app/Http/Requests/Mission/CloseMissionRequest.php`
  - [ ] `authorize()`: Check user is Producer AND owns the mission
  - [ ] `withValidator()`: Check mission status is 'published' (not draft, closed, or completed)
  - [ ] Return proper French error messages for 422 validation errors

- [ ] Task 3: Add closeMission method to MissionService (AC: #1)
  - [ ] Add `closeMission(Mission $mission): Mission` method
  - [ ] Update status to `MissionStatus::Closed`
  - [ ] Save and return updated mission

- [ ] Task 4: Add POST route for close action (AC: #8)
  - [ ] Add `POST /v1/producer/missions/{mission}/close` route to `routes/api/producer.php`
  - [ ] Route protected by `auth:sanctum` middleware
  - [ ] Use route model binding for `{mission}`

- [ ] Task 5: Create backend feature tests (AC: #1-#10)
  - [ ] Create `tests/Feature/Mission/CloseMissionTest.php`
  - [ ] Test successful close from published status
  - [ ] Test cannot close draft mission (422)
  - [ ] Test cannot close already closed mission (422)
  - [ ] Test cannot close completed mission (422)
  - [ ] Test ownership check (403)
  - [ ] Test Face user cannot close (403)
  - [ ] Test unauthenticated request (401)
  - [ ] Test response format includes updated mission data
  - [ ] Test status badge label is "Clôturée"

- [ ] Task 6: Add closeMission method to missionApi (frontend) (AC: #1)
  - [ ] Add `closeMission(id: number): Promise<MissionResponse>` to `missionApi.ts`

- [ ] Task 7: Add close action to MissionCard component (AC: #1, #3)
  - [ ] Add "Clôturer" button visible only when status is "published"
  - [ ] Button triggers confirmation dialog or direct action
  - [ ] On success, emit event to refresh list
  - [ ] Use `modify_frontend` MCP tool to update MissionCard.vue

- [ ] Task 8: Create useCloseMission composable (AC: #1, #10)
  - [ ] Create `frontend/src/features/mission/composables/useCloseMission.ts`
  - [ ] Manage closing state (isClosing, error)
  - [ ] Implement closeMission method
  - [ ] Return success/error result

- [ ] Task 9: Add close action to MissionsListPage (AC: #1)
  - [ ] Handle close event from MissionCard
  - [ ] Show success toast on close
  - [ ] Refresh mission list after close
  - [ ] Use `modify_frontend` MCP tool if UI changes needed

- [ ] Task 10: Export composable
  - [ ] Export `useCloseMission` from `composables/index.ts`

- [ ] Task 11: Type checking and tests verification
  - [ ] TypeScript type checking passes
  - [ ] Backend tests pass
  - [ ] Manual frontend verification

## Dev Notes

### 🎨 CRITICAL: Frontend UI Implementation with Gemini MCP

**All frontend UI work MUST be done exclusively using the Gemini MCP tools:**

- **`modify_frontend`**: For updating MissionCard and MissionsListPage components

**You CAN write yourself:**
- TypeScript composables (useCloseMission.ts) - logic only
- API service methods (missionApi.ts)
- Types and interfaces
- Non-UI logic code

### Architecture Patterns (from previous stories)

- **Controllers**: `app/Http/Controllers/Api/V1/{Domain}/` - Controllers organized by domain
- **Form Requests**: `app/Http/Requests/{Domain}/` - Validation with French messages
- **Services**: Business logic in Services, not Controllers
- **Policies**: Authorization in FormRequest for action-specific checks (like close)
- **API Resources**: Use MissionResource for response transformation

### Database Schema Reference (from Story 5-1)

```php
// Mission table - relevant column for this story
$table->string('status', 20)->default('draft'); // draft, published, closed, completed
```

### Enum Values Reference

**MissionStatus** (`App\Enums\MissionStatus`):
- `draft` - Brouillon (grey badge) - CANNOT be closed
- `published` - Publiée (green badge) - CAN be closed
- `closed` - Clôturée (orange badge) - Target state
- `completed` - Terminée (blue badge) - CANNOT be closed

### API Endpoint Specification

```
POST /api/v1/producer/missions/{id}/close
Authorization: Bearer {token}

Request Body: (none required)

Response (Success - 200):
{
  "data": {
    "id": 1,
    "titre": "Tournage publicitaire Coca-Cola",
    "status": "closed",
    "status_label": "Clôturée",
    "is_accepting_candidatures": false,
    ... // all mission fields
  },
  "message": "Mission clôturée avec succès"
}

Response (Validation Error - 422):
{
  "message": "Seules les missions publiées peuvent être clôturées",
  "errors": {
    "status": ["Seules les missions publiées peuvent être clôturées"]
  }
}

Response (Forbidden - 403):
{
  "message": "Cette action n'est pas autorisée"
}
```

### Learnings from Previous Stories

1. **Story 5-3 (Edit Mission)**:
   - FormRequest handles both authorization AND status validation
   - Use `withValidator()` for status-based validation with custom French messages
   - Remove redundant Gate/Policy calls when FormRequest handles it

2. **Story 5-4 (Delete Mission)**:
   - Pattern for action-specific FormRequests (DeleteMissionRequest)
   - Status validation in FormRequest returns 422 with French message

3. **Story 5-5 (Missions List)**:
   - MissionCard component shows status badges with color coding
   - Actions can be conditionally shown based on mission status
   - useToast for success/error notifications

4. **Security Fix (Recent)**:
   - Producer routes must check ownership
   - Show method now requires ownership check

### Frontend Structure Reference

Existing files to modify:
- `features/mission/services/missionApi.ts` - Add closeMission method
- `features/mission/components/MissionCard.vue` - Add close button (status-conditional)
- `features/mission/composables/index.ts` - Export new composable
- `pages/producer/mission/MissionsListPage.vue` - Handle close event

New files to create:
- `features/mission/composables/useCloseMission.ts`

### Backend Files Reference

Existing files to modify:
- `app/Http/Controllers/Api/V1/Producer/MissionController.php` - Add close method
- `app/Services/MissionService.php` - Add closeMission method
- `routes/api/producer.php` - Add POST route

New files to create:
- `app/Http/Requests/Mission/CloseMissionRequest.php`
- `tests/Feature/Mission/CloseMissionTest.php`

### Status Transition Rules

```
                    close()
    published ──────────────► closed
        │                        │
        │                        │
        │    mark_complete()     │    mark_complete()
        └────────────────────────┴──────────► completed
```

**Valid close transition:**
- `published` → `closed` ✅

**Invalid close transitions:**
- `draft` → `closed` ❌ (must publish first)
- `closed` → `closed` ❌ (already closed)
- `completed` → `closed` ❌ (already completed)

### Button Visibility Rules on MissionCard

| Status | Edit | Delete | Close | Mark Complete |
|--------|------|--------|-------|---------------|
| draft | ✅ | ✅ | ❌ | ❌ |
| published | ✅ | ✅ | ✅ | ❌ |
| closed | ❌ | ❌ | ❌ | ✅ (Story 5-7) |
| completed | ❌ | ❌ | ❌ | ❌ |

### MissionResource Reference

The `is_accepting_candidatures` field is already computed in MissionResource:
```php
'is_accepting_candidatures' => $this->isAcceptingCandidatures(),
```

This returns `true` only if status is `published`. When closed, it returns `false`.

### References

- [Source: _bmad-output/planning-artifacts/epics.md - Story 5.6]
- [Source: _bmad-output/project-context.md - API Response Format, Naming Conventions]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/MissionController.php - Controller pattern]
- [Source: backend/app/Http/Requests/Mission/DeleteMissionRequest.php - FormRequest pattern]
- [Source: backend/app/Services/MissionService.php - Service pattern]
- [Source: frontend/src/features/mission/components/MissionCard.vue - Card component]
- [Source: frontend/src/features/mission/composables/useDeleteMission.ts - Composable pattern]

## Dev Agent Record

### Agent Model Used

{{agent_model_name_version}}

### Debug Log References

### Completion Notes List

### File List
