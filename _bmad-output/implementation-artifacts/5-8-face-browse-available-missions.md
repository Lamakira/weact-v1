# Story 5.8: Face Browse Available Missions

Status: done

## Story

As a **Face**,
I want **to browse all available missions**,
so that **I can find opportunities that match my profile**.

## Acceptance Criteria

1. **Given** I am logged in as a Face **When** I navigate to "Missions disponibles" **Then** I see a paginated list of published missions

2. **Given** I am on the missions list **When** the page loads **Then** I see mission cards with: titre, description (truncated), date_tournage, budget, lieu, type_mission, nombre_faces_voulu, and producer info

3. **Given** I am viewing missions **When** the list displays **Then** missions are ordered by most recent first (created_at DESC)

4. **Given** I am viewing missions **When** I scroll past the first page **Then** I see pagination controls (previous/next or infinite scroll)

5. **Given** there are more than 12 missions **When** the page loads **Then** I see 12 missions per page

6. **Given** I am viewing a mission card **When** I click on it **Then** I am redirected to the mission detail page (Story 5-10)

7. **Given** there are no published missions **When** the page loads **Then** I see an empty state with message "Aucune mission disponible pour le moment"

8. **Given** I am a Producer **When** I try to access the Face missions list **Then** I am redirected to my Producer dashboard

9. **Given** I am not logged in **When** I try to access the Face missions list **Then** I am redirected to the login page

10. **Given** I am viewing missions **When** I see a mission card **Then** I see the producer's name/agency name and profile photo

**(FR31)**

## Tasks / Subtasks

