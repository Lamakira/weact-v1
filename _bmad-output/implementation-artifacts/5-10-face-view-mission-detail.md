# Story 5.10: Face View Mission Detail

Status: done

## Story

As a **Face**,
I want **to view the complete details of a mission**,
so that **I can decide whether to apply**.

## Acceptance Criteria

1. **Given** I am logged in as a Face **When** I click on a mission from the list **Then** I am navigated to `/face/missions/:id`

2. **Given** I am on the mission detail page **When** the page loads **Then** I see all mission information including:
   - Titre
   - Description (full, not truncated)
   - Date de tournage
   - Lieu
   - Budget (formatted as XOF)
   - Type de mission
   - Genre voulu
   - Profil recherché
   - Nombre de Faces voulu
   - Durée
   - Date limite de candidature
   - Producer info (name, photo, type)

3. **Given** I am viewing a published mission **When** the mission is accepting candidatures **Then** I see a "Postuler" button

4. **Given** I am viewing a published mission **When** the mission is NOT accepting candidatures (closed or past deadline) **Then** the "Postuler" button is disabled or hidden with appropriate message

5. **Given** I try to access a mission that doesn't exist **When** the API returns 404 **Then** I see a user-friendly error message and a link back to the missions list

6. **Given** I try to access a non-published mission (draft, closed, completed) **When** the API validates access **Then** I receive a 404 error (Faces can only see published missions)

7. **Given** I am on the mission detail page **When** I click "Retour" or back button **Then** I return to the missions list with my filters preserved

**(FR33)**

## Tasks / Subtasks

