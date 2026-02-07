# Story 11.10: Slug-Based Public URLs

Status: done

## Story

As a **visitor**,
I want **public pages to use human-readable slugs (username for faces, title-based slug for missions) instead of numeric IDs in URLs**,
So that **URLs are SEO-friendly, shareable, and don't expose sequential internal IDs (preventing enumeration)**.

## Acceptance Criteria

1. **Given** I navigate to `/faces/johndoe` **When** the page loads **Then** I see the public profile for the face with username `johndoe`, identical to the previous `/faces/42` behavior

2. **Given** I navigate to `/missions/casting-publicite-mtn` **When** the page loads **Then** I see the mission detail for the mission with slug `casting-publicite-mtn`, identical to the previous `/missions/42` behavior

3. **Given** I am on the `/faces` list page **When** I click on a FaceCard **Then** the URL navigates to `/faces/{username}` (not `/faces/{id}`)

4. **Given** I am on the `/missions` list page **When** I click on a MissionCard **Then** the URL navigates to `/missions/{slug}` (not `/missions/{id}`)

5. **Given** a face with username `johndoe` exists **When** I access `/faces/johndoe` via direct URL **Then** the page loads correctly (deep-linking works)

6. **Given** a mission with slug `casting-publicite-mtn` exists **When** I access `/missions/casting-publicite-mtn` via direct URL **Then** the page loads correctly (deep-linking works)

7. **Given** I navigate to `/faces/nonexistent-user` **When** the API responds 404 **Then** I see the existing "not found" state (no regression)

8. **Given** I navigate to `/missions/nonexistent-slug` **When** the API responds 404 **Then** I see the existing "not found" state (no regression)

9. **Given** a producer creates a mission with title "Casting Publicité MTN" **When** the mission is saved **Then** a unique slug `casting-publicite-mtn` is generated automatically

10. **Given** two missions have the same title "Casting Spot TV" **When** both are saved **Then** the slugs are unique: `casting-spot-tv` and `casting-spot-tv-2`

11. **Given** existing missions in the database have no slug **When** the migration runs **Then** all existing missions receive auto-generated slugs from their titles

12. **Given** the `faces.username` column **When** I check the database **Then** it has a unique constraint and an index for efficient lookups

13. **Given** the old URL format `/faces/42` or `/missions/42` **When** someone accesses it **Then** the backend returns 404 (numeric-only routes are removed from public API)

---

## Tasks / Subtasks

### Backend Tasks

