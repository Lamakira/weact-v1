# Story 6.3: Face View My Candidatures

Status: done

## Story

As a **Face**,
I want **to see all my candidatures with their current status**,
so that **I can track my applications and upcoming work**.

## Acceptance Criteria

1. **Given** I am logged in as a Face **When** I navigate to "Mes candidatures" **Then** I see a list of all my candidatures

2. **Given** I have candidatures in different statuses **When** the page loads **Then** candidatures are visually grouped or filterable by status (pending, accepted, confirmed, in_progress, completed, rejected)

3. **Given** I have a candidature **When** I view it in the list **Then** I see: mission title, mission date, producer name, status badge, date submitted

4. **Given** I have no candidatures **When** I view the page **Then** I see an empty state message encouraging me to browse missions

5. **Given** the list has many candidatures **When** I scroll **Then** the list is paginated (15 per page)

6. **Given** I click on a candidature **When** the detail loads **Then** I am taken to the mission detail page with my candidature status visible

7. **Given** I want to filter by status **When** I select a status filter **Then** only candidatures with that status are shown

**(FR35)**

## Tasks / Subtasks

- [x] Task 1: Create Face CandidatureController index method (AC: #1, #2, #5)
  - [x] Create `app/Http/Controllers/Api/V1/Face/CandidatureController.php` - add `index()` method
  - [x] Return paginated candidatures for authenticated Face
  - [x] Eager load mission and producer relationships
  - [x] Support optional `status` query param filter
  - [x] Return FaceCandidatureResource collection

- [x] Task 2: Create FaceCandidatureResource for list response (AC: #3)
  - [x] Create `app/Http/Resources/FaceCandidatureResource.php`
  - [x] Return: id, status, status_label, message_motivation (truncated), created_at
  - [x] Include nested mission: id, titre, date_tournage, lieu, budget
  - [x] Include nested producer: id, display_name, type, profile_photo_url

- [x] Task 3: Add route for candidatures list (AC: #1)
  - [x] Add `GET /v1/face/candidatures` route to `routes/api/face.php`
  - [x] Apply `face` and `throttle:60,1` middleware

- [x] Task 4: Create backend feature tests (AC: #1-7)
  - [x] Create `tests/Feature/Candidature/FaceViewCandidaturesTest.php`
  - [x] Test Face can view their candidatures
  - [x] Test pagination works (15 per page)
  - [x] Test status filter works
  - [x] Test candidatures include mission and producer data
  - [x] Test empty list returns empty array (not error)
  - [x] Test Producer cannot access Face candidatures (403)
  - [x] Test unauthenticated returns 401

- [x] Task 5: Create candidature types for frontend (AC: #3)
  - [x] Add `FaceCandidature` interface to `frontend/src/features/candidature/types/index.ts`
  - [x] Add `FaceCandidatureListResponse` interface
  - [x] Include nested `MissionSummary` and `ProducerSummary` types

- [x] Task 6: Add getCandidatures to candidatureApi (AC: #1, #5, #7)
  - [x] Add `getCandidatures(page?: number, status?: string)` method
  - [x] Return `PaginatedResponse<FaceCandidature>` type

- [x] Task 7: Create useFaceCandidatures composable (AC: #1, #2, #5, #7)
  - [x] Create `frontend/src/features/candidature/composables/useFaceCandidatures.ts`
  - [x] Manage loading, error, pagination states
  - [x] Support status filter
  - [x] Export from composables/index.ts

- [x] Task 8: Create FaceCandidaturesPage (AC: #1, #2, #3, #4)
  - [x] Create `frontend/src/pages/face/candidature/FaceCandidaturesPage.vue`
  - [x] Display candidatures in card list format
  - [x] Show status badges with appropriate colors
  - [x] Show empty state with CTA to browse missions
  - [x] Use Gemini MCP for UI design

- [x] Task 9: Create CandidatureCard component (AC: #3, #6)
  - [x] Create `frontend/src/features/candidature/components/CandidatureCard.vue`
  - [x] Show mission title, date, producer, status badge
  - [x] Link to mission detail page
  - [x] Use Gemini MCP for UI design

- [x] Task 10: Create StatusFilter component (AC: #7)
  - [x] Create `frontend/src/features/candidature/components/StatusFilter.vue`
  - [x] Chip/tab style filter for status selection
  - [x] Include "Tous" option to clear filter

- [x] Task 11: Add route to Vue Router
  - [x] Add route `/face/candidatures` to `router/index.ts`
  - [x] Add navigation link to Face sidebar/menu

- [x] Task 12: TypeScript types and verification
  - [x] TypeScript type checking passes
  - [x] All backend tests pass (no regressions)

## Dev Notes

### 🚨 CRITICAL: Use Gemini MCP for Frontend UI

**You MUST use the Gemini MCP tools for all frontend UI development:**
- `create_frontend` - For new components and pages
- `modify_frontend` - For modifying existing components
- `snippet_frontend` - For smaller UI pieces

**Always pass the existing CSS/theme files in the `context` parameter** to ensure design consistency.

### 🎯 This story continues the candidature feature from Story 6-2

The Face can now view all their submitted candidatures from Story 6-2's apply functionality.

### Architecture Patterns

**Backend:**
- Paginated endpoint with optional filter
- Eager load relationships to avoid N+1
- Use dedicated resource for list (different from detail)

**Frontend:**
- Page with composable for data fetching
- Reusable card component
- Filter component with URL sync

### API Endpoint

```
GET /api/v1/face/candidatures
Authorization: Bearer {token}
Query Params:
  - page: number (default 1)
  - status: string (optional filter: pending|accepted|confirmed|in_progress|completed|rejected)

Response (200 OK):
{
  "data": [
    {
      "id": 1,
      "status": "pending",
      "status_label": "En attente",
      "message_motivation": "Je suis très motivée...", // truncated
      "created_at": "2026-01-27T10:00:00Z",
      "mission": {
        "id": 5,
        "titre": "Publicité TV pour marque cosmétique",
        "date_tournage": "2026-02-15",
        "lieu": "Cotonou",
        "budget": 150000
      },
      "producer": {
        "id": 3,
        "display_name": "Agence XYZ",
        "type": "agency",
        "profile_photo_url": "https://..."
      }
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
    "per_page": 15,
    "total": 42
  }
}

Response (401): Unauthenticated
Response (403): Not a Face user
```

### Controller Pattern

```php
// Face\CandidatureController
public function index(Request $request): JsonResponse
{
    $face = $request->user()->userable;

    $query = Candidature::where('face_id', $face->id)
        ->with(['mission', 'mission.producer'])
        ->latest();

    // Optional status filter
    if ($request->has('status')) {
        $status = CandidatureStatus::tryFrom($request->status);
        if ($status) {
            $query->where('status', $status);
        }
    }

    $candidatures = $query->paginate(15);

    return FaceCandidatureResource::collection($candidatures)
        ->response();
}
```

### Resource Pattern

```php
// FaceCandidatureResource
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'status' => $this->status->value,
        'status_label' => $this->status->label(),
        'message_motivation' => $this->message_motivation
            ? Str::limit($this->message_motivation, 100)
            : null,
        'created_at' => $this->created_at->toISOString(),
        'mission' => [
            'id' => $this->mission->id,
            'titre' => $this->mission->titre,
            'date_tournage' => $this->mission->date_tournage->format('Y-m-d'),
            'lieu' => $this->mission->lieu,
            'budget' => $this->mission->budget,
        ],
        'producer' => [
            'id' => $this->mission->producer->id,
            'display_name' => $this->mission->producer->display_name,
            'type' => $this->mission->producer->type->value,
            'profile_photo_url' => $this->mission->producer->profile_photo_url,
        ],
    ];
}
```

### Status Badge Colors

| Status | Color | French Label |
|--------|-------|--------------|
| pending | Yellow/Amber | En attente |
| accepted | Blue | Acceptée |
| confirmed | Green | Confirmée |
| in_progress | Purple | En cours |
| completed | Green (darker) | Terminée |
| rejected | Red | Refusée |

### Page Layout Design

```
┌─────────────────────────────────────────────────────────────────┐
│  Mes candidatures                                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  [Tous] [En attente] [Acceptées] [En cours] [Terminées] [Ref]   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  🟡 En attente                                          │   │
│  │  Publicité TV pour marque cosmétique                    │   │
│  │  📅 15 février 2026 • 📍 Cotonou • 💰 150 000 XOF       │   │
│  │  Par: Agence XYZ                        Postuté le 27/01│   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  🟢 Acceptée                                            │   │
│  │  Court-métrage "Les Rêves"                              │   │
│  │  📅 20 février 2026 • 📍 Porto-Novo • 💰 200 000 XOF    │   │
│  │  Par: Jean Producteur                   Postuté le 25/01│   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│                        < 1 2 3 >                                │
└─────────────────────────────────────────────────────────────────┘
```

### Empty State Design

```
┌─────────────────────────────────────────────────────────────────┐
│  Mes candidatures                                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│                     📋                                          │
│                                                                 │
│              Aucune candidature                                 │
│                                                                 │
│     Vous n'avez pas encore postulé à une mission.               │
│     Découvrez les opportunités disponibles !                    │
│                                                                 │
│              [ Parcourir les missions ]                         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Existing Files Reference

**Backend files to create:**
- `app/Http/Resources/FaceCandidatureResource.php`
- `tests/Feature/Candidature/FaceViewCandidaturesTest.php`

**Backend files to modify:**
- `app/Http/Controllers/Api/V1/Face/CandidatureController.php` - Add index method
- `routes/api/face.php` - Add GET route

**Frontend files to create:**
- `src/pages/face/candidature/FaceCandidaturesPage.vue`
- `src/features/candidature/components/CandidatureCard.vue`
- `src/features/candidature/components/StatusFilter.vue`
- `src/features/candidature/composables/useFaceCandidatures.ts`

**Frontend files to modify:**
- `src/features/candidature/types/index.ts` - Add list types
- `src/features/candidature/services/candidatureApi.ts` - Add getCandidatures
- `src/features/candidature/composables/index.ts` - Export new composable
- `src/features/candidature/components/index.ts` - Export new components
- `src/router/index.ts` - Add route

### Previous Story Intelligence

**From Story 6-2 (Face Apply to Mission):**
- `Candidature` model and `CandidatureResource` already exist
- `candidatureApi` service exists with `applyToMission()` method
- `useApplyToMission` composable pattern to follow
- Frontend `features/candidature/` directory structure established
- Status types already defined in frontend

**From Story 6-1 (Candidature Schema):**
- `Candidature` model with relationships to `Face` and `Mission`
- `CandidatureStatus` enum with `label()` method
- Mission has `producer()` relationship for eager loading

### Git Intelligence (Recent Commits)

```
ca50e7f docs: complete story 6-2 face apply to mission
0b5251c feat(candidature): add apply to mission modal and integration
10a1495 test(candidature): add Face apply to mission tests
4151d89 feat(candidature): add Face apply to mission API endpoint
```

Story 6-2 established the candidature feature module patterns.

### Test Scenarios

| Scenario | Input | Expected |
|----------|-------|----------|
| View candidatures | GET as Face | 200, paginated list |
| Empty list | Face with no candidatures | 200, empty data array |
| Filter by status | ?status=pending | Only pending candidatures |
| Invalid status filter | ?status=invalid | Ignore filter, return all |
| Pagination | ?page=2 | Second page of results |
| Producer access | GET as Producer | 403 |
| Unauthenticated | No token | 401 |
| Candidatures include mission | GET | Each has mission object |
| Candidatures include producer | GET | Each has producer object |

### Dependencies

- **Depends on**: Story 6-1 (Candidature schema), Story 6-2 (Apply to mission)
- **Blocks**: Story 6-8 (Face Confirm Mission - needs to see accepted candidatures)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.3 - Face View My Candidatures, FR35]
- [Source: _bmad-output/project-context.md#API Response Format]
- [Source: backend/app/Models/Candidature.php - Candidature model]
- [Source: backend/app/Enums/CandidatureStatus.php - Status enum with labels]
- [Source: frontend/src/features/candidature/ - Existing feature module]
- [Source: _bmad-output/implementation-artifacts/6-2-face-apply-to-mission.md - Previous story patterns]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

None - all tests passed on first run

### Completion Notes List

1. **Backend API Complete**: Added `index()` method to CandidatureController with pagination (15/page) and optional status filter
2. **FaceCandidatureResource**: Created dedicated resource with nested mission and producer summary, motivation truncated to 100 chars
3. **14 Backend Tests**: Comprehensive coverage including pagination, filtering by all 6 statuses, authorization (401/403), ordering, and isolation
4. **Frontend Types**: Added FaceCandidature, MissionSummary, ProducerSummary, and StatusFilterOption interfaces
5. **candidatureApi Extended**: Added `getCandidatures(page, status)` method
6. **useFaceCandidatures Composable**: Full state management with pagination, status filter, URL sync support
7. **FaceCandidaturesPage**: Complete page with filter, loading/error/empty states, pagination, URL query sync
8. **CandidatureCard**: Reusable card component linking to mission detail with status badge colors
9. **StatusFilter**: Chip-style filter with all status options plus "Tous"
10. **Router**: Added `/face/candidatures` route with Face role protection
11. **TypeScript**: All types pass `vue-tsc --noEmit`
12. **All 532 backend tests pass** (no regressions, 14 new tests)

### File List

**Backend Files Created:**
- `backend/app/Http/Resources/FaceCandidatureResource.php`
- `backend/tests/Feature/Candidature/FaceViewCandidaturesTest.php`

**Backend Files Modified:**
- `backend/app/Http/Controllers/Api/V1/Face/CandidatureController.php` - Added index() method
- `backend/routes/api/face.php` - Added GET /face/candidatures route

**Frontend Files Created:**
- `frontend/src/pages/face/candidature/FaceCandidaturesPage.vue`
- `frontend/src/features/candidature/components/CandidatureCard.vue`
- `frontend/src/features/candidature/components/StatusFilter.vue`
- `frontend/src/features/candidature/composables/useFaceCandidatures.ts`

**Frontend Files Modified:**
- `frontend/src/features/candidature/types/index.ts` - Added FaceCandidature, MissionSummary, ProducerSummary types
- `frontend/src/features/candidature/services/candidatureApi.ts` - Added getCandidatures method
- `frontend/src/features/candidature/composables/index.ts` - Exported useFaceCandidatures
- `frontend/src/features/candidature/components/index.ts` - Exported CandidatureCard, StatusFilter
- `frontend/src/router/index.ts` - Added /face/candidatures route

### Code Review Fixes

**Reviewed by:** Claude Opus 4.5 (adversarial code review)

**Issues Found & Fixed:**

| Priority | Issue | Fix Applied |
|----------|-------|-------------|
| HIGH | Producer null check missing in FaceCandidatureResource | Added `&& $this->mission?->producer` condition |
| MEDIUM | Date parsing could crash on invalid dates in CandidatureCard | Added try/catch with isNaN validation |
| MEDIUM | Race condition when changing filter rapidly | Added request ID tracking to ignore stale responses |
| MEDIUM | Raw status shown in empty state message | Changed to use `CandidatureStatusLabel[statusFilter]` |

**Files Modified During Review:**
- `backend/app/Http/Resources/FaceCandidatureResource.php` - Null safety fix
- `frontend/src/features/candidature/components/CandidatureCard.vue` - Date parsing safety
- `frontend/src/features/candidature/composables/useFaceCandidatures.ts` - Race condition prevention
- `frontend/src/pages/face/candidature/FaceCandidaturesPage.vue` - French status label in empty state

**Verification:** All 14 backend tests pass, TypeScript check passes

**OWASP Security Review:**

| OWASP Check | Result | Notes |
|-------------|--------|-------|
| A01 Broken Access Control | PASS | Uses session Face ID, no IDOR, middleware applied |
| A03 Injection | PASS | Enum validation for filter, Vue auto-escaping for XSS |
| A04 Insecure Design | PASS | 60 req/min rate limit, 15 per page pagination |
| A05 Security Misconfiguration | PASS | Generic error messages, no stack traces |
| A07 Auth Failures | PASS | 401/403 tested, session-based auth |
