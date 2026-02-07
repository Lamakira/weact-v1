# Story 11.7: Public Mission Detail

Status: done

## Story

As a **visitor**,
I want **to view a mission's full details without logging in**,
So that **I can evaluate the opportunity and decide whether to register/login to apply**.

## Acceptance Criteria

1. **Given** I am on the `/missions` page **When** I click on a mission card **Then** I navigate to `/missions/:id` and see the full mission details

2. **Given** I am on the `/missions/:id` page **When** the page loads **Then** I see all public mission fields: title, description, type, genre, budget, shooting date, application deadline, location, duration, number of faces needed, and profile sought

3. **Given** I am viewing a mission detail **When** I look at the producer section **Then** I see the producer's display name, thumbnail photo, average rating, and ratings count

4. **Given** I am viewing a mission detail **When** I look at the call-to-action area **Then** I see a prominent CTA button to register/login to apply to this mission

5. **Given** I navigate to `/missions/:id` with a non-existent ID **When** the page loads **Then** I see a user-friendly "mission not found" message with a link back to the missions list

6. **Given** I navigate to `/missions/:id` for a non-published mission (draft/closed/completed) **When** the API responds **Then** I get a 404 and see the "not found" message (non-published missions are not publicly accessible)

7. **Given** I am on the mission detail page **When** I look at dates and currency **Then** dates are formatted in French locale and budget is displayed in XOF format

8. **Given** I am on the mission detail page **When** I want to go back **Then** I see a back link/button to return to the missions list

9. **Given** I share the URL `/missions/42` with someone **When** they open it **Then** they see the same mission detail (direct URL access works)

10. **Given** the mission detail is loading **When** I wait **Then** I see a skeleton/loading state, and if the API fails I see an error state with retry option

---

## Tasks / Subtasks

### Backend Tasks

