# Story 4.4: Producer Profile Display

Status: done

## Story

As a **Face or visitor**,
I want **to view a Producer's complete profile**,
So that **I can evaluate their credibility before applying to their missions**.

## Acceptance Criteria

1. **Given** I am viewing a Producer's profile (logged in or visitor), **When** the page loads, **Then** I see their profile photo (or placeholder), display name (agency name or "First Last"), and bio

2. **Given** the Producer is an Agency, **When** I view their profile, **Then** I also see their agency logo displayed prominently alongside their name

3. **Given** the Producer is a Particulier, **When** I view their profile, **Then** no logo section is displayed (only profile photo)

4. **Given** the Producer has completed missions with ratings (future feature), **When** I view their profile, **Then** I see their average rating displayed (show placeholder text: "Aucune note pour le moment" for MVP)

5. **Given** I want to see the Producer's activity, **When** I view their profile, **Then** I see how many missions they have posted (show 0 for MVP since missions table doesn't exist yet)

6. **Given** I navigate to a non-existent Producer ID, **When** the page loads, **Then** I see a 404 error page or friendly "Producer not found" message

7. **Given** I am a logged-in Face, **When** I view a Producer's profile from a mission detail (future), **Then** the profile page provides context about who the Producer is

## Tasks / Subtasks

### Task 1: Create Public Producer Show Endpoint (AC: #1, #2, #3, #6)

- [x] 1.1 Create `app/Http/Controllers/Api/V1/Public/ProducerController.php`
- [x] 1.2 Implement `show($id)` method - return Producer data using ProducerResource
- [x] 1.3 Handle 404 for non-existent Producer (use `findOrFail` pattern)
- [x] 1.4 No authentication required - this is a PUBLIC endpoint

### Task 2: Create PublicProducerResource (AC: #1, #2, #3, #4, #5)

- [x] 2.1 Create `app/Http/Resources/PublicProducerResource.php`
- [x] 2.2 Include fields: `id`, `type`, `display_name`, `bio`, `profile_photo_url`, `thumbnail_url`
- [x] 2.3 Include `agency_name` (null for particulier)
- [x] 2.4 Include `agency_logo_url`, `agency_logo_thumbnail_url` (null for particulier)
- [x] 2.5 Add `missions_count` field (hardcode 0 for MVP - no missions table yet)
- [x] 2.6 Add `average_rating` field (hardcode null for MVP - no ratings table yet)
- [x] 2.7 Add `ratings_count` field (hardcode 0 for MVP)
- [x] 2.8 Add `member_since` field using `created_at->translatedFormat('F Y')` in French locale

### Task 3: Add Public API Route (AC: #1, #6)

- [x] 3.1 Create or update `routes/api/public.php` for public routes
- [x] 3.2 Add route: `GET /api/v1/public/producers/{id}` → `PublicProducerController@show`
- [x] 3.3 Include route in `routes/api.php` if separate file created
- [x] 3.4 No auth middleware on public routes

### Task 4: Create Backend Tests (AC: #1, #2, #3, #6)

- [x] 4.1 Create `tests/Feature/Public/ProducerProfileTest.php`
- [x] 4.2 Test: Unauthenticated user can view Producer profile (200)
- [x] 4.3 Test: Authenticated Face can view Producer profile (200)
- [x] 4.4 Test: Response includes all expected fields for Agency producer
- [x] 4.5 Test: Response includes all expected fields for Particulier producer
- [x] 4.6 Test: Agency producer has agency_logo_url, Particulier has null
- [x] 4.7 Test: Non-existent Producer returns 404
- [x] 4.8 Test: Response includes missions_count = 0 (MVP placeholder)
- [x] 4.9 Test: Response includes average_rating = null (MVP placeholder)

### Task 5: Create Frontend Types (AC: #1, #4, #5)

- [x] 5.1 Create `frontend/src/features/public/types.ts` (if not exists)
- [x] 5.2 Add `PublicProducer` interface with all fields from PublicProducerResource
- [x] 5.3 Add `PublicProducerResponse` interface for API response

### Task 6: Create Public Producer API Service (AC: #1)

- [x] 6.1 Create `frontend/src/features/public/services/publicApi.ts`
- [x] 6.2 Add `getProducer(id: number)` method
- [x] 6.3 Use unauthenticated axios call (no auth headers needed)

### Task 7: Create usePublicProducer Composable (AC: #1, #6)

- [x] 7.1 Create `frontend/src/features/public/composables/usePublicProducer.ts`
- [x] 7.2 Include refs: `producer`, `isLoading`, `error`, `notFound`
- [x] 7.3 Implement `fetchProducer(id)` function
- [x] 7.4 Handle 404 errors gracefully (set notFound flag)
- [x] 7.5 Handle network errors with French error messages

### Task 8: Create ProducerProfilePage Component (AC: #1, #2, #3, #4, #5, #6)

- [x] 8.1 Create `frontend/src/pages/public/ProducerProfilePage.vue`
- [x] 8.2 ~~Use Gemini MCP `create_frontend`~~ Created manually with premium design using Tailwind
- [x] 8.3 Display profile photo prominently (use thumbnail_url)
- [x] 8.4 Display display_name as main heading
- [x] 8.5 Display bio in a readable format (handle null case)
- [x] 8.6 For Agency: Show agency logo next to agency_name
- [x] 8.7 Show member_since date in French format
- [x] 8.8 Show missions_count with label "Missions publiées"
- [x] 8.9 Show rating section: "Aucune note pour le moment" (MVP) or stars when available
- [x] 8.10 Handle loading state with skeleton/spinner
- [x] 8.11 Handle 404/not found with friendly message
- [x] 8.12 Handle network errors with retry option
- [x] 8.13 Responsive design (mobile-first per architecture)

