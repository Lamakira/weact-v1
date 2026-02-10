# Story 12.8: Public Article Reading

Status: done

## Story

As a **visitor**,
I want **to read a full article**,
So that **I can learn from the content**.

## Acceptance Criteria

1. **AC1**: Given I send `GET /api/v1/public/articles/{slug}`, When the article exists and is published, Then a 200 response is returned with the full article data including `content`, wrapped in `{ data: {...}, message: "Article retrieved successfully" }`.

2. **AC2**: Given the article slug does not exist, When I request the detail, Then a 404 response is returned with `{ error: { code: "ARTICLE_NOT_FOUND", message: "Article non trouvé" } }`.

3. **AC3**: Given the article exists but has `status = draft`, When I request the detail via slug, Then a 404 response is returned (draft articles are never publicly visible).

4. **AC4**: Given the article detail response, When I inspect the returned fields, Then it contains: `id`, `title`, `slug`, `content` (full rich text), `excerpt`, `category` (value + label), `featured_image` (URL), `published_at`, `created_at`, `author_name` (admin name string). It must NOT contain: `status` (always published), `updated_at` (admin concern), `admin` object (email/id sensitive).

5. **AC5**: Given the detail response, When I inspect the response structure, Then there is NO `meta` key (single resource, not a list).

6. **AC6**: Given the endpoint is public, When I make a request without authentication, Then the response succeeds (no auth required). The endpoint inherits the existing `throttle:60,1` rate limit.

7. **AC7**: Given all endpoints work, Then backend tests cover: successful retrieval of published article with all fields, 404 for non-existent slug, 404 for draft article, response structure (no meta, no status, no admin object), content field is present (full rich text), author name is returned, no authentication required, correct error response format, rate limiting, success message.

## Tasks / Subtasks