- [x] Task 1: Add `slug` column to missions table (AC: #9, #10, #11)
  - [x] Create migration `add_slug_to_missions_table`
  - [x] Add `slug` column: `string(200)->unique()->nullable()` (nullable initially for migration)
  - [x] Add index on `slug` for fast lookups
  - [x] In migration `up()`: generate slugs for all existing missions using `Str::slug(titre)` + collision handling
  - [x] After backfill, alter column to `->nullable(false)` (make NOT NULL)
  - [x] Add `slug` to Mission model `$fillable` array

- [x] Task 2: Add unique constraint + index to `faces.username` (AC: #12)
  - [x] Create migration `add_unique_constraint_to_faces_username`
  - [x] Add unique index on `username` column
  - [x] Verify no duplicate usernames exist in DB before migration (add check in migration)

- [x] Task 3: Add slug auto-generation on Mission create/update (AC: #9, #10)
  - [x] Add `boot()` method or Observer to Mission model
  - [x] On `creating` event: generate slug from `titre` using `Str::slug()`
  - [x] Handle collisions: if slug exists, append `-2`, `-3`, etc.
  - [x] On `updating` event: regenerate slug ONLY if `titre` changed (preserve existing slugs)
  - [x] Add helper method `generateUniqueSlug(string $title, ?int $excludeId = null): string`

- [x] Task 4: Update backend public routes to use slug/username (AC: #1, #2, #5, #6, #13)
  - [x] Change `Route::get('/faces/{id}', ...)->whereNumber('id')` → `Route::get('/faces/{username}', ...)`
  - [x] Change `Route::get('/missions/{id}', ...)->whereNumber('id')` → `Route::get('/missions/{slug}', ...)`
  - [x] Update `FaceController::show()`: `$id` param → `string $username`, query by `where('username', $username)` instead of `find($id)`
  - [x] Update `MissionController::show()`: `$id` param → `string $slug`, query by `where('slug', $slug)` instead of `find($id)`
  - [x] Keep `->where('status', Published)` filter BEFORE the slug lookup (same security pattern)

- [x] Task 5: Include slug/username in API list responses (AC: #3, #4)
  - [x] Verify `PublicFaceResource` already includes `username` field — if not, add it
  - [x] Add `slug` field to `PublicMissionResource`
  - [x] Verify both list and detail responses include these fields

- [x] Task 6: Write/update backend tests (AC: #1-#13)
  - [x] Update `PublicFaceProfileTest`: change route from `/faces/{id}` to `/faces/{username}`
  - [x] Update `PublicMissionDetailTest`: change route from `/missions/{id}` to `/missions/{slug}`
  - [x] Add test: slug auto-generation on mission create
  - [x] Add test: slug uniqueness (collision handling)
  - [x] Add test: slug regeneration only when title changes
  - [x] Add test: migration generates slugs for existing missions
  - [x] Add test: username unique constraint works
  - [x] Update existing list tests if API response fields changed

### Frontend Tasks

- [x] Task 7: Update `PublicMission` type and API service (AC: #2, #4, #6, #8)
  - [x] Add `slug: string` to `PublicMission` interface in `publicMissionsApi.ts`
  - [x] Change `fetchPublicMissionDetail(id: number)` → `fetchPublicMissionDetail(slug: string)`
  - [x] Update API URL: `/v1/public/missions/${id}` → `/v1/public/missions/${slug}`

- [x] Task 8: Update `PublicFace` type and API service (AC: #1, #3, #5, #7)
  - [x] Verify `username` field exists in `PublicFace` interface in `publicFacesApi.ts` — if not, add it
  - [x] Change `fetchPublicFaceProfile(id: number)` → `fetchPublicFaceProfile(username: string)`
  - [x] Update API URL: `/v1/public/faces/${id}` → `/v1/public/faces/${username}`

- [x] Task 9: Update frontend router (AC: #1, #2, #5, #6)
  - [x] Change `/faces/:id` → `/faces/:username` in router/index.ts
  - [x] Change `/missions/:id` → `/missions/:slug` in router/index.ts
  - [x] Route names remain `public-face-profile` and `public-mission-detail` (no change)

- [x] Task 10: Update detail view components (AC: #1, #2)
  - [x] `PublicFaceProfileView.vue`: change `parseInt(route.params.id)` → `route.params.username as string`, pass to `fetchFace(username)`
  - [x] `PublicMissionDetailView.vue`: change `parseInt(route.params.id)` → `route.params.slug as string`, pass to `fetchMission(slug)`
  - [x] Remove `Number.isNaN` guard (slugs are strings, not parsed as int)

- [x] Task 11: Update card components to link with slug/username (AC: #3, #4)
  - [x] `FaceCard.vue`: change `` `/faces/${face.id}` `` → `` `/faces/${face.username}` ``
  - [x] `PublicMissionCard.vue`: change `` `/missions/${mission.id}` `` → `` `/missions/${mission.slug}` ``

- [x] Task 12: Update composables (AC: #1, #2)
  - [x] `useFaceProfile.ts`: change `fetchFace(id: number)` → `fetchFace(username: string)`
  - [x] `useMissionDetail.ts`: change `fetchMission(id: number)` → `fetchMission(slug: string)`

### Testing Tasks

- [x] Task 13: Update frontend tests for slug-based routing
  - [x] Update `useFaceProfile.spec.ts`: mock API calls with username instead of ID
  - [x] Update `useMissionDetail.spec.ts`: mock API calls with slug instead of ID
  - [x] Update `PublicFaceProfileView.spec.ts`: set `mockParams.username` instead of `mockParams.id`
  - [x] Update `PublicMissionDetailView.spec.ts`: set `mockParams.slug` instead of `mockParams.id`
  - [x] Update `FaceCard.spec.ts`: verify link uses `face.username` not `face.id`
  - [x] Update `PublicMissionCard.spec.ts` (if exists): verify link uses `mission.slug` not `mission.id`
  - [x] Remove tests for non-numeric ID validation (no longer relevant — slugs are strings)
  - [x] Add test: mission card links include slug field

---

## Dev Notes

### CRITICAL: Slug Generation Strategy

**For Missions** — auto-generate from `titre`:
```php
use Illuminate\Support\Str;

// In Mission model boot() or Observer:
protected static function boot(): void
{
    parent::boot();

    static::creating(function (Mission $mission) {
        if (empty($mission->slug)) {
            $mission->slug = self::generateUniqueSlug($mission->titre);
        }
    });

    static::updating(function (Mission $mission) {
        if ($mission->isDirty('titre')) {
            $mission->slug = self::generateUniqueSlug($mission->titre, $mission->id);
        }
    });
}

public static function generateUniqueSlug(string $title, ?int $excludeId = null): string
{
    $slug = Str::slug($title);
    $original = $slug;
    $counter = 2;

    $query = static::where('slug', $slug);
    if ($excludeId) {
        $query->where('id', '!=', $excludeId);
    }

    while ($query->exists()) {
        $slug = "{$original}-{$counter}";
        $query = static::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $counter++;
    }

    return $slug;
}
```

**For Faces** — use existing `username` column (already unique per user, already generated at registration). No slug generation needed.

### CRITICAL: Migration Must Backfill Existing Records

The migration MUST generate slugs for all existing missions. Use a chunked query to avoid memory issues:

```php
public function up(): void
{
    Schema::table('missions', function (Blueprint $table) {
        $table->string('slug', 200)->nullable()->after('titre');
        $table->index('slug');
    });

    // Backfill existing missions
    Mission::query()->whereNull('slug')->chunkById(100, function ($missions) {
        foreach ($missions as $mission) {
            $mission->slug = Mission::generateUniqueSlug($mission->titre, $mission->id);
            $mission->saveQuietly(); // Skip events to avoid infinite loop
        }
    });

    // Make NOT NULL after backfill
    Schema::table('missions', function (Blueprint $table) {
        $table->string('slug', 200)->nullable(false)->unique()->change();
    });
}
```

### CRITICAL: Backend Route Parameter Changes

**Before:**
```php
Route::get('/faces/{id}', [FaceController::class, 'show'])->whereNumber('id');
Route::get('/missions/{id}', [MissionController::class, 'show'])->whereNumber('id');
```

**After:**
```php
Route::get('/faces/{username}', [FaceController::class, 'show']);
Route::get('/missions/{slug}', [MissionController::class, 'show']);
```

Note: Remove `whereNumber()` — slugs are strings. Laravel will match any string.

### CRITICAL: Controller Query Changes

**FaceController::show() — Before:**
```php
public function show(int $id): JsonResponse
{
    $face = Face::with('photos')->find($id);
```

**FaceController::show() — After:**
```php
public function show(string $username): JsonResponse
{
    $face = Face::query()
        ->where('username', $username)
        ->with('photos')
        ->first();
```

**MissionController::show() — Before:**
```php
public function show(int $id): JsonResponse
{
    $mission = $this->publishedWithProducer()->find($id);
```

**MissionController::show() — After:**
```php
public function show(string $slug): JsonResponse
{
    $mission = $this->publishedWithProducer()
        ->where('slug', $slug)
        ->first();
```

### Frontend View Changes

**Before** (PublicMissionDetailView.vue):
```typescript
const missionId = computed(() => {
  const id = route.params.id
  if (typeof id !== 'string') return null
  const parsed = parseInt(id, 10)
  return Number.isNaN(parsed) ? null : parsed
})
```

**After:**
```typescript
const missionSlug = computed(() => {
  const slug = route.params.slug
  return typeof slug === 'string' && slug.length > 0 ? slug : null
})
```

Same pattern for `PublicFaceProfileView.vue` using `route.params.username`.

### PublicMissionResource Must Include `slug`

```php
// In PublicMissionResource::toArray()
'slug' => $this->slug,  // ADD this field
```

### Verify PublicFaceResource Includes `username`

Check if the existing `PublicFaceResource` already returns `username`. If not, add:
```php
'username' => $this->username,
```

### MissionFactory Must Generate Slugs

```php
// In MissionFactory::definition()
'slug' => fake()->unique()->slug(3, false),
```

Or better, use the model's `creating` boot event which will auto-generate from `titre`.

### File Structure

```
backend/
├── app/Models/
│   └── Mission.php                              # MODIFY (add slug to fillable, add boot() for auto-generation)
├── app/Http/Controllers/Api/V1/Public/
│   ├── FaceController.php                       # MODIFY (show() takes username instead of id)
│   └── MissionController.php                    # MODIFY (show() takes slug instead of id)
├── app/Http/Resources/
│   ├── PublicMissionResource.php                # MODIFY (add slug field)
│   └── PublicFaceResource.php                   # VERIFY (username field present)
├── database/migrations/
│   ├── XXXX_add_slug_to_missions_table.php      # NEW
│   └── XXXX_add_unique_to_faces_username.php    # NEW
├── routes/api/
│   └── public.php                               # MODIFY (route params)
└── tests/Feature/Public/
    ├── PublicFaceProfileTest.php                 # MODIFY (use username in routes)
    ├── PublicMissionDetailTest.php               # MODIFY (use slug in routes)
    └── MissionSlugTest.php                       # NEW (slug generation + uniqueness)

frontend/src/
├── features/public/
│   ├── components/
│   │   ├── FaceCard.vue                         # MODIFY (link uses username)
│   │   ├── PublicMissionCard.vue                # MODIFY (link uses slug)
│   │   └── __tests__/
│   │       ├── FaceCard.spec.ts                 # MODIFY (verify username link)
│   │       └── PublicMissionCard.spec.ts        # MODIFY (verify slug link)
│   ├── composables/
│   │   ├── useFaceProfile.ts                    # MODIFY (param: string)
│   │   ├── useMissionDetail.ts                  # MODIFY (param: string)
│   │   └── __tests__/
│   │       ├── useFaceProfile.spec.ts           # MODIFY (use username)
│   │       └── useMissionDetail.spec.ts         # MODIFY (use slug)
│   └── services/
│       ├── publicFacesApi.ts                    # MODIFY (param: string, add username to type)
│       └── publicMissionsApi.ts                 # MODIFY (param: string, add slug to type)
├── views/
│   ├── PublicFaceProfileView.vue                # MODIFY (use route.params.username)
│   ├── PublicMissionDetailView.vue              # MODIFY (use route.params.slug)
│   └── __tests__/
│       ├── PublicFaceProfileView.spec.ts        # MODIFY (mockParams.username)
│       └── PublicMissionDetailView.spec.ts      # MODIFY (mockParams.slug)
└── router/
    └── index.ts                                 # MODIFY (route param names)
```

### Dependencies

- `Illuminate\Support\Str` — already available (Laravel core). Provides `Str::slug()`.
- No new external dependencies needed.

### CRITICAL: Test Backward Compatibility

This story modifies many existing files. All existing tests must be updated to use slug/username instead of numeric IDs. Run the full test suite after every task to catch regressions early.

Existing test files that MUST be updated:
- `PublicFaceProfileTest.php` (backend)
- `PublicMissionDetailTest.php` (backend)
- `PublicMissionsListTest.php` (backend — if response fields change)
- `PublicFacesListTest.php` (backend — if response fields change)
- `useFaceProfile.spec.ts` (frontend)
- `useMissionDetail.spec.ts` (frontend)
- `PublicFaceProfileView.spec.ts` (frontend)
- `PublicMissionDetailView.spec.ts` (frontend)
- `FaceCard.spec.ts` (frontend)

---

## Previous Story Intelligence (11-7, 11-9)

### Learnings Applied

1. **Backend detail pattern**: `Public\MissionController::show()` uses `publishedWithProducer()` shared query method — extend this for slug lookup
2. **Frontend composable pattern**: `useMissionDetail` / `useFaceProfile` use `{ success, data, notFound, error }` result pattern — keep this, just change param type
3. **Route name stability**: Route names (`public-mission-detail`, `public-face-profile`) are referenced by card components — do NOT change route names, only change param names
4. **Code review learnings**: Always filter by published status BEFORE looking up by identifier (prevents leaking non-published records)
5. **Test pattern**: Each detail endpoint needs tests for: valid lookup, not-found, non-published states, no auth required, correct response fields

### Relevant Commits

- `6f53f87` - feat(public): add public mission detail page with review fixes (Story 11-7)
- `cf0fe0b` - fix(review): address code review findings for Story 11-9
- `6fd3b80` - feat(public): add search functionality to public Faces list (Story 11-9)

---

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

None

### Completion Notes List

- Task 2 (faces.username unique constraint) was already done — existing migration `2026_01_08_015054` already has the unique index
- Task 3 (slug auto-generation on Mission) was already implemented in a previous session
- Fixed a leftover `missionId` reference in `PublicMissionDetailView.vue` handleRetry() — should have been `missionSlug`
- Added `username` to `PublicFaceResource` and `PublicFaceProfileResource` (was missing)
- Added `slug` to `PublicMissionResource`
- Updated `PublicFacesView.spec.ts` and `usePaginatedFaces.spec.ts` mock data to include `username` field
- All backend tests: 112 passing (104 public + 8 slug tests)
- All frontend tests: 1607 passing across 87 test files
- TypeScript: clean (vue-tsc --noEmit passes)

### File List

**Backend (Modified):**
- `backend/database/migrations/2026_02_07_140136_add_slug_to_missions_table.php` — NEW: migration with backfill
- `backend/app/Http/Controllers/Api/V1/Public/MissionController.php` — show() uses slug
- `backend/app/Http/Controllers/Api/V1/Public/FaceController.php` — show() uses username
- `backend/routes/api/public.php` — route params changed
- `backend/app/Http/Resources/PublicMissionResource.php` — added slug field
- `backend/app/Http/Resources/PublicFaceResource.php` — added username field
- `backend/app/Http/Resources/PublicFaceProfileResource.php` — added username field
- `backend/tests/Feature/Public/MissionSlugTest.php` — NEW: 8 slug tests
- `backend/tests/Feature/Public/PublicMissionDetailTest.php` — updated for slug routes
- `backend/tests/Feature/Public/PublicFaceProfileTest.php` — updated for username routes
- `backend/tests/Feature/Public/PublicFacesListTest.php` — added username to structure
- `backend/tests/Feature/Public/PublicMissionsListTest.php` — added slug to structure

**Frontend (Modified):**
- `frontend/src/features/public/services/publicMissionsApi.ts` — slug in type + API
- `frontend/src/features/public/services/publicFacesApi.ts` — username in type + API
- `frontend/src/router/index.ts` — :slug and :username params
- `frontend/src/views/PublicMissionDetailView.vue` — missionSlug
- `frontend/src/views/PublicFaceProfileView.vue` — faceUsername
- `frontend/src/features/public/components/FaceCard.vue` — link uses username
- `frontend/src/features/public/components/PublicMissionCard.vue` — link uses slug
- `frontend/src/features/public/composables/useMissionDetail.ts` — slug param
- `frontend/src/features/public/composables/useFaceProfile.ts` — username param

**Frontend Tests (Modified):**
- `frontend/src/features/public/composables/__tests__/useMissionDetail.spec.ts`
- `frontend/src/features/public/composables/__tests__/useFaceProfile.spec.ts`
- `frontend/src/features/public/composables/__tests__/usePaginatedFaces.spec.ts`
- `frontend/src/views/__tests__/PublicMissionDetailView.spec.ts`
- `frontend/src/views/__tests__/PublicFaceProfileView.spec.ts`
- `frontend/src/views/__tests__/PublicFacesView.spec.ts`
- `frontend/src/features/public/components/__tests__/FaceCard.spec.ts`
- `frontend/src/features/public/components/__tests__/PublicMissionCard.spec.ts`
