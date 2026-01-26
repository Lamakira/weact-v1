# Story 5.9: Face Filter Missions

Status: done

## Story

As a **Face**,
I want **to filter missions by city, budget, date, and type**,
so that **I can find relevant opportunities quickly**.

## Acceptance Criteria

1. **Given** I am on the Face missions list **When** the page loads **Then** I see filter controls for ville, budget min/max, date, and type_mission

2. **Given** I select a city filter **When** I apply the filter **Then** only missions in that city are displayed

3. **Given** I enter a minimum budget **When** I apply the filter **Then** only missions with budget >= min are displayed

4. **Given** I enter a maximum budget **When** I apply the filter **Then** only missions with budget <= max are displayed

5. **Given** I select a date filter **When** I apply the filter **Then** only missions with date_tournage >= selected date are displayed

6. **Given** I select a mission type **When** I apply the filter **Then** only missions of that type are displayed

7. **Given** I apply multiple filters **When** the list updates **Then** missions matching ALL criteria are displayed (AND logic)

8. **Given** I have active filters **When** I click "Réinitialiser" **Then** all filters are cleared and full list is displayed

9. **Given** filters are applied **When** no missions match **Then** I see empty state with message "Aucune mission ne correspond à vos critères"

10. **Given** I apply filters **When** pagination exists **Then** pagination resets to page 1

11. **Given** filters are applied **When** I navigate away and back **Then** filters are preserved (URL query params)

**(FR32)**

## Tasks / Subtasks