- [x] Task 1: Create `PublicArticleDetailResource` (AC: #4, #5)
  - [x] 1.1 Create `app/Http/Resources/PublicArticleDetailResource.php`
  - [x] 1.2 Expose all fields from `PublicArticleResource` PLUS `content` (full rich text body)
  - [x] 1.3 Fields: `id`, `title`, `slug`, `content`, `excerpt`, `category` (value + label), `featured_image` (URL via `featured_image_url` accessor), `published_at` (ISO 8601), `created_at` (ISO 8601), `author_name` (`$this->admin?->name`)
  - [x] 1.4 Explicitly exclude: `status`, `updated_at`, `admin` object

- [x] Task 2: Add `show` method to public `ArticleController` (AC: #1, #2, #3)
  - [x] 2.1 Add `show(string $slug): JsonResponse` method to existing `app/Http/Controllers/Api/V1/Public/ArticleController.php`
  - [x] 2.2 Query: `Article::published()->with('admin')->where('slug', $slug)->first()`
  - [x] 2.3 If not found: return 404 with `{ error: { code: "ARTICLE_NOT_FOUND", message: "Article non trouvé" } }`
  - [x] 2.4 If found: return 200 with `{ data: new PublicArticleDetailResource($article), message: "Article retrieved successfully" }`
  - [x] 2.5 No `meta` key in response (single resource, not paginated)

- [x] Task 3: Add show route (AC: #6)
  - [x] 3.1 Add `GET /articles/{slug}` to `routes/api/public.php` inside the existing `v1/public` prefix group (inherits `throttle:60,1`)
  - [x] 3.2 Place route AFTER the `/articles` index route to avoid slug matching conflicts

- [x] Task 4: Write backend tests (AC: #7)
  - [x] 4.1 Test successful retrieval of published article with all expected fields
  - [x] 4.2 Test `content` field is present and contains the full article content
  - [x] 4.3 Test 404 for non-existent slug
  - [x] 4.4 Test 404 for draft article (unpublished articles are invisible)
  - [x] 4.5 Test response structure: no `meta` key, has `data` and `message`
  - [x] 4.6 Test response excludes `status`, `updated_at`, and `admin` object
  - [x] 4.7 Test `author_name` is returned as string (admin name, not full object)
  - [x] 4.8 Test 404 error response format: `{ error: { code: "ARTICLE_NOT_FOUND", message: "Article non trouvé" } }`
  - [x] 4.9 Test no authentication required (no token, still 200)
  - [x] 4.10 Test rate limiting (61 requests → 429)
  - [x] 4.11 Test success message is returned
  - [x] 4.12 Test category value/label format in detail response
  - [x] 4.13 Test featured image URL is returned correctly (with and without image)

## Dev Notes

### Scope Clarification (CRITICAL)

This is the **detail** endpoint for Story 12-8. It shows a single article's full content by slug. Key scope boundaries:

- **Story 12-7 (DONE)**: Paginated list — `GET /api/v1/public/articles` — uses `PublicArticleResource` (NO content)
- **Story 12-8 (THIS)**: Single article detail — `GET /api/v1/public/articles/{slug}` — uses `PublicArticleDetailResource` (WITH content)
- **Story 12-9**: Category filtering on the LIST endpoint — adds `category` query param to 12-7's endpoint

Do NOT modify the existing `PublicArticleResource` or `index()` method. This story ADDS a new resource and a new `show()` method.

### Endpoint Design

```
GET /api/v1/public/articles/{slug}
```

No authentication. No request body. No FormRequest needed (no query parameters to validate).

Success response (200):
```json
{
  "data": {
    "id": 1,
    "title": "Conseils pour votre premier casting",
    "slug": "conseils-pour-votre-premier-casting",
    "content": "<h2>Introduction</h2><p>Full rich text article body...</p>",
    "excerpt": "Découvrez les meilleures pratiques...",
    "category": {
      "value": "conseils-face",
      "label": "Conseils Face"
    },
    "featured_image": "http://localhost/storage/articles/featured/uuid.jpg",
    "published_at": "2026-01-15T10:00:00+00:00",
    "created_at": "2026-01-14T08:30:00+00:00",
    "author_name": "Admin Name"
  },
  "message": "Article retrieved successfully"
}
```

Error response (404):
```json
{
  "error": {
    "code": "ARTICLE_NOT_FOUND",
    "message": "Article non trouvé"
  }
}
```

### Why a separate `PublicArticleDetailResource`?

The existing `PublicArticleResource` (created in Story 12-7) intentionally excludes `content` because:
- The list endpoint returns many articles — including full content would be too heavy
- The detail endpoint returns ONE article — the `content` field is the whole point

The detail resource includes everything from the list resource PLUS `content`. Two approaches:

**Option A (Recommended): Standalone resource** — Copy the list resource fields and add `content`. Simple, no coupling.

**Option B: Extend `PublicArticleResource`** — Override `toArray()` calling `parent::toArray()` and merging `content`. Creates coupling.

Use Option A for simplicity and decoupling.

### Controller Pattern — Follow `MissionController::show()`

The existing `MissionController::show()` in `app/Http/Controllers/Api/V1/Public/` is the exact reference pattern:
- Takes `string $slug` parameter (NOT route model binding — manual query)
- Uses `published()` scope + `where('slug', $slug)->first()`
- Returns 404 with `{ error: { code, message } }` (NOT `abort(404)`)
- Returns 200 with `{ data: Resource, message: "..." }` (NO `meta` key)
- Eager loads relationships

**CRITICAL**: Do NOT use Laravel route model binding (`Article $article`) — it would return ALL articles including drafts. Use manual query with `published()` scope to ensure only published articles are accessible.

### Route Placement — AFTER Index Route

The route must be added AFTER the existing `/articles` index route:
```php
// Public Articles list (paginated)
Route::get('/articles', [ArticleController::class, 'index']);

// Public Article detail
Route::get('/articles/{slug}', [ArticleController::class, 'show']);
```

This prevents `{slug}` from matching the word "articles" in the index route.

### Existing Files to Extend

- `backend/app/Http/Controllers/Api/V1/Public/ArticleController.php` — add `show()` method
- `backend/routes/api/public.php` — add GET /articles/{slug} route

### New Files to Create

- `backend/app/Http/Resources/PublicArticleDetailResource.php`
- `backend/tests/Feature/Public/PublicArticleDetailTest.php`

### Existing Patterns to Follow

- Controller: `app/Http/Controllers/Api/V1/Public/MissionController.php` → `show()` method (slug lookup, 404 error format, response format)
- Resource: `app/Http/Resources/PublicArticleResource.php` → public-safe field selection (add `content` for detail)
- Route: `routes/api/public.php` → add route inside existing `v1/public` throttled group, after index
- Tests: `tests/Feature/Public/PublicMissionDetailTest.php` → public detail test structure (404 tests, field assertions, auth, rate limiting)
- Tests: `tests/Feature/Public/PublicArticlesListTest.php` → article-specific test helpers

### Error Handling Pattern (CRITICAL)

From `MissionController::show()`:
```php
if (! $article) {
    return response()->json([
        'error' => [
            'code' => 'ARTICLE_NOT_FOUND',
            'message' => 'Article non trouvé',
        ],
    ], 404);
}
```

Do NOT use `abort(404)` — use explicit JSON response to maintain consistent API error format.

### ArticleFactory States for Tests

- `published()` — status=Published, published_at=now()
- `draft()` — status=Draft, published_at=null
- Default: draft, no image, random category

### Previous Story Intelligence

From Story 12-7 implementation and code review:
- **`PublicArticleResource`** uses `$this->admin?->name` (null-safe operator) — NOT `whenLoaded()`. The code review (M2) flagged `whenLoaded` as returning `{}` when not loaded. Use the same null-safe pattern.
- **`featured_image_url`** accessor returns full URL or null — safe to use directly.
- **Category field** is an object with `value` and `label` (code review M3 added a test for this).
- **Eager loading**: Always call `->with('admin')` to avoid N+1.
- **Test file naming**: `PublicArticlesListTest.php` for list, so use `PublicArticleDetailTest.php` for detail.
- **14 tests (283 assertions)** pass for Story 12-7 — ensure no regressions.
- **3 pre-existing failures**: MissionSchemaTest + EmailVerificationTest (slug default value issue from Story 11-10) — NOT new.

### Git Intelligence

Recent commits show:
- Stories 12-2 through 12-7 each landed in 3 grouped commits: feat, test, docs
- Consistent commit message pattern: `feat(public):`, `test(public):`, `docs:`
- Branch: `feature/blog-resources/db-schema`

### References

- [Source: _bmad-output/planning-artifacts/epics.md — Epic 12, Story 12.8, FR66]
- [Source: _bmad-output/planning-artifacts/epics.md — Story 12.7 (FR65) and 12.9 (FR67) for scope boundary]
- [Source: _bmad-output/project-context.md — Technology Stack, API Response Format, Error Response Format]
- [Source: backend/app/Http/Controllers/Api/V1/Public/MissionController.php — show() method pattern (slug lookup, 404, response)]
- [Source: backend/app/Http/Controllers/Api/V1/Public/ArticleController.php — existing index() method to extend]
- [Source: backend/app/Http/Resources/PublicArticleResource.php — list resource (add content for detail)]
- [Source: backend/app/Http/Resources/PublicMissionResource.php — public resource pattern]
- [Source: backend/app/Models/Article.php — scopes, slug field, accessor, admin relationship]
- [Source: tests/Feature/Public/PublicMissionDetailTest.php — public detail test patterns (404, fields, auth)]
- [Source: tests/Feature/Public/PublicArticlesListTest.php — article test helpers]
- [Source: _bmad-output/implementation-artifacts/12-7-public-articles-list.md — previous story intelligence, code review findings]

## Senior Developer Review (AI)

**Review Date:** 2026-02-10
**Reviewer:** Claude Opus 4.6 (adversarial code review)
**Outcome:** Approve (after fixes)

**Git vs Story Discrepancies:** 0

### Action Items

- [x] **M1**: No test verifying ISO 8601 date format for `published_at`/`created_at` — Added `test_date_fields_are_in_iso_8601_format()` test
- [x] **M2**: No test verifying `admin_id` foreign key excluded from response — Added `assertArrayNotHasKey('admin_id', $data)` to test 4.6
- [ ] **L1**: Field duplication between `PublicArticleResource` and `PublicArticleDetailResource` (10 of 11 fields identical) — Accepted tradeoff per Dev Notes (Option A: standalone resource)
- [ ] **L2**: Content field returns raw HTML — XSS sanitization depends on admin write endpoint / frontend rendering — Cross-cutting concern, out of scope

### OWASP Top 10 Review

| Check | Result |
|---|---|
| A01 Broken Access Control | PASS — `published()` scope prevents access to drafts |
| A03 Injection | PASS — Eloquent parameterized query |
| A04 Insecure Design | PASS — Rate limited, single resource |
| A05 Security Misconfiguration | PASS — Error response doesn't leak internals |
| A07 Auth Failures | N/A — Intentionally public endpoint |

## Dev Agent Record

### Agent Model Used
Claude Opus 4.6

### Debug Log References
- All 14 tests pass (118 assertions) for Story 12-8
- Full regression suite: 1251 passed, 3 pre-existing failures (MissionSchemaTest + EmailVerificationTest slug issue from Story 11-10)
- No new regressions introduced
- Story 12-7 tests: 14 passed (283 assertions) — no regression

### Completion Notes List
- **Task 1**: Created `PublicArticleDetailResource` — standalone resource (Option A) exposing all fields from `PublicArticleResource` PLUS `content` (full rich text body). Uses null-safe operator `$this->admin?->name` per 12-7 code review finding M2. Excludes `status`, `updated_at`, `admin` object.
- **Task 2**: Added `show(string $slug): JsonResponse` method to existing public `ArticleController`. Follows `MissionController::show()` pattern exactly: manual slug lookup with `published()` scope + `with('admin')` eager loading, explicit JSON 404 response (not `abort()`), no `meta` key.
- **Task 3**: Added `GET /articles/{slug}` route to `routes/api/public.php` inside existing `v1/public` throttled group, placed AFTER the `/articles` index route.
- **Task 4**: 14 feature tests (118 assertions) covering: published article retrieval with all fields, content field presence, 404 for non-existent slug, 404 for draft article, no meta key, excluded fields, author name as string, error response format, no auth required, rate limiting, success message, category value/label, featured image URL (with and without), ISO 8601 date format.

### Change Log
- 2026-02-10: Code review fixes — M1: added ISO 8601 date format test, M2: added admin_id exclusion assertion (0H/2M/2L found, 2 fixed)
- 2026-02-09: Story 12-8 implementation complete — public article detail GET endpoint with full content, 13 tests

### File List

**Created:**
- `backend/app/Http/Resources/PublicArticleDetailResource.php`
- `backend/tests/Feature/Public/PublicArticleDetailTest.php`

**Modified:**
- `backend/app/Http/Controllers/Api/V1/Public/ArticleController.php` (added `show()` method + `PublicArticleDetailResource` import)
- `backend/routes/api/public.php` (added `GET /articles/{slug}` route)
