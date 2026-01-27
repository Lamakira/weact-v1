# Story 5.5: Producer Missions List

Status: done

## Story

As a **Producer**,
I want **to see all my missions with their statuses**,
so that **I can manage my casting calls effectively**.

## Acceptance Criteria

1. **Given** I am logged in as a Producer **And** I navigate to "Mes missions" **When** the page loads **Then** I see a list of all my missions

2. **Given** I am on my missions list **When** I view each mission card **Then** I see: titre, date_tournage, budget (formatted XOF), status badge, nombre de candidatures (0 for now - candidatures model doesn't exist yet)

3. **Given** I have missions with different statuses **When** I view the list **Then** missions are sorted by most recent first (created_at DESC)

4. **Given** I click on a mission **When** the action is triggered **Then** I can navigate to the edit page for that mission

5. **Given** I click the delete button on a mission **When** the confirmation is confirmed **Then** the mission is deleted **And** the list refreshes to show updated state

6. **Given** I have no missions **When** I view the missions list page **Then** I see an empty state message encouraging me to publish a mission **And** a CTA button to create a new mission

7. **Given** I am not logged in **When** I try to access the missions list page **Then** I am redirected to the login page

8. **Given** I am logged in as a Face (not Producer) **When** I try to access the missions list page **Then** I am redirected to my Face dashboard

9. **Given** the API request to fetch missions fails **When** the error occurs **Then** I see an error message **And** I can retry

10. **Given** I am on the missions list **When** I click "Publier une mission" **Then** I navigate to the mission creation page

**(FR28)**

## Tasks / Subtasks

- [x] Task 1: Add index method to MissionController (AC: #1, #2, #3, #7)
  - [x] Add `index(): JsonResponse` method to `app/Http/Controllers/Api/V1/Producer/MissionController.php`
  - [x] Query missions for authenticated producer only
  - [x] Order by created_at DESC
  - [x] Eager load producer relationship (for consistency with show/update responses)
  - [x] Return MissionCollection (or array of MissionResource)
  - [x] Return envelope format `{ data: [...], message: "..." }`

- [x] Task 2: Add GET route for missions list (AC: #7)
  - [x] Add `GET /v1/producer/missions` route to `routes/api/producer.php`
  - [x] Route protected by `auth:sanctum` middleware
  - [x] Note: Policy authorization not needed since we filter by producer_id

- [x] Task 3: Create backend feature tests (AC: #1-#3, #7, #8)
  - [x] Add tests to existing test file or create `tests/Feature/Mission/ProducerMissionsListTest.php`
  - [x] Test successful list retrieval (producer sees only own missions)
  - [x] Test empty list response
  - [x] Test ordering (most recent first)
  - [x] Test unauthorized access (not logged in → 401)
  - [x] Test Face user cannot access (→ 403)
  - [x] Test response format includes expected fields

- [x] Task 4: Add getMissions method to missionApi (frontend) (AC: #1)
  - [x] Add `getMissions(): Promise<MissionsListResponse>` to `missionApi.ts`
  - [x] Define `MissionsListResponse` type

- [x] Task 5: Create useMissionsList composable (AC: #1, #9)
  - [x] Create `frontend/src/features/mission/composables/useMissionsList.ts`
  - [x] Manage list state (missions, isLoading, error)
  - [x] Implement fetchMissions method
  - [x] Implement refreshMissions method for after delete
  - [x] Handle API errors gracefully

- [x] Task 6: Create MissionCard component (USE GEMINI MCP) (AC: #2, #4, #5)
  - [x] Use `create_frontend` MCP tool
  - [x] Display: titre, date_tournage (formatted French), budget (formatted XOF), status badge (color-coded)
  - [x] Display candidatures count badge (0 for now - placeholder)
  - [x] Action buttons: "Modifier" (navigates to edit), "Supprimer" (triggers delete dialog)
  - [x] Match existing design system (weact color #198496, shadows, rounded corners)
  - [x] Responsive: stack on mobile, horizontal on desktop

- [x] Task 7: Create MissionsListPage.vue (USE GEMINI MCP) (AC: #1, #6, #10)
  - [x] Use `create_frontend` MCP tool
  - [x] Page header with "Mes missions" title and "Publier une mission" button
  - [x] Grid/list of MissionCard components
  - [x] Loading state while fetching
  - [x] Empty state with illustration and CTA
  - [x] Error state with retry button
  - [x] Integrate DeleteMissionDialog (from Story 5-4)

- [x] Task 8: Add frontend route (AC: #7, #8)
  - [x] Add `/producer/missions` route to `frontend/src/router/index.ts`
  - [x] Route protected by auth guard (Producer role)
  - [x] Lazy-load the page component

- [x] Task 9: Update Producer Dashboard to link to missions list
  - [x] Add "Mes missions" card/link to producer dashboard if not already present
  - [x] Or update existing "Publier une mission" section to include link to list

- [x] Task 10: Export composable and component
  - [x] Export `useMissionsList` from `composables/index.ts`
  - [x] Export `MissionCard` from `components/index.ts`

- [x] Task 11: Update EditMissionPage redirect
  - [x] After successful delete in EditMissionPage, redirect to `/producer/missions` (not dashboard)
  - [x] This provides better UX flow

- [x] Task 12: Type checking and tests verification
  - [x] TypeScript type checking passes
  - [x] Backend tests pass
  - [x] Manual frontend verification

## Dev Notes

### 🎨 CRITICAL: Frontend UI Implementation with Gemini MCP

**All frontend UI work MUST be done exclusively using the Gemini MCP tools:**

- **`create_frontend`**: For creating MissionCard and MissionsListPage components
- **`modify_frontend`**: For updating existing components

**You CAN write yourself:**
- TypeScript composables (useMissionsList.ts) - logic only
- API service methods (missionApi.ts)
- Types and interfaces
- Non-UI logic code

### Architecture Patterns (from previous stories)

- **Controllers**: `app/Http/Controllers/Api/V1/{Domain}/` - Controllers organized by domain
- **Form Requests**: `app/Http/Requests/{Domain}/` - Validation with French messages
- **Services**: Business logic in Services, not Controllers
- **Policies**: Use Policies for resource authorization (not needed for index - filter by owner)
- **API Resources**: Use MissionResource for response transformation

### Database Schema Reference (from Story 5-1)

```php
// Mission table columns relevant for list display
$table->id();
$table->foreignId('producer_id')->constrained('producers')->cascadeOnDelete();
$table->string('titre', 255);
$table->date('date_tournage');
$table->unsignedInteger('budget');
$table->string('status', 20)->default('draft'); // draft, published, closed, completed
$table->timestamps();
```

### Enum Values Reference

**MissionStatus** (`App\Enums\MissionStatus`):
- `draft` - Brouillon (grey badge)
- `published` - Publiée (green badge)
- `closed` - Clôturée (orange badge)
- `completed` - Terminée (blue badge)

### API Endpoint Specification

```
GET /api/v1/producer/missions
Authorization: Bearer {token}

Response (Success - 200):
{
  "data": [
    {
      "id": 1,
      "titre": "Tournage publicitaire Coca-Cola",
      "description": "...",
      "date_tournage": "2026-02-15",
      "budget": 150000,
      "status": "published",
      "lieu": "Cotonou",
      "nombre_faces_voulu": 3,
      "candidatures_count": 0,  // Placeholder until Epic 6
      "created_at": "2026-01-26T10:00:00Z",
      "producer": {
        "id": 1,
        "type": "agency",
        "agency_name": "WeactProd"
      }
    }
  ],
  "message": "Missions récupérées avec succès"
}

Response (Empty - 200):
{
  "data": [],
  "message": "Aucune mission trouvée"
}
```

### Learnings from Previous Stories

1. **Story 5-3 (Edit Mission)**:
   - Use `MissionValidationRules` trait for shared validation
   - FormRequest for status checks returns proper French 422 errors
   - Remove redundant Gate calls when FormRequest handles authorization

2. **Story 5-4 (Delete Mission)**:
   - DeleteMissionDialog component already exists and can be reused
   - useDeleteMission composable handles deletion logic
   - Integrate delete into list by refreshing after successful deletion

3. **Story 5-2 (Publish Mission)**:
   - MissionForm component pattern established
   - Route protection with Producer guard
   - API envelope format `{ data, message }`

### Frontend Structure Reference

Existing files to reference/reuse:
- `features/mission/services/missionApi.ts` - Add getMissions method
- `features/mission/composables/index.ts` - Export new composable
- `features/mission/components/DeleteMissionDialog.vue` - Reuse for delete
- `features/mission/composables/useDeleteMission.ts` - Reuse delete logic

New files to create:
- `features/mission/composables/useMissionsList.ts`
- `features/mission/components/MissionCard.vue`
- `pages/producer/mission/MissionsListPage.vue` (or `MyMissionsPage.vue`)

### Status Badge Color Mapping

```typescript
const statusColors = {
  draft: 'bg-gray-100 text-gray-800',
  published: 'bg-green-100 text-green-800',
  closed: 'bg-orange-100 text-orange-800',
  completed: 'bg-blue-100 text-blue-800',
} as const;

const statusLabels = {
  draft: 'Brouillon',
  published: 'Publiée',
  closed: 'Clôturée',
  completed: 'Terminée',
} as const;
```

### Currency Formatting (XOF)

```typescript
function formatXOF(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}
// Example: formatXOF(150000) → "150 000 XOF"
```

### Date Formatting (French locale)

```typescript
function formatDateFr(isoDate: string): string {
  const date = new Date(isoDate);
  return new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(date);
}
// Example: formatDateFr("2026-02-15") → "15 février 2026"
```

### Empty State Design

The empty state should include:
- Illustration (optional, can use placeholder icon)
- Message: "Vous n'avez pas encore de missions"
- CTA Button: "Publier une mission" → navigates to `/producer/missions/publish`

### Candidatures Count Note

The Candidature model doesn't exist yet (Epic 6). For this story:
- Display `candidatures_count: 0` as placeholder
- The MissionResource may need a `candidatures_count` accessor that returns 0 for now
- When Epic 6 is implemented, update to use `$mission->candidatures()->count()`

### References

- [Source: _bmad-output/planning-artifacts/epics.md - Story 5.5]
- [Source: _bmad-output/project-context.md - API Response Format, Naming Conventions]
- [Source: docs/planning-artifacts/architecture.md - Frontend Architecture, Structure Patterns]
- [Source: backend/app/Http/Resources/MissionResource.php - Response transformation]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/MissionController.php - Controller pattern]
- [Source: frontend/src/features/mission/composables/useDeleteMission.ts - Composable pattern]
- [Source: frontend/src/features/mission/components/DeleteMissionDialog.vue - Dialog pattern]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

### Completion Notes List

- Backend implementation complete with 9 tests (39 assertions) in ProducerMissionsListTest.php
- All 394 backend tests pass (1747 assertions) - no regressions
- Frontend implementation complete with composable, API method, MissionCard component, and MissionsListPage
- TypeScript type checking passes
- Gemini MCP used successfully for MissionCard and MissionsListPage components
- Added "Mes missions" card to Producer Dashboard
- Updated EditMissionPage to redirect to missions list after delete
- Route protected by auth guard with Producer role check

### Code Review Fixes (Post-Review)

- Added useToast for success/error notifications on delete
- Fixed @delete event handler type mismatch (now properly receives ID and finds mission)
- Changed navigation to use named routes instead of string paths
- Added error toast display when delete fails

### File List

**Created:**
- `backend/tests/Feature/Mission/ProducerMissionsListTest.php` - Feature tests (9 tests)
- `frontend/src/features/mission/composables/useMissionsList.ts` - List composable
- `frontend/src/features/mission/components/MissionCard.vue` - Mission card component
- `frontend/src/pages/producer/mission/MissionsListPage.vue` - Missions list page

**Modified:**
- `backend/app/Http/Controllers/Api/V1/Producer/MissionController.php` - Added index method
- `backend/routes/api/producer.php` - Added GET /missions route
- `frontend/src/features/mission/types/mission.ts` - Added MissionsListResponse type
- `frontend/src/features/mission/services/missionApi.ts` - Added getMissions method
- `frontend/src/features/mission/composables/index.ts` - Export useMissionsList
- `frontend/src/features/mission/components/index.ts` - Export MissionCard
- `frontend/src/router/index.ts` - Added /producer/missions route
- `frontend/src/pages/dashboard/ProducerDashboardPage.vue` - Added "Mes missions" card
- `frontend/src/pages/producer/mission/EditMissionPage.vue` - Redirect to missions list after delete