- [x] Task 1: Create Face MissionController for public missions (AC: #1, #3, #4, #5)
  - [x] Create `app/Http/Controllers/Api/V1/Face/MissionController.php`
  - [x] Add `index()` method to list published missions with pagination
  - [x] Filter by `status = 'published'` only
  - [x] Order by `created_at DESC`
  - [x] Paginate with 12 per page
  - [x] Eager load `producer` relationship

- [x] Task 2: Create Face API routes (AC: #9)
  - [x] Add `/v1/face/missions` route to `routes/api/face.php` (create if not exists)
  - [x] Protect with `auth:sanctum` middleware
  - [x] Add Face guard middleware to restrict to Face users only

- [x] Task 3: Create FaceGuard middleware (AC: #8)
  - [x] Create `app/Http/Middleware/EnsureUserIsFace.php`
  - [x] Check `user()->userable_type === Face::class`
  - [x] Return 403 if not Face
  - [x] Register middleware in `bootstrap/app.php`

- [x] Task 4: Create backend feature tests (AC: #1-#10)
  - [x] Create `tests/Feature/Mission/FaceBrowseMissionsTest.php`
  - [x] Test Face can list published missions
  - [x] Test pagination (12 per page)
  - [x] Test ordering (newest first)
  - [x] Test only published missions are returned (not draft, closed, completed)
  - [x] Test Producer cannot access this endpoint (403)
  - [x] Test unauthenticated returns 401
  - [x] Test response includes producer data
  - [x] Test empty list returns empty array with message

- [x] Task 5: Add Face missions route to frontend router (AC: #1, #8, #9)
  - [x] Add `/face/missions` route to `router/index.ts`
  - [x] Add Face role guard (redirect Producer to dashboard)
  - [x] Add auth guard (redirect unauthenticated to login)

- [x] Task 6: Create Face missionApi service (AC: #1)
  - [x] Create `frontend/src/features/mission/services/faceMissionApi.ts`
  - [x] Add `getAvailableMissions(page: number): Promise<PaginatedMissionsResponse>`
  - [x] Use `/face/missions?page={page}` endpoint

- [x] Task 7: Create useFaceMissions composable (AC: #1, #4)
  - [x] Create `frontend/src/features/mission/composables/useFaceMissions.ts`
  - [x] Manage loading state, error state, pagination state
  - [x] Implement `fetchMissions(page)` method
  - [x] Expose `missions`, `isLoading`, `error`, `currentPage`, `lastPage`, `totalCount`

- [x] Task 8: Create AvailableMissionCard component (AC: #2, #6, #10)
  - [x] Create `frontend/src/features/mission/components/AvailableMissionCard.vue`
  - [x] Display mission info: titre, description (truncated), date_tournage, budget, lieu
  - [x] Display producer info: name/agency name, profile photo
  - [x] Display type_mission and nombre_faces_voulu badges
  - [x] Make entire card clickable (emit click event with mission id)
  - [x] Use consistent styling with existing MissionCard

- [x] Task 9: Create FaceMissionsListPage (AC: #1, #4, #7)
  - [x] Create `frontend/src/pages/face/mission/FaceMissionsListPage.vue`
  - [x] Display header "Missions disponibles"
  - [x] Grid layout for mission cards (responsive)
  - [x] Pagination controls at bottom
  - [x] Loading skeleton state
  - [x] Empty state with illustration and message
  - [x] Error state with retry button

- [x] Task 10: Add navigation link to Face dashboard (AC: #1)
  - [x] Add "Voir les missions" card/button to Face dashboard
  - [x] Link to `/face/missions`

- [x] Task 11: Export new components and composables
  - [x] Export from `composables/index.ts`
  - [x] Export from `components/index.ts`

- [x] Task 12: TypeScript and tests verification
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (437 tests, 1944 assertions - no regressions)
  - [x] Manual frontend verification

## Dev Notes

### 🚨 CRITICAL: Use Gemini MCP for Frontend UI

**You MUST use the Gemini MCP tools for all frontend UI development:**
- `create_frontend` - For new components (AvailableMissionCard, FaceMissionsListPage)
- `modify_frontend` - For modifying existing components
- `snippet_frontend` - For smaller UI pieces

**Always pass the existing CSS/theme files in the `context` parameter** to ensure design consistency.

### 🎯 This is the FIRST Face-side mission feature

This story introduces:
1. New Face API routes (`/v1/face/missions`)
2. FaceGuard middleware pattern
3. Face-specific mission browsing (different from Producer's mission management)

### Architecture Patterns (from Previous Stories)

- **Controllers**: `app/Http/Controllers/Api/V1/{Role}/` - Controllers organized by user role
- **Middleware**: Create role-specific guards (FaceGuard, ProducerGuard already exists)
- **API Resources**: Use existing MissionResource for response transformation
- **Pagination**: Use Laravel's built-in pagination with `->paginate(12)`

### Database Query Pattern

```php
// In Face\MissionController::index()
$missions = Mission::where('status', MissionStatus::Published)
    ->with('producer')
    ->orderBy('created_at', 'desc')
    ->paginate(12);

return MissionResource::collection($missions);
```

### API Endpoint Specification

```
GET /api/v1/face/missions
Authorization: Bearer {token}

Query Parameters:
- page: integer (default: 1)

Response (Success - 200):
{
  "data": [
    {
      "id": 1,
      "titre": "Tournage publicitaire",
      "description": "Description...",
      "date_tournage": "2026-02-15",
      "budget": 150000,
      "lieu": "Cotonou",
      "type_mission": "tournage",
      "nombre_faces_voulu": 3,
      "status": "published",
      "status_label": "Publiée",
      "producer": {
        "id": 1,
        "type": "agency",
        "agency_name": "Studio XYZ",
        "first_name": null,
        "last_name": null,
        "photo_url": "/storage/avatars/producer-1.jpg"
      },
      "created_at": "2026-01-25T10:00:00Z"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 12,
    "total": 35
  }
}

Response (Empty - 200):
{
  "data": [],
  "links": {...},
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 12,
    "total": 0
  },
  "message": "Aucune mission disponible pour le moment"
}

Response (Unauthorized - 401):
{
  "message": "Unauthenticated."
}

Response (Forbidden - 403):
{
  "message": "Cette action n'est pas autorisée"
}
```

### Frontend Types

```typescript
// Add to mission/types/mission.ts
export interface PaginatedMissionsResponse {
  data: Mission[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  message?: string
}
```

### Mission Card Display Fields

| Field | Display Format |
|-------|----------------|
| titre | H3 heading, truncate at 60 chars |
| description | Paragraph, truncate at 120 chars |
| date_tournage | "15 février 2026" (French format) |
| budget | "150 000 XOF" (formatted with spaces) |
| lieu | City name with MapPin icon |
| type_mission | Badge (e.g., "Tournage", "Shooting") |
| nombre_faces_voulu | "3 Faces recherchées" |
| producer | Name + avatar |

### Pagination UI Options

**Recommended: Classic Pagination** (simpler for MVP)
- Previous / Page numbers / Next
- "Page 1 sur 3"

Alternative (V2): Infinite scroll with "Charger plus" button

### Frontend Routing

```typescript
// In router/index.ts
{
  path: '/face/missions',
  name: 'face-missions',
  component: () => import('@/pages/face/mission/FaceMissionsListPage.vue'),
  meta: { requiresAuth: true, role: 'face' }
}
```

### Role Guard Pattern

```typescript
// In router beforeEach guard
if (to.meta.role === 'face' && authStore.userType !== 'face') {
  // Redirect Producer to their dashboard
  return { name: 'producer-dashboard' }
}
```

### Empty State Design

```vue
<div class="flex flex-col items-center justify-center py-24 text-center">
  <Inbox class="h-16 w-16 text-muted-foreground/50" />
  <h3 class="mt-4 text-lg font-semibold">Aucune mission disponible</h3>
  <p class="mt-2 text-muted-foreground">
    Revenez plus tard pour découvrir de nouvelles opportunités.
  </p>
</div>
```

### Existing Files Reference

**Backend files to create:**
- `app/Http/Controllers/Api/V1/Face/MissionController.php`
- `app/Http/Middleware/EnsureUserIsFace.php`
- `routes/api/face.php`
- `tests/Feature/Mission/FaceBrowseMissionsTest.php`

**Frontend files to create:**
- `src/features/mission/services/faceMissionApi.ts`
- `src/features/mission/composables/useFaceMissions.ts`
- `src/features/mission/components/AvailableMissionCard.vue`
- `src/pages/face/mission/FaceMissionsListPage.vue`

**Files to modify:**
- `bootstrap/app.php` - Register FaceGuard middleware
- `router/index.ts` - Add face missions route
- `features/mission/composables/index.ts` - Export new composable
- `features/mission/components/index.ts` - Export new component
- `features/mission/types/mission.ts` - Add pagination types

### Previous Story Intelligence (5-5, 5-7)

**Patterns from Producer MissionsListPage (5-5):**
- TransitionGroup for smooth list animations
- Loading skeleton with pulse animation
- Error state with retry button
- Empty state with illustration

**Code reuse opportunities:**
- Copy MissionsListPage structure for FaceMissionsListPage
- Create AvailableMissionCard based on MissionCard (but read-only, no actions)
- Reuse formatDate and formatCurrency utilities

### Git Intelligence (Recent Commits)

```
4eb9913 docs: complete story 5-7 mark mission as completed
b44088b feat(mission): add complete mission UI
d1c9241 feat(mission): add missions list page and components
```

Story 5-5 (d1c9241) established the MissionsListPage pattern we should follow.

### References

- [Source: _bmad-output/planning-artifacts/epics.md - Story 5.8, FR31]
- [Source: docs/planning-artifacts/architecture.md - API patterns, frontend structure]
- [Source: _bmad-output/implementation-artifacts/5-5-producer-missions-list.md - List patterns]
- [Source: frontend/src/pages/producer/mission/MissionsListPage.vue - UI patterns]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/MissionController.php - Controller patterns]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

### Completion Notes List

- Backend implementation complete with 10 feature tests (78 assertions) in FaceBrowseMissionsTest.php
- All 437 backend tests pass (1944 assertions) - no regressions
- Created EnsureUserIsFace middleware and registered as 'face' alias
- Added GET /face/missions route with Face middleware guard
- Face MissionController returns paginated published missions ordered by newest first
- Frontend composable useFaceMissions with full pagination support
- AvailableMissionCard component displays mission info with producer details
- FaceMissionsListPage with responsive grid, loading skeletons, empty state, error state, and pagination
- Added "Voir les missions" card to Face dashboard for navigation
- Added MissionProducer interface and PaginatedMissionsResponse type
- TypeScript type checking passes
- Added placeholder route for face-mission-detail (Story 5-10)

### File List

**Created:**
- `backend/app/Http/Controllers/Api/V1/Face/MissionController.php` - Face mission browsing controller
- `backend/app/Http/Middleware/EnsureUserIsFace.php` - FaceGuard middleware
- `backend/tests/Feature/Mission/FaceBrowseMissionsTest.php` - 10 feature tests (78 assertions)
- `frontend/src/features/mission/services/faceMissionApi.ts` - Face mission API service
- `frontend/src/features/mission/composables/useFaceMissions.ts` - Face missions composable
- `frontend/src/features/mission/components/AvailableMissionCard.vue` - Mission card for Face browsing
- `frontend/src/pages/face/mission/FaceMissionsListPage.vue` - Face missions list page

**Modified:**
- `backend/bootstrap/app.php` - Registered 'face' middleware alias
- `backend/routes/api/face.php` - Added GET /missions route with Face middleware
- `frontend/src/router/index.ts` - Added face-missions and face-mission-detail routes
- `frontend/src/features/mission/types/mission.ts` - Added MissionProducer and PaginatedMissionsResponse types
- `frontend/src/features/mission/composables/index.ts` - Export useFaceMissions
- `frontend/src/features/mission/components/index.ts` - Export AvailableMissionCard
- `frontend/src/pages/dashboard/FaceDashboardPage.vue` - Added "Voir les missions" navigation card