### Task 9: Add Public Route (AC: #1, #6)

- [x] 9.1 Add route to `frontend/src/router/index.ts`
- [x] 9.2 Ensure route works for both authenticated and unauthenticated users

### Task 10: Create Frontend Tests (AC: #1, #2, #3, #6)

- [x] 10.1 Create `frontend/src/features/public/composables/__tests__/usePublicProducer.spec.ts`
- [x] 10.2 Test: Fetches producer data successfully
- [x] 10.3 Test: Sets notFound flag on 404
- [x] 10.4 Test: Sets error on network failure
- [x] 10.5 Create `frontend/src/pages/public/__tests__/ProducerProfilePage.spec.ts`
- [x] 10.6 Test: Displays all producer info correctly
- [x] 10.7 Test: Shows logo section only for Agency
- [x] 10.8 Test: Shows not found message for invalid producer
- [x] 10.9 Test: Shows loading state while fetching

## Dev Notes

### Architecture Requirements

- **PUBLIC API**: This is a PUBLIC endpoint - no auth required
- Route boundary: `/api/v1/public/*` per architecture.md
- Frontend route: `/producers/:id` - accessible without login

### Key Technical Patterns

1. **Public Controller Location**: `app/Http/Controllers/Api/V1/Public/ProducerController.php`
2. **Resource Pattern**: Create separate `PublicProducerResource` - don't reuse authenticated `ProducerResource` (may expose different fields in future)
3. **Route File**: Consider creating `routes/api/public.php` for all public endpoints (Epic 11 will add more)
4. **404 Handling**: Use `Producer::findOrFail($id)` - Laravel returns 404 automatically

### MVP Placeholder Strategy

Since Epic 5 (Missions) and Epic 8 (Ratings) are still in backlog, this story must:
- Show `missions_count: 0` hardcoded
- Show `average_rating: null` and "Aucune note pour le moment" in UI
- Architecture should make it easy to add real data later (just update resource)

### Frontend Public Feature Module

Create new feature module structure:
```
frontend/src/features/public/
├── components/        # Reusable public components
├── composables/       # usePublicProducer, etc.
├── services/          # publicApi.ts
└── types.ts           # PublicProducer, etc.
```

### Project Structure Notes

- Backend: Follow existing pattern in `app/Http/Controllers/Api/V1/Producer/ProfileController.php`
- Frontend: New public feature module at `frontend/src/features/public/`
- API response format: Use envelope `{ data: {...} }` per architecture
- Use Tailwind with `weact-*` brand colors (not hardcoded hex)

### References

- [Source: docs/planning-artifacts/architecture.md#API-Route-Boundaries] - Public routes at `/api/v1/public/*`
- [Source: _bmad-output/planning-artifacts/epics.md#Story-4.4] - Story requirements
- [Source: backend/app/Http/Resources/ProducerResource.php] - Existing resource pattern
- [Source: backend/app/Models/Producer.php] - Model with all accessors
- [Source: frontend/src/pages/producer/ProfileEditPage.vue] - Existing profile page pattern

### Previous Story Learnings (4-3 Agency Logo)

- Use `Attribute::make()` pattern for computed accessors (already implemented)
- ProducerResource already includes all fields needed (`profile_photo_url`, `thumbnail_url`, `agency_logo_url`, etc.)
- French locale for dates: Use `Carbon::setLocale('fr')` or format manually
- Follow pattern of self-contained composables

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

N/A

### Completion Notes List

- All 10 backend tests pass (10 tests, 58 assertions)
- All 37 frontend tests pass (14 composable + 23 page tests)
- Public API endpoint at `/api/v1/public/producers/{id}` working
- Frontend route at `/producers/:id` accessible without auth
- MVP placeholders in place for missions_count and average_rating

### Change Log

| Date | Author | Change |
|------|--------|--------|
| 2026-01-19 | SM Agent | Story created - ready-for-dev |
| 2026-01-19 | Dev Agent | All tasks implemented - status: review |

### File List

**Backend:**
- `backend/app/Http/Controllers/Api/V1/Public/ProducerController.php` (NEW)
- `backend/app/Http/Resources/PublicProducerResource.php` (NEW)
- `backend/routes/api/public.php` (NEW)
- `backend/routes/api.php` (MODIFIED - added public routes include)
- `backend/tests/Feature/Public/ProducerProfileTest.php` (NEW)

**Frontend:**
- `frontend/src/features/public/types.ts` (NEW)
- `frontend/src/features/public/services/publicApi.ts` (NEW)
- `frontend/src/features/public/composables/usePublicProducer.ts` (NEW)
- `frontend/src/features/public/composables/__tests__/usePublicProducer.spec.ts` (NEW)
- `frontend/src/pages/public/ProducerProfilePage.vue` (NEW)
- `frontend/src/pages/public/__tests__/ProducerProfilePage.spec.ts` (NEW)
- `frontend/src/router/index.ts` (MODIFIED - added public route)