- [x] Task 1: Add `show()` method to `Public\MissionController` (AC: #1, #2, #3, #5, #6)
  - [x] Add `public function show(int $id): JsonResponse` method
  - [x] Query `Mission::where('status', MissionStatus::Published)->with(['producer' => ...eager load ratings])->find($id)`
  - [x] Return 404 with `MISSION_NOT_FOUND` error code if not found OR not published
  - [x] Return single mission using existing `PublicMissionResource`
  - [x] Response format: `{ data: {...}, message: "Mission retrieved successfully" }` (no `meta` for single resource)

- [x] Task 2: Add route for mission detail (AC: #1, #9)
  - [x] Add `Route::get('/missions/{id}', [MissionController::class, 'show'])->whereNumber('id')` to `routes/api/public.php`
  - [x] Place AFTER the existing `/missions` (index) route

- [x] Task 3: Write backend feature tests for mission detail (AC: #1-#6, #9)
  - [x] Test returns published mission with all expected fields
  - [x] Test returns producer data with ratings
  - [x] Test returns 404 for non-existent mission ID
  - [x] Test returns 404 for draft mission (not publicly visible)
  - [x] Test returns 404 for closed mission
  - [x] Test returns 404 for completed mission
  - [x] Test does not require authentication
  - [x] Test response has correct data types
  - [x] Test date fields are formatted correctly (ISO date strings)
  - [x] Test budget is integer XOF

### Frontend Tasks

- [x] Task 4: Add `fetchPublicMissionDetail()` to `publicMissionsApi.ts` (AC: #1)
  - [x] Add `PublicMissionDetailResponse` interface: `{ data: PublicMission, message: string }`
  - [x] Add `PublicMissionDetailResult` interface: `{ success: boolean, mission?: PublicMission, error?: string, notFound?: boolean }`
  - [x] Add `fetchPublicMissionDetail(id: number): Promise<PublicMissionDetailResult>` with try/catch, 404 handling
  - Note: Reused `PublicMission` type directly instead of a separate `PublicMissionDetail` — same fields from `PublicMissionResource`

- [x] Task 5: Create `useMissionDetail` composable (AC: #10)
  - [x] Create `frontend/src/features/public/composables/useMissionDetail.ts`
  - [x] Reactive state: `mission`, `isLoading`, `error`, `notFound`
  - [x] `fetchMission(id: number)` function that resets state before fetching
  - [x] Follow exact pattern from `useFaceProfile` composable

- [x] Task 6: Create `PublicMissionDetailView.vue` (AC: #1-#4, #7, #8, #10)
  - [x] Create `frontend/src/views/PublicMissionDetailView.vue`
  - [x] Gemini MCP (`create_frontend`) was unavailable (503), designed manually following design-system.md + PublicFaceProfileView pattern
  - [x] Display all mission fields with proper formatting (French dates, XOF budget)
  - [x] Producer section with photo (initials fallback), name, rating
  - [x] CTA section: "Se connecter pour postuler" button linking to `/login`, register link to `/register/face`
  - [x] Back link to `/missions` list
  - [x] Loading state with Skeleton components
  - [x] Error state with retry button
  - [x] Not-found state with link back to missions list
  - [x] `useTitle` from `@vueuse/core` for dynamic page title
  - [x] Deadline passed detection: shows "Candidatures clôturées" badge instead of CTA

- [x] Task 7: Add route to `router/index.ts` (AC: #1, #9)
  - [x] Add `/missions/:id` route with name `public-mission-detail` (name already used in PublicMissionCard!)
  - [x] Component: lazy import `PublicMissionDetailView.vue`
  - [x] Meta: `title: 'Mission | WEACT'`, `description: 'Découvrez les détails de cette mission sur WEACT.'`
  - [x] Place AFTER the existing `/missions` route

### Testing Tasks

- [x] Task 8: Write `useMissionDetail` composable tests
  - [x] Create `frontend/src/features/public/composables/__tests__/useMissionDetail.spec.ts`
  - [x] Test initial state (null, not loading, no error)
  - [x] Test successful fetch populates mission data
  - [x] Test 404 sets notFound to true
  - [x] Test network error sets error message
  - [x] Test state resets between fetches
  - [x] Test loading state during fetch

- [x] Task 9: Write `PublicMissionDetailView` integration tests
  - [x] Create `frontend/src/views/__tests__/PublicMissionDetailView.spec.ts`
  - [x] Test renders mission detail on successful load
  - [x] Test shows loading state initially
  - [x] Test shows not-found state for 404
  - [x] Test shows error state with retry button
  - [x] Test back link navigates to missions list
  - [x] Test CTA button links to login/register
  - [x] Test displays formatted dates and budget
  - [x] Test producer section shows name, photo, rating

---

## Dev Notes

### CRITICAL: Follow Existing Public Detail Pattern Exactly

Story 11-4 established the public profile detail pattern (PublicFaceProfileView). Story 11-7 follows the EXACT same architecture:

**Backend pattern (extend existing `Public\MissionController`):**
```php
public function show(int $id): JsonResponse
{
    $mission = Mission::query()
        ->where('status', MissionStatus::Published)
        ->with(['producer' => fn ($q) => $q
            ->withAvg('ratingsReceived', 'score')
            ->withCount('ratingsReceived'),
        ])
        ->find($id);

    if (! $mission) {
        return response()->json([
            'error' => [
                'code' => 'MISSION_NOT_FOUND',
                'message' => 'Mission non trouvée',
            ],
        ], 404);
    }

    return response()->json([
        'data' => new PublicMissionResource($mission),
        'message' => 'Mission retrieved successfully',
    ]);
}
```

**CRITICAL: Filter by `status = Published` BEFORE `find($id)`** to ensure draft/closed/completed missions return 404. Do NOT find first and then check status — that leaks existence of non-published missions.

### Frontend API Service Pattern

Follow `publicFacesApi.ts::fetchPublicFaceProfile()` exactly:
```typescript
export async function fetchPublicMissionDetail(
  id: number
): Promise<PublicMissionDetailResult> {
  try {
    const response = await publicApiClient.get<PublicMissionDetailResponse>(
      `/v1/public/missions/${id}`
    )
    return { success: true, mission: response.data.data }
  } catch (err) {
    const axiosError = err as AxiosError
    if (axiosError.response?.status === 404) {
      return { success: false, notFound: true, error: 'Mission non trouvée' }
    }
    return { success: false, error: 'Une erreur est survenue. Veuillez réessayer.' }
  }
}
```

### Composable Pattern

Follow `useFaceProfile.ts` exactly:
```typescript
export function useMissionDetail() {
  const mission = ref<PublicMissionDetail | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const notFound = ref(false)

  async function fetchMission(id: number): Promise<void> {
    isLoading.value = true
    error.value = null
    notFound.value = false
    mission.value = null

    const result = await fetchPublicMissionDetail(id)
    // ... handle result
    isLoading.value = false
  }

  return { mission, isLoading, error, notFound, fetchMission }
}
```

### PublicMissionResource Already Exists

The `PublicMissionResource` was created in Story 11-6. It returns ALL needed fields including the `producer` object with `display_name`, `profile_photo_thumbnail_url`, `average_rating`, `ratings_count`. **Reuse it as-is** — no new resource needed.

### Route Name Already Referenced

`PublicMissionCard.vue` already links to `{ name: 'public-mission-detail', params: { id: mission.id } }`. The route must use this exact name: `public-mission-detail`.

### Date & Currency Formatting

- Dates: Use `new Date(dateString).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })` → "6 janvier 2026"
- Budget: Use `budget.toLocaleString('fr-FR')` + " XOF" → "150 000 XOF"
- Duration: Display `duree` as-is (already human-readable, e.g. "2 jours")

### CTA Design

The CTA should be prominent and contextual:
- Primary button: "Se connecter pour postuler" → links to `/login`
- Secondary link: "Pas encore inscrit ? Créez votre compte" → links to `/register`
- Consider checking if `date_limite_candidature` has passed → show "Candidatures clôturées" instead

### Fields to Display

| Field | Source | Display |
|-------|--------|---------|
| Title | `titre` | Page heading |
| Type | `type_mission_label` | Badge/tag |
| Genre | `genre_voulu_label` | Badge/tag |
| Description | `description` | Full text block |
| Budget | `budget` | "150 000 XOF" formatted |
| Shooting date | `date_tournage` | French date format |
| Deadline | `date_limite_candidature` | French date + urgency indicator |
| Location | `lieu` | With MapPin icon |
| Duration | `duree` | With Clock icon |
| Faces needed | `nombre_faces_voulu` | With Users icon |
| Profile sought | `profil_recherche` | Full text block |
| Producer name | `producer.display_name` | Linked to producer profile |
| Producer photo | `producer.profile_photo_thumbnail_url` | Thumbnail |
| Producer rating | `producer.average_rating` + `ratings_count` | Stars + count |
| Posted date | `created_at` | Relative or absolute |

### File Structure

```
backend/
├── app/Http/Controllers/Api/V1/Public/
│   └── MissionController.php              # MODIFY (add show() method)
├── routes/api/
│   └── public.php                         # MODIFY (add mission detail route)
└── tests/Feature/Public/
    └── PublicMissionDetailTest.php         # NEW

frontend/src/
├── features/public/
│   ├── composables/
│   │   ├── useMissionDetail.ts            # NEW
│   │   └── __tests__/
│   │       └── useMissionDetail.spec.ts   # NEW
│   └── services/
│       └── publicMissionsApi.ts           # MODIFY (add fetchPublicMissionDetail)
├── views/
│   ├── PublicMissionDetailView.vue        # NEW
│   └── __tests__/
│       └── PublicMissionDetailView.spec.ts # NEW
└── router/
    └── index.ts                           # MODIFY (add route)
```

### Dependencies

- `@vueuse/core` — already installed. Provides `useTitle`.
- `lucide-vue-next` — already installed. Icons: `Calendar`, `MapPin`, `Wallet`, `Users`, `Briefcase`, `Clock`, `ChevronLeft`, `AlertCircle`, `RefreshCw`, `Star`.
- No new dependencies needed.

### CRITICAL: Test Backward Compatibility

All existing tests must continue passing. The new route and controller method are additive — no existing behavior changes. Verify:
- Existing `PublicMissionsListTest` still passes
- Existing `PublicMissionsView.spec.ts` still passes
- Existing `PublicMissionCard.spec.ts` still passes (already expects `public-mission-detail` route)

---

## Previous Story Intelligence (11-6)

### Learnings Applied

1. **Backend pattern**: `Public\MissionController` already has `index()` — add `show()` in same class
2. **Resource reuse**: `PublicMissionResource` already transforms all fields — reuse for detail view
3. **Route pattern**: `routes/api/public.php` already has `/missions` GET — add `/missions/{id}` GET
4. **Frontend API service**: `publicMissionsApi.ts` already has `fetchPublicMissions` — add `fetchPublicMissionDetail`
5. **Frontend detail view pattern**: `PublicFaceProfileView.vue` + `useFaceProfile.ts` established the detail view pattern in Story 11-4
6. **Test pattern**: Follow `PublicFacesListTest.php` structure for backend tests, follow `PublicFaceProfileView.spec.ts` for frontend
7. **Code review learnings from 11-6**: Use lazy loading on images, add aria-hidden on decorative icons, type nullable fields correctly

### Relevant Commits

- `5d4cfcc` - chore(sprint): mark Story 11-9 done (public faces search)
- `63287e9` - chore(sprint): mark Story 11-6 done (public missions list)
- `c27c113` - feat(public): add public missions list page with pagination (Story 11-6)

---

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

### Completion Notes List

- All 9 tasks completed successfully
- Backend: 12 new tests passing (85 assertions), 16 existing missions list tests still passing — total 28 (561 assertions)
- Frontend: 1607 tests passing across 87 files (zero regressions)
  - useMissionDetail: 10 tests (4 initial state + 6 fetchMission)
  - PublicMissionDetailView: 15 tests (integration tests covering all states + formatting + CTA + breadcrumb + retry click + non-numeric param)
- TypeScript check clean (no errors)
- Backend `show()` filters by `status = Published` BEFORE `find($id)` to prevent leaking non-published missions
- Frontend reuses `PublicMission` type directly (no separate `PublicMissionDetail` — same resource)
- Gemini MCP was unavailable (503), view designed manually matching design-system.md + PublicFaceProfileView pattern
- Deadline-past detection: shows "Candidatures clôturées" badge instead of apply CTA
- French locale date formatting and XOF budget formatting in view
- Route name `public-mission-detail` matches existing reference in PublicMissionCard.vue
- Code review fixes applied (8 findings):
  - Extracted `publishedWithProducer()` shared query in MissionController (DRY)
  - Fixed timezone-naive deadline comparison to use date-only string comparison
  - Added retry button click test + non-numeric route param test (+2 tests)
  - Fixed `PublicMissionResource` to return `null` when producer is null (not an object with null fields)
  - Added dynamic OG/meta tags for SEO (parity with PublicFaceProfileView)
  - Added `aria-label` on CTA section for accessibility
  - Strengthened producer `display_name` assertion in backend test

### File List

**New:**
- `backend/tests/Feature/Public/PublicMissionDetailTest.php` (12 tests)
- `frontend/src/features/public/composables/useMissionDetail.ts`
- `frontend/src/features/public/composables/__tests__/useMissionDetail.spec.ts` (10 tests)
- `frontend/src/views/PublicMissionDetailView.vue`
- `frontend/src/views/__tests__/PublicMissionDetailView.spec.ts` (15 tests)

**Modified:**
- `backend/app/Http/Controllers/Api/V1/Public/MissionController.php` (added show() method + publishedWithProducer() DRY refactor)
- `backend/app/Http/Resources/PublicMissionResource.php` (producer null handling)
- `backend/routes/api/public.php` (added /missions/{id} route)
- `frontend/src/features/public/services/publicMissionsApi.ts` (added fetchPublicMissionDetail + interfaces)
- `frontend/src/router/index.ts` (added public-mission-detail route)