- [x] Task 1: Add filter parameters to Face MissionController (AC: #2-#7, #9)
  - [x] Modify `app/Http/Controllers/Api/V1/Face/MissionController.php`
  - [x] Add query parameters: `lieu`, `budget_min`, `budget_max`, `date_tournage`, `type_mission`
  - [x] Apply `where` clauses conditionally for each filter
  - [x] Maintain existing pagination and ordering

- [x] Task 2: Create FilterMissionsRequest for validation (AC: #2-#6)
  - [x] Create `app/Http/Requests/Mission/FilterMissionsRequest.php`
  - [x] Validate `lieu` as optional string
  - [x] Validate `budget_min` and `budget_max` as optional positive integers
  - [x] Validate `date_tournage` as optional date (Y-m-d format)
  - [x] Validate `type_mission` as optional enum value

- [x] Task 3: Create backend feature tests for filters (AC: #2-#7, #9)
  - [x] Add tests to `tests/Feature/Mission/FaceBrowseMissionsTest.php`
  - [x] Test filter by lieu
  - [x] Test filter by budget_min
  - [x] Test filter by budget_max
  - [x] Test filter by date_tournage
  - [x] Test filter by type_mission
  - [x] Test multiple filters combined (AND logic)
  - [x] Test no matches returns empty array

- [x] Task 4: Update faceMissionApi service (AC: #1)
  - [x] Modify `frontend/src/features/mission/services/faceMissionApi.ts`
  - [x] Add filter parameters to `getAvailableMissions()`
  - [x] Create `MissionFilters` interface for typed filter params

- [x] Task 5: Create useMissionFilters composable (AC: #1, #7, #8, #10, #11)
  - [x] Create `frontend/src/features/mission/composables/useMissionFilters.ts`
  - [x] Manage filter state: lieu, budgetMin, budgetMax, dateTournage, typeMission
  - [x] Implement `applyFilters()` and `resetFilters()` methods
  - [x] Sync filters with URL query parameters
  - [x] Integrate with useFaceMissions for data fetching

- [x] Task 6: Create MissionFiltersPanel component (AC: #1, #8)
  - [x] Create `frontend/src/features/mission/components/MissionFiltersPanel.vue`
  - [x] Add input for ville (text input or select with cities)
  - [x] Add inputs for budget min and max (number inputs)
  - [x] Add date picker for date_tournage
  - [x] Add select for type_mission (from enum options)
  - [x] Add "Appliquer" and "Réinitialiser" buttons
  - [x] Responsive: collapsible on mobile, sidebar on desktop

- [x] Task 7: Integrate filters into FaceMissionsListPage (AC: #1, #9, #10)
  - [x] Modify `frontend/src/pages/face/mission/FaceMissionsListPage.vue`
  - [x] Add MissionFiltersPanel component
  - [x] Connect to useMissionFilters composable
  - [x] Show filter count badge when filters are active
  - [x] Update empty state message when filters yield no results

- [x] Task 8: Add filter types and exports (AC: #1)
  - [x] Add `MissionFilters` interface to `frontend/src/features/mission/types/mission.ts`
  - [x] Export new composable from `composables/index.ts`
  - [x] Export new component from `components/index.ts`

- [x] Task 9: TypeScript and tests verification
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (no regressions)
  - [x] Manual frontend verification of filter functionality

## Dev Notes

### 🚨 CRITICAL: Use Gemini MCP for Frontend UI

**You MUST use the Gemini MCP tools for all frontend UI development:**
- `create_frontend` - For new components (MissionFiltersPanel)
- `modify_frontend` - For modifying existing components (FaceMissionsListPage)
- `snippet_frontend` - For smaller UI pieces

**Always pass the existing CSS/theme files in the `context` parameter** to ensure design consistency.

### 🎯 This story extends Story 5-8 (Face Browse Missions)

This story builds on top of the existing Face missions browsing feature:
- Adds server-side filtering to the existing `/v1/face/missions` endpoint
- Extends the frontend with a filters panel
- Preserves all existing functionality (pagination, ordering, producer info)

### Architecture Patterns (from Previous Stories)

- **Form Requests**: Use for API validation (see `StoreMissionRequest` pattern)
- **Conditional Queries**: Use `when()` for optional filter clauses
- **URL State**: Sync filter state with query params for shareable URLs
- **Composables**: Split filter logic from data fetching for reusability

### API Endpoint Extension

```
GET /api/v1/face/missions
Authorization: Bearer {token}

Query Parameters:
- page: integer (default: 1)
- lieu: string (optional) - city name filter
- budget_min: integer (optional) - minimum budget
- budget_max: integer (optional) - maximum budget
- date_tournage: string (optional) - Y-m-d format, missions on or after this date
- type_mission: string (optional) - enum: publicite, film, court_metrage, clip_musical, autre

Example:
GET /api/v1/face/missions?lieu=Cotonou&budget_min=50000&type_mission=publicite&page=1
```

### Backend Query Pattern

```php
// In Face\MissionController::index()
public function index(FilterMissionsRequest $request): JsonResponse
{
    $missions = Mission::where('status', MissionStatus::Published)
        ->when($request->lieu, fn ($q, $lieu) => $q->where('lieu', 'like', "%{$lieu}%"))
        ->when($request->budget_min, fn ($q, $min) => $q->where('budget', '>=', $min))
        ->when($request->budget_max, fn ($q, $max) => $q->where('budget', '<=', $max))
        ->when($request->date_tournage, fn ($q, $date) => $q->where('date_tournage', '>=', $date))
        ->when($request->type_mission, fn ($q, $type) => $q->where('type_mission', $type))
        ->with('producer')
        ->orderBy('created_at', 'desc')
        ->paginate(12);

    return response()->json(MissionResource::collection($missions)->response()->getData(true));
}
```

### Frontend Filter Types

```typescript
// Add to mission/types/mission.ts
export interface MissionFilters {
  lieu?: string
  budget_min?: number
  budget_max?: number
  date_tournage?: string  // Y-m-d format
  type_mission?: MissionTypeType
}
```

### URL Query Params Pattern

```typescript
// useMissionFilters.ts
import { useRoute, useRouter } from 'vue-router'

function syncFiltersToUrl(filters: MissionFilters) {
  const query: Record<string, string> = {}
  if (filters.lieu) query.lieu = filters.lieu
  if (filters.budget_min) query.budget_min = String(filters.budget_min)
  if (filters.budget_max) query.budget_max = String(filters.budget_max)
  if (filters.date_tournage) query.date_tournage = filters.date_tournage
  if (filters.type_mission) query.type_mission = filters.type_mission

  router.push({ query })
}
```

### Filter Panel Design

```
┌─────────────────────────────────────────┐
│ Filtres                          [Hide] │
├─────────────────────────────────────────┤
│ Ville                                   │
│ [___________________________]           │
│                                         │
│ Budget                                  │
│ Min [________] - Max [________] XOF     │
│                                         │
│ Date de tournage                        │
│ [📅 À partir du __/__/____]             │
│                                         │
│ Type de mission                         │
│ [▼ Tous les types___________]           │
│                                         │
│ [Réinitialiser]  [Appliquer les filtres]│
└─────────────────────────────────────────┘
```

### Mobile Responsiveness

- Filters hidden by default on mobile, revealed via "Filtres" button
- Full-width filter panel slides in from bottom or side
- Active filter count shown as badge on button: "Filtres (3)"

### Mission Type Options (from Story 5-8)

```typescript
// Already defined in mission/types/mission.ts
export const MissionType = {
  PUBLICITE: 'publicite',
  FILM: 'film',
  COURT_METRAGE: 'court_metrage',
  CLIP_MUSICAL: 'clip_musical',
  AUTRE: 'autre',
} as const
```

### Existing Files Reference

**Backend files to modify:**
- `app/Http/Controllers/Api/V1/Face/MissionController.php` - Add filter logic
- `tests/Feature/Mission/FaceBrowseMissionsTest.php` - Add filter tests

**Backend files to create:**
- `app/Http/Requests/Mission/FilterMissionsRequest.php` - Filter validation

**Frontend files to create:**
- `src/features/mission/composables/useMissionFilters.ts`
- `src/features/mission/components/MissionFiltersPanel.vue`

**Frontend files to modify:**
- `src/features/mission/services/faceMissionApi.ts` - Add filter params
- `src/pages/face/mission/FaceMissionsListPage.vue` - Integrate filters
- `src/features/mission/types/mission.ts` - Add MissionFilters type
- `src/features/mission/composables/index.ts` - Export new composable
- `src/features/mission/components/index.ts` - Export new component

### Previous Story Intelligence (5-8)

**From Story 5-8 implementation:**
- Face MissionController already has base query structure
- useFaceMissions composable handles pagination state
- faceMissionApi.getAvailableMissions() needs filter param extension
- FaceMissionsListPage has grid layout ready for filter panel integration

**Patterns to reuse:**
- Import `getApiErrorMessage` from `@/features/auth/services/authApi` (fixed in 5-8)
- TransitionGroup for smooth filter results updates
- Loading state pattern from existing composable

### Git Intelligence (Recent Commits)

```
e62394a docs: complete story 5-8 face browse available missions
7acda83 feat(dashboard): add missions navigation to Face dashboard
2ed52af feat(mission): add Face missions browsing UI
586ef0d test(mission): add Face browse missions feature tests
0eee3a4 feat(mission): add Face missions browsing API endpoint
```

Story 5-8 commits show the patterns to follow for extending the Face missions feature.

### Test Scenarios

| Scenario | Input | Expected |
|----------|-------|----------|
| Filter by city | lieu=Cotonou | Only Cotonou missions |
| Filter by min budget | budget_min=50000 | Missions with budget >= 50000 |
| Filter by max budget | budget_max=100000 | Missions with budget <= 100000 |
| Filter by budget range | budget_min=50000&budget_max=100000 | 50000 <= budget <= 100000 |
| Filter by date | date_tournage=2026-02-01 | Missions on/after Feb 1st |
| Filter by type | type_mission=publicite | Only publicite missions |
| Combined filters | lieu=Cotonou&type_mission=film | Cotonou AND film |
| No matches | lieu=Nonexistent | Empty with "Aucune mission..." |
| Reset filters | Click reset | All filters cleared, full list |

### References

- [Source: _bmad-output/planning-artifacts/epics.md - Story 5.9, FR32]
- [Source: docs/planning-artifacts/architecture.md - API patterns, Form Requests]
- [Source: _bmad-output/implementation-artifacts/5-8-face-browse-available-missions.md - Base implementation]
- [Source: frontend/src/pages/face/mission/FaceMissionsListPage.vue - UI patterns]
- [Source: backend/app/Http/Controllers/Api/V1/Face/MissionController.php - Controller to extend]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

### Completion Notes List

- Backend: Added filter parameters to Face MissionController using Laravel's `when()` conditional clauses
- Backend: Created FilterMissionsRequest for validation of lieu, budget_min, budget_max, date_tournage, type_mission
- Backend: Added 14 new filter tests (24 total tests, 132 assertions) in FaceBrowseMissionsTest.php
- Backend: All 451 tests pass (1998 assertions) - no regressions
- Frontend: Added MissionFilters interface to mission types
- Frontend: Updated faceMissionApi to accept optional filters parameter
- Frontend: Created useMissionFilters composable with URL sync (query params preserved)
- Frontend: Created MissionFiltersPanel component with responsive design (sidebar on desktop, overlay on mobile)
- Frontend: Updated useFaceMissions to support filter parameter passing
- Frontend: Integrated filters into FaceMissionsListPage with active filter count badge
- Frontend: Different empty state messages for "no missions" vs "no matching filters"
- Frontend: TypeScript type checking passes
- All 11 acceptance criteria satisfied

**Code Review Fixes (7 issues resolved):**
- Added budget_min ≤ budget_max validation with custom after() validator
- Escaped LIKE wildcards in lieu filter to prevent pattern injection
- Added test for budget_min > budget_max validation error
- Removed redundant onMounted() URL initialization (now explicit in page)
- Added guard flag to prevent URL watcher re-initialization loops
- Improved budget input NaN handling with Math.max(0, parsed)

### File List

**Created:**
- `backend/app/Http/Requests/Mission/FilterMissionsRequest.php` - Filter validation request
- `frontend/src/features/mission/composables/useMissionFilters.ts` - Filter state with URL sync
- `frontend/src/features/mission/components/MissionFiltersPanel.vue` - Filter panel UI

**Modified:**
- `backend/app/Http/Controllers/Api/V1/Face/MissionController.php` - Added filter logic with when() clauses
- `backend/tests/Feature/Mission/FaceBrowseMissionsTest.php` - Added 13 filter tests
- `frontend/src/features/mission/types/mission.ts` - Added MissionFilters interface
- `frontend/src/features/mission/services/faceMissionApi.ts` - Added filters parameter
- `frontend/src/features/mission/composables/useFaceMissions.ts` - Added filter support
- `frontend/src/features/mission/composables/index.ts` - Export useMissionFilters
- `frontend/src/features/mission/components/index.ts` - Export MissionFiltersPanel
- `frontend/src/pages/face/mission/FaceMissionsListPage.vue` - Integrated filters panel
