# Story 6.4: Producer View Mission Candidatures

Status: done

## Story

As a **Producer**,
I want **to see all candidatures received for each of my missions**,
so that **I can review applicants and make selections**.

## Acceptance Criteria

1. **Given** I am viewing my mission detail **When** I click on "Candidatures" section **Then** I see a list of all candidatures for this mission

2. **Given** a mission has candidatures **When** the page loads **Then** I see: Face name, Face profile photo, status badge, motivation message (truncated), date submitted, Face pricing (tarif horaire and journalier)

3. **Given** a mission has no candidatures **When** I view the candidatures section **Then** I see an empty state message "Aucune candidature reçue"

4. **Given** a mission has many candidatures **When** I scroll **Then** the list is paginated (15 per page)

5. **Given** I want to filter by status **When** I select a status filter **Then** only candidatures with that status are shown

6. **Given** I click on a Face's profile **When** the page loads **Then** I navigate to the candidate's full profile (Story 6-5)

7. **Given** I am a Face (not Producer) **When** I try to access this endpoint **Then** I get a 403 error

8. **Given** I am not the mission owner **When** I try to view candidatures **Then** I get a 403 error

**(FR36)**

## Tasks / Subtasks

- [x] Task 1: Create Producer CandidatureController with index method (AC: #1, #4, #5, #7, #8)
  - [x] Create `app/Http/Controllers/Api/V1/Producer/CandidatureController.php`
  - [x] Add `index(Request $request, Mission $mission)` method
  - [x] Verify mission belongs to authenticated Producer (authorization)
  - [x] Return paginated candidatures for the mission
  - [x] Eager load face and face.user relationships
  - [x] Support optional `status` query param filter
  - [x] Return ProducerCandidatureResource collection

- [x] Task 2: Create ProducerCandidatureResource for list response (AC: #2)
  - [x] Create `app/Http/Resources/ProducerCandidatureResource.php`
  - [x] Return: id, status, status_label, message_motivation (truncated 150 chars), created_at
  - [x] Include nested face: id, display_name, profile_photo_url, category, city, tarif_horaire, tarif_journalier

- [x] Task 3: Add route for mission candidatures list (AC: #1)
  - [x] Add `GET /v1/producer/missions/{mission}/candidatures` route to `routes/api/producer.php`
  - [x] Apply `producer` and `throttle:60,1` middleware

- [x] Task 4: Create backend feature tests (AC: #1-8)
  - [x] Create `tests/Feature/Candidature/ProducerViewCandidaturesTest.php`
  - [x] Test Producer can view candidatures for their mission
  - [x] Test pagination works (15 per page)
  - [x] Test status filter works
  - [x] Test candidatures include face data
  - [x] Test empty list returns empty array (not error)
  - [x] Test Face cannot access Producer candidatures (403)
  - [x] Test Producer cannot view another Producer's mission candidatures (403)
  - [x] Test unauthenticated returns 401

- [x] Task 5: Create candidature types for frontend (AC: #2)
  - [x] Add `ProducerCandidature` interface to `frontend/src/features/candidature/types/index.ts`
  - [x] Add `ProducerCandidatureListResponse` interface
  - [x] Include nested `FaceSummary` type (id, display_name, profile_photo_url, category, city, tarif_horaire, tarif_journalier)

- [x] Task 6: Add getMissionCandidatures to candidatureApi (AC: #1, #4, #5)
  - [x] Add `getMissionCandidatures(missionId: number, page?: number, status?: string)` method
  - [x] Return `PaginatedResponse<ProducerCandidature>` type

- [x] Task 7: Create useProducerCandidatures composable (AC: #1, #4, #5)
  - [x] Create `frontend/src/features/candidature/composables/useProducerCandidatures.ts`
  - [x] Accept missionId as parameter
  - [x] Manage loading, error, pagination states
  - [x] Support status filter
  - [x] Export from composables/index.ts

- [x] Task 8: Create ProducerCandidaturesSection component (AC: #1, #2, #3)
  - [x] Create `frontend/src/features/candidature/components/ProducerCandidaturesSection.vue`
  - [x] Accept missionId prop
  - [x] Display candidatures in list format
  - [x] Show status badges with appropriate colors
  - [x] Show empty state when no candidatures
  - [x] Use Gemini MCP for UI design

- [x] Task 9: Create ProducerCandidatureCard component (AC: #2, #6)
  - [x] Create `frontend/src/features/candidature/components/ProducerCandidatureCard.vue`
  - [x] Show Face photo, name, category, city, status badge, motivation excerpt
  - [x] Show Face pricing (tarif_horaire, tarif_journalier) formatted as XOF currency
  - [x] Show date submitted
  - [x] Link Face name/photo to candidate profile (placeholder route for now)
  - [x] Use Gemini MCP for UI design

- [x] Task 10: Integrate candidatures section into mission detail
  - [x] Created dedicated `/producer/missions/:id/candidatures` page
  - [x] Created `ProducerMissionCandidaturesPage.vue`
  - [x] Added route `producer-mission-candidatures`
  - [x] Added clickable candidatures count in MissionCard that navigates to candidatures page

- [x] Task 11: Create StatusFilter for Producer (reuse or adapt)
  - [x] Reuse existing StatusFilter component from Story 6-3

- [x] Task 12: TypeScript types and verification
  - [x] TypeScript type checking passes
  - [x] All 85 backend tests pass (no regressions)

## Dev Notes

### 🚨 CRITICAL: Use Gemini MCP for Frontend UI

**You MUST use the Gemini MCP tools for all frontend UI development:**
- `create_frontend` - For new components and pages
- `modify_frontend` - For modifying existing components
- `snippet_frontend` - For smaller UI pieces

**Always pass the existing CSS/theme files in the `context` parameter** to ensure design consistency.

### 🎯 This story is the Producer-side counterpart to Story 6-3

Story 6-3 was Face viewing their own candidatures. This story is Producer viewing candidatures received for their missions.

### Architecture Patterns

**Backend:**
- Nested resource route: `/missions/{mission}/candidatures`
- Authorization check: mission must belong to authenticated Producer
- Paginated endpoint with optional status filter
- Eager load face relationship to avoid N+1

**Frontend:**
- Section component that can be embedded in mission detail
- Reuse StatusFilter component from Story 6-3
- Card component for each candidature

### API Endpoint

```
GET /api/v1/producer/missions/{mission_id}/candidatures
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
      "message_motivation": "Je suis très motivée pour cette mission...", // truncated 150 chars
      "created_at": "2026-01-27T10:00:00Z",
      "face": {
        "id": 5,
        "display_name": "Marie Dupont",
        "profile_photo_url": "https://...",
        "category": "acteur",
        "city": "Cotonou",
        "tarif_horaire": 25000,
        "tarif_journalier": 150000
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
    "last_page": 2,
    "per_page": 15,
    "total": 25
  }
}

Response (401): Unauthenticated
Response (403): Not mission owner or not a Producer
Response (404): Mission not found
```

### Controller Pattern

```php
// Producer\CandidatureController
public function index(Request $request, Mission $mission): AnonymousResourceCollection
{
    $producer = $request->user()->userable;

    // Authorization: mission must belong to this Producer
    if ($mission->producer_id !== $producer->id) {
        abort(403, 'Cette mission ne vous appartient pas');
    }

    $query = Candidature::where('mission_id', $mission->id)
        ->with(['face', 'face.user'])
        ->latest();

    // Optional status filter
    if ($request->has('status') && $request->status !== '') {
        $status = CandidatureStatus::tryFrom($request->status);
        if ($status) {
            $query->where('status', $status);
        }
    }

    $candidatures = $query->paginate(15);

    return ProducerCandidatureResource::collection($candidatures);
}
```

### Resource Pattern

```php
// ProducerCandidatureResource
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'status' => $this->status?->value,
        'status_label' => $this->status?->label(),
        'message_motivation' => $this->message_motivation
            ? Str::limit($this->message_motivation, 150)
            : null,
        'created_at' => $this->created_at?->toIso8601String(),
        'face' => $this->whenLoaded('face', fn () => [
            'id' => $this->face->id,
            'display_name' => $this->face->display_name,
            'profile_photo_url' => $this->face->profile_photo_url,
            'category' => $this->face->category?->value,
            'city' => $this->face->ville,
            'tarif_horaire' => $this->face->tarif_horaire,
            'tarif_journalier' => $this->face->tarif_journalier,
        ]),
    ];
}
```

### Page Layout Design

```
┌─────────────────────────────────────────────────────────────────┐
│  Mission: Publicité TV pour marque cosmétique                   │
│  25 candidatures reçues                                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  [Tous] [En attente] [Acceptées] [Confirmées] [Refusées]        │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  [Photo] Marie Dupont                    🟡 En attente  │   │
│  │          Acteur • Cotonou                               │   │
│  │          💰 25 000 XOF/h • 150 000 XOF/jour             │   │
│  │                                                         │   │
│  │  "Je suis très motivée pour cette mission car..."       │   │
│  │                                        Postulé le 27/01 │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  [Photo] Jean Martin                     🟢 Acceptée    │   │
│  │          Influenceur • Porto-Novo                       │   │
│  │          💰 30 000 XOF/h • 180 000 XOF/jour             │   │
│  │                                                         │   │
│  │  "Avec mon expérience dans la pub cosmétique..."        │   │
│  │                                        Postulé le 25/01 │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│                        < 1 2 >                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Empty State Design

```
┌─────────────────────────────────────────────────────────────────┐
│  Mission: Court-métrage "Les Rêves"                             │
│  0 candidatures reçues                                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│                     👥                                          │
│                                                                 │
│              Aucune candidature reçue                           │
│                                                                 │
│     Les Faces n'ont pas encore postulé à cette mission.         │
│     Vérifiez que votre mission est bien visible.                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Existing Files Reference

**Backend files to create:**
- `app/Http/Controllers/Api/V1/Producer/CandidatureController.php`
- `app/Http/Resources/ProducerCandidatureResource.php`
- `tests/Feature/Candidature/ProducerViewCandidaturesTest.php`

**Backend files to modify:**
- `routes/api/producer.php` - Add GET route

**Frontend files to create:**
- `src/features/candidature/components/ProducerCandidaturesSection.vue`
- `src/features/candidature/components/ProducerCandidatureCard.vue`
- `src/features/candidature/composables/useProducerCandidatures.ts`

**Frontend files to modify:**
- `src/features/candidature/types/index.ts` - Add Producer types
- `src/features/candidature/services/candidatureApi.ts` - Add getMissionCandidatures
- `src/features/candidature/composables/index.ts` - Export new composable
- `src/features/candidature/components/index.ts` - Export new components
- Producer mission detail page (TBD) - Integrate candidatures section

### Previous Story Intelligence

**From Story 6-3 (Face View My Candidatures):**
- `FaceCandidatureResource` pattern to follow
- `useFaceCandidatures` composable pattern
- `StatusFilter` component can be reused
- `CandidatureCard` styling patterns
- Race condition protection with request ID tracking
- URL sync for filters

**From Story 6-2 (Face Apply to Mission):**
- `Candidature` model and relationships
- `CandidatureStatus` enum with `label()` method

**From Story 6-1 (Candidature Schema):**
- `candidatures` table structure
- Status workflow: pending → accepted → confirmed → in_progress → completed
- Also: pending → rejected

**From Producer MissionController:**
- Authorization pattern: check `$mission->producer_id !== $producer->id`
- Nested resource pattern in routes

### Git Intelligence (Recent Commits)

```
5d5fc82 docs: complete story 6-3 face view my candidatures
cb2f2fe feat(candidature): add face view my candidatures page
ca50e7f docs: complete story 6-2 face apply to mission
0b5251c feat(candidature): add apply to mission modal and integration
```

Story 6-3 established comprehensive candidature list patterns that this story mirrors for Producer.

### Test Scenarios

| Scenario | Input | Expected |
|----------|-------|----------|
| View mission candidatures | GET as mission owner | 200, paginated list |
| Empty list | Mission with no candidatures | 200, empty data array |
| Filter by status | ?status=pending | Only pending candidatures |
| Invalid status filter | ?status=invalid | Ignore filter, return all |
| Pagination | ?page=2 | Second page of results |
| Face access | GET as Face | 403 |
| Other Producer access | GET as different Producer | 403 |
| Unauthenticated | No token | 401 |
| Non-existent mission | Invalid mission_id | 404 |
| Candidatures include face | GET | Each has face object |

### Face Model Fields Reference

The Face model has these relevant fields for the summary:
- `id` - Face ID
- `profile_photo_path` (accessor: `profile_photo_url`) - Profile photo URL
- `ville` - City
- `category` - Enum (acteur, influenceur, créateur, mannequin, figurant)
- `user->name` or computed `display_name` - Full name
- `tarif_horaire` - Hourly rate in XOF (integer, nullable)
- `tarif_journalier` - Daily rate in XOF (integer, nullable)

### Dependencies

- **Depends on**: Story 6-1 (Candidature schema), Story 5-2 (Mission exists)
- **Blocks**: Story 6-5 (Producer View Candidate Profile), Story 6-6 (Accept), Story 6-7 (Reject)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 6.4 - Producer View Mission Candidatures, FR36]
- [Source: _bmad-output/project-context.md#API Response Format]
- [Source: backend/app/Models/Candidature.php - Candidature model]
- [Source: backend/app/Enums/CandidatureStatus.php - Status enum]
- [Source: backend/app/Http/Controllers/Api/V1/Producer/MissionController.php - Producer authorization pattern]
- [Source: _bmad-output/implementation-artifacts/6-3-face-view-my-candidatures.md - Previous story patterns]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

- Initial test file used Pest syntax but project uses PHPUnit - rewrote using class-based PHPUnit pattern
- Fixed unique constraint violations in tests by creating different Face instances for each candidature

### Completion Notes List

- Backend: Created Producer CandidatureController with authorization checks (Producer role + mission ownership)
- Backend: Created ProducerCandidatureResource with nested Face data including pricing (tarif_horaire, tarif_journalier)
- Backend: Added nested route GET /v1/producer/missions/{mission}/candidatures
- Backend: 14 comprehensive tests covering all acceptance criteria
- Frontend: Added FaceSummary, ProducerCandidature, ProducerCandidatureListResponse types
- Frontend: Added getMissionCandidatures method to candidatureApi service
- Frontend: Created useProducerCandidatures composable with race condition protection
- Frontend: Created ProducerCandidatureCard component (used Gemini MCP for initial design, then refined)
- Frontend: Created ProducerCandidaturesSection component with loading, error, empty states
- Frontend: Created ProducerMissionCandidaturesPage.vue for dedicated candidatures view
- Frontend: Added routes: producer-mission-candidatures and producer-candidate-profile (placeholder)
- Frontend: Modified MissionCard to emit viewCandidatures event on candidatures count click
- Frontend: Modified MissionsListPage to handle viewCandidatures navigation
- Reused existing StatusFilter component from Story 6-3
- All TypeScript checks pass
- All 85 backend tests pass

### Code Review Fixes (AI-Review)

**H1 (High): Controller missing Form Request validation**
- Created `IndexMissionCandidaturesRequest` FormRequest class
- Updated CandidatureController to use Form Request
- Status filter now properly validated (422 for invalid values instead of silent ignore)
- Updated test to expect 422 for invalid status

**M1 (Medium): Placeholder route pointed to wrong component**
- Created `CandidateProfilePlaceholder.vue` page showing "Coming Soon" message
- Updated router to use correct placeholder component

**M2 (Medium): initials computed crash on empty name**
- Added `.filter(n => n.length > 0)` before `.map()` and fallback to '?' if empty

**M3 (Medium): Missing NaN missionId validation**
- Added validation check for invalid/NaN missionId in page component

### File List

**Backend Files Created:**
- `backend/app/Http/Controllers/Api/V1/Producer/CandidatureController.php`
- `backend/app/Http/Resources/ProducerCandidatureResource.php`
- `backend/app/Http/Requests/Producer/IndexMissionCandidaturesRequest.php` (Code Review)
- `backend/tests/Feature/Candidature/ProducerViewCandidaturesTest.php`

**Backend Files Modified:**
- `backend/routes/api/producer.php` - Added candidatures route

**Frontend Files Created:**
- `frontend/src/features/candidature/components/ProducerCandidatureCard.vue`
- `frontend/src/features/candidature/components/ProducerCandidaturesSection.vue`
- `frontend/src/features/candidature/composables/useProducerCandidatures.ts`
- `frontend/src/pages/producer/candidature/ProducerMissionCandidaturesPage.vue`
- `frontend/src/pages/producer/candidature/CandidateProfilePlaceholder.vue` (Code Review)

**Frontend Files Modified:**
- `frontend/src/features/candidature/types/index.ts` - Added Producer types
- `frontend/src/features/candidature/services/candidatureApi.ts` - Added getMissionCandidatures
- `frontend/src/features/candidature/composables/index.ts` - Export new composable
- `frontend/src/features/candidature/components/index.ts` - Export new components
- `frontend/src/features/mission/components/MissionCard.vue` - Added viewCandidatures emit
- `frontend/src/pages/producer/mission/MissionsListPage.vue` - Handle viewCandidatures navigation
- `frontend/src/router/index.ts` - Added producer-mission-candidatures and producer-candidate-profile routes