- [x] Task 1: Add show method to Face MissionController (AC: #2, #5, #6)
  - [x] Add `show(Mission $mission)` method to `Face\MissionController`
  - [x] Verify mission status is `published` (return 404 if not)
  - [x] Load producer relationship
  - [x] Return MissionResource with full data

- [x] Task 2: Add route for mission detail (AC: #1)
  - [x] Add `GET /v1/face/missions/{mission}` route to `routes/api/face.php`
  - [x] Apply `face` and `throttle:60,1` middleware

- [x] Task 3: Create backend feature tests (AC: #2, #5, #6)
  - [x] Create `tests/Feature/Mission/FaceViewMissionDetailTest.php`
  - [x] Test Face can view published mission detail
  - [x] Test Face cannot view draft mission (404)
  - [x] Test Face cannot view closed mission (404)
  - [x] Test Face cannot view completed mission (404)
  - [x] Test non-existent mission returns 404
  - [x] Test Producer cannot access Face mission detail endpoint (403)
  - [x] Test unauthenticated user cannot access (401)
  - [x] Test response includes all required fields

- [x] Task 4: Add getMissionDetail to faceMissionApi (AC: #2)
  - [x] Add `getMissionDetail(id: number)` method to `faceMissionApi.ts`
  - [x] Return `MissionResponse` type

- [x] Task 5: Create useMissionDetail composable (AC: #2, #5)
  - [x] Create `frontend/src/features/mission/composables/useMissionDetail.ts`
  - [x] Manage mission state, loading, error
  - [x] Handle 404 errors gracefully
  - [x] Export from composables/index.ts

- [x] Task 6: Create FaceMissionDetailPage component (AC: #2, #3, #4, #7)
  - [x] Create `frontend/src/pages/face/mission/FaceMissionDetailPage.vue`
  - [x] Display all mission information in organized layout
  - [x] Show "Postuler" button if mission is accepting candidatures
  - [x] Show disabled state or message if not accepting
  - [x] Add back button to return to list
  - [x] Handle loading and error states

- [x] Task 7: Update router configuration (AC: #1)
  - [x] Update `frontend/src/router/index.ts`
  - [x] Change `face-mission-detail` route to use `FaceMissionDetailPage.vue`

- [x] Task 8: TypeScript and tests verification
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (no regressions)
  - [x] Manual frontend verification

## Dev Notes

### 🚨 CRITICAL: Use Gemini MCP for Frontend UI

**You MUST use the Gemini MCP tools for all frontend UI development:**
- `create_frontend` - For new components (FaceMissionDetailPage)
- `modify_frontend` - For modifying existing components
- `snippet_frontend` - For smaller UI pieces

**Always pass the existing CSS/theme files in the `context` parameter** to ensure design consistency.

### 🎯 This story extends Story 5-8 and 5-9 (Face Browse/Filter Missions)

This story builds on the existing Face missions browsing feature:
- Adds a detail page for individual mission viewing
- Extends the Face\MissionController with a `show` method
- Preserves navigation back to filtered list

### Architecture Patterns (from Previous Stories)

- **Controller show method**: Return single resource with MissionResource
- **Route model binding**: Use `{mission}` for automatic model resolution
- **Error handling**: Use `getApiErrorMessage` from `@/features/auth/services/authApi`
- **Composables**: Split data fetching logic into reusable composable

### API Endpoint

```
GET /api/v1/face/missions/{id}
Authorization: Bearer {token}

Response (200):
{
  "data": {
    "id": 1,
    "titre": "Publicité Savon",
    "description": "Recherche visages pour campagne...",
    "date_tournage": "2026-02-15T00:00:00Z",
    "profil_recherche": "Femme 25-35 ans, peau noire...",
    "budget": 150000,
    "date_limite_candidature": "2026-02-01T00:00:00Z",
    "nombre_faces_voulu": 3,
    "type_mission": "publicite",
    "type_mission_label": "Publicité",
    "genre_voulu": "femme",
    "genre_voulu_label": "Femme",
    "lieu": "Cotonou",
    "duree": "1 jour",
    "status": "published",
    "status_label": "Publiée",
    "is_accepting_candidatures": true,
    "producer": {
      "id": 1,
      "type": "agency",
      "agency_name": "Studio XYZ",
      "display_name": "Studio XYZ",
      "profile_photo_url": "...",
      "agency_logo_url": "..."
    },
    "created_at": "2026-01-20T10:00:00Z",
    "updated_at": "2026-01-20T10:00:00Z"
  }
}

Response (404): Mission not found or not published
Response (401): Unauthenticated
Response (403): Not a Face user
```

### Backend Controller Pattern

```php
// In Face\MissionController
public function show(Mission $mission): JsonResponse
{
    // Only allow viewing published missions
    if ($mission->status !== MissionStatus::Published) {
        abort(404);
    }

    $mission->load('producer');

    return response()->json([
        'data' => new MissionResource($mission),
    ]);
}
```

### Frontend API Service Pattern

```typescript
// Add to faceMissionApi.ts
async getMissionDetail(id: number): Promise<MissionResponse> {
  const response = await apiClient.get<MissionResponse>(`/face/missions/${id}`)
  return response.data
}
```

### Frontend Composable Pattern

```typescript
// useMissionDetail.ts
export function useMissionDetail() {
  const mission = ref<Mission | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const notFound = ref(false)

  async function fetchMission(id: number): Promise<void> {
    isLoading.value = true
    error.value = null
    notFound.value = false

    try {
      const response = await faceMissionApi.getMissionDetail(id)
      mission.value = response.data
    } catch (err: unknown) {
      if (isAxiosError(err) && err.response?.status === 404) {
        notFound.value = true
      } else {
        error.value = getApiErrorMessage(err)
      }
    } finally {
      isLoading.value = false
    }
  }

  return { mission, isLoading, error, notFound, fetchMission }
}
```

### Page Design

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Retour aux missions                                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  [Badge: Publicité]                                             │
│                                                                 │
│  Publicité Savon National                                       │
│  ═══════════════════════════════                                │
│                                                                 │
│  Recherche de visages féminins pour une campagne de             │
│  publicité pour le nouveau savon national...                    │
│                                                                 │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ 📅 Date de tournage    │ 15 février 2026              │    │
│  │ 📍 Lieu                │ Cotonou, Bénin               │    │
│  │ 💰 Budget              │ 150 000 XOF                  │    │
│  │ 👥 Faces recherchées   │ 3 Faces                      │    │
│  │ ⏱️ Durée               │ 1 jour                       │    │
│  │ 🎭 Genre voulu         │ Femme                        │    │
│  │ 📆 Date limite         │ 1 février 2026               │    │
│  └────────────────────────────────────────────────────────┘    │
│                                                                 │
│  Profil recherché                                               │
│  ────────────────                                               │
│  Femme 25-35 ans, peau noire, photogénique...                  │
│                                                                 │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ Producteur                                             │    │
│  │ ┌─────┐                                                │    │
│  │ │ IMG │  Studio XYZ                                    │    │
│  │ └─────┘  Agence                                        │    │
│  └────────────────────────────────────────────────────────┘    │
│                                                                 │
│                                    ┌─────────────────────────┐  │
│                                    │      POSTULER           │  │
│                                    └─────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Existing Files Reference

**Backend files to modify:**
- `app/Http/Controllers/Api/V1/Face/MissionController.php` - Add show method
- `routes/api/face.php` - Add show route

**Backend files to create:**
- `tests/Feature/Mission/FaceViewMissionDetailTest.php` - Feature tests

**Frontend files to create:**
- `src/pages/face/mission/FaceMissionDetailPage.vue` - Detail page
- `src/features/mission/composables/useMissionDetail.ts` - Composable

**Frontend files to modify:**
- `src/features/mission/services/faceMissionApi.ts` - Add getMissionDetail
- `src/features/mission/composables/index.ts` - Export new composable
- `src/router/index.ts` - Update route component

### Previous Story Intelligence (5-9)

**From Story 5-9 implementation:**
- Face MissionController has `index` method pattern to follow
- `getApiErrorMessage` import from `@/features/auth/services/authApi` works correctly
- MissionResource returns all needed fields including `is_accepting_candidatures`
- AvailableMissionCard shows the data structure for mission display
- FaceMissionsListPage has navigation to `face-mission-detail` route already

**Patterns to reuse:**
- `formatDate` and `formatCurrency` helpers from AvailableMissionCard
- Loading skeleton pattern from FaceMissionsListPage
- Error state pattern from FaceMissionsListPage
- Producer display pattern (name, avatar, initials)

### Git Intelligence (Recent Commits)

```
825b764 docs: complete story 5-9 face filter missions
2085444 feat(mission): integrate filters into Face missions page
459af87 feat(mission): create filter composable and panel component
71aebe1 feat(mission): add filter types and API support
bbf4095 test(mission): add filter tests for Face browse missions
c28db1f feat(mission): add filter validation for Face missions endpoint
```

Story 5-9 commits show the patterns for extending Face missions feature.

### Test Scenarios

| Scenario | Input | Expected |
|----------|-------|----------|
| View published mission | GET /face/missions/1 | 200 with full mission data |
| View draft mission | GET /face/missions/2 (draft) | 404 Not Found |
| View closed mission | GET /face/missions/3 (closed) | 404 Not Found |
| View completed mission | GET /face/missions/4 (completed) | 404 Not Found |
| View non-existent | GET /face/missions/9999 | 404 Not Found |
| Producer access | Producer token | 403 Forbidden |
| Unauthenticated | No token | 401 Unauthorized |
| Mission open for candidatures | is_accepting = true | "Postuler" button enabled |
| Mission closed | is_accepting = false | "Postuler" button disabled |

### MissionResource Fields (Already Implemented)

The `MissionResource` already returns all needed fields:
- id, titre, description
- date_tournage, lieu, budget, duree
- type_mission, type_mission_label
- genre_voulu, genre_voulu_label
- profil_recherche
- nombre_faces_voulu
- date_limite_candidature
- status, status_label
- is_accepting_candidatures (computed)
- producer (nested ProducerResource)
- created_at, updated_at

### Router Current State

The route already exists but points to wrong component:
```typescript
{
  path: '/face/missions/:id',
  name: 'face-mission-detail',
  // TODO: Story 5-10 will create this page
  component: () => import('../pages/face/mission/FaceMissionsListPage.vue'), // WRONG
  meta: { requiresAuth: true, role: 'Face' },
}
```

Update to:
```typescript
{
  path: '/face/missions/:id',
  name: 'face-mission-detail',
  component: () => import('../pages/face/mission/FaceMissionDetailPage.vue'),
  meta: { requiresAuth: true, role: 'Face' },
}
```

### References

- [Source: _bmad-output/planning-artifacts/epics.md - Story 5.10, FR33]
- [Source: backend/app/Http/Resources/MissionResource.php - API response structure]
- [Source: frontend/src/features/mission/components/AvailableMissionCard.vue - Display patterns]
- [Source: frontend/src/pages/face/mission/FaceMissionsListPage.vue - Navigation, error handling]
- [Source: backend/app/Http/Controllers/Api/V1/Face/MissionController.php - Controller to extend]
- [Source: frontend/src/router/index.ts - Route already defined, needs component update]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

### Completion Notes List

- Backend: Added `show` method to Face\MissionController with status verification (only published missions viewable)
- Backend: Added GET `/v1/face/missions/{mission}` route with `face` and `throttle:60,1` middleware
- Backend: Created comprehensive test suite `FaceViewMissionDetailTest.php` with 11 tests (75 assertions)
- Backend: All 462 tests pass (2073 assertions) - no regressions
- Frontend: Added `getMissionDetail(id: number)` method to `faceMissionApi.ts`
- Frontend: Created `useMissionDetail` composable with loading, error, and notFound states
- Frontend: Created `FaceMissionDetailPage.vue` with responsive layout displaying all mission fields
- Frontend: Page shows "Postuler" button when mission is accepting candidatures, disabled message otherwise
- Frontend: Proper 404 handling with user-friendly error message and back button
- Frontend: Updated router to point `face-mission-detail` route to new page component
- Frontend: TypeScript type checking passes
- All 7 acceptance criteria satisfied

**Code Review Fixes (4 issues resolved):**
- M1: Added sprint-status.yaml to File List (documentation completeness)
- M2: Removed console.log from handleApply function (production code quality)
- M3: Changed router.push() to router.back() to preserve filters (AC #7 compliance)
- M4: Removed scoped CSS overrides, using Tailwind CSS variables (maintainability)
- L1: Added NaN check for route param parsing (error handling)

### File List

**Created:**
- `backend/tests/Feature/Mission/FaceViewMissionDetailTest.php` - Feature tests (11 tests)
- `frontend/src/features/mission/composables/useMissionDetail.ts` - Mission detail composable
- `frontend/src/pages/face/mission/FaceMissionDetailPage.vue` - Mission detail page

**Modified:**
- `backend/app/Http/Controllers/Api/V1/Face/MissionController.php` - Added show method
- `backend/routes/api/face.php` - Added show route
- `frontend/src/features/mission/services/faceMissionApi.ts` - Added getMissionDetail method
- `frontend/src/features/mission/composables/index.ts` - Export useMissionDetail
- `frontend/src/router/index.ts` - Updated face-mission-detail route component
- `_bmad-output/implementation-artifacts/sprint-status.yaml` - Updated story status
