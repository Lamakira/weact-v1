# Story 12.10: Articles Frontend Pages

Status: done

## Story

As an **Admin**,
I want **frontend pages to manage articles (create, edit, list, delete, change status/category)**,
so that **I can manage blog content directly from the admin dashboard instead of raw API calls**.

As a **Visitor**,
I want **public pages to browse and read published articles**,
so that **I can discover educational content about the platform**.

The backend API is fully implemented (Stories 12-1 through 12-9). This story covers the **entire frontend** for article management and public reading.

## Acceptance Criteria

### Admin Pages

1. **AC1 — Admin Articles List Page**: Given I am an authenticated admin on `/admin/articles`, When the page loads, Then I see a paginated table of all articles (draft + published) showing: title, category badge, status badge (Draft/Published), author name, published_at date, and action buttons (edit, delete). The table supports search by title and filter by category/status.

2. **AC2 — Admin Article Create Page**: Given I am on `/admin/articles/create`, When I fill in title, content (rich text or textarea), excerpt, category (select from `conseils-face`, `guide-producteur`, `actualites`), optional featured image upload, and submit, Then the article is created via `POST /api/v1/admin/articles` and I am redirected to the articles list with a success message.

3. **AC3 — Admin Article Edit Page**: Given I am on `/admin/articles/:id/edit`, When the page loads, Then the form is pre-filled with the article's current data. When I modify fields and submit, Then the article is updated via `PUT /api/v1/admin/articles/:id` and I see a success message.

4. **AC4 — Admin Toggle Article Status**: Given I am viewing the articles list, When I click a status toggle button on an article row, Then the status is toggled (draft↔published) via `PATCH /api/v1/admin/articles/:id/status` with visual feedback.

5. **AC5 — Admin Delete Article**: Given I am viewing the articles list, When I click delete on an article, Then a ConfirmModal (danger variant) appears. On confirmation, the article is deleted via `DELETE /api/v1/admin/articles/:id` and removed from the list.

6. **AC6 — Admin Articles List Endpoint**: Given the admin backend has no `GET /articles` list endpoint, When this story is implemented, Then a new `index` method is added to `Admin\ArticleController` returning paginated articles (all statuses) with search/filter support, and a corresponding route `GET /api/v1/admin/articles` is registered.

### Public Pages

7. **AC7 — Public Articles List Page**: Given I am a visitor on `/ressources`, When the page loads, Then I see a grid/list of published articles showing: title, excerpt, category badge, featured image, published date. The list is paginated via `GET /api/v1/public/articles`.

8. **AC8 — Public Article Detail Page**: Given I click on an article from the list, When I navigate to `/ressources/:slug`, Then I see the full article content, author name, published date, category, and featured image via `GET /api/v1/public/articles/:slug`.

9. **AC9 — Public Articles Filter by Category**: Given I am on the public articles list, When I select a category filter, Then only articles in that category are displayed via the `?category=` query parameter.

### Frontend Infrastructure

10. **AC10 — Admin Articles API Service**: A `adminArticlesApi.ts` service is created under `features/admin/services/` with methods: `fetchArticles()`, `createArticle()`, `updateArticle()`, `deleteArticle()`, `updateArticleStatus()`, `updateArticleCategory()`.

11. **AC11 — Admin Articles Composable**: A `useAdminArticles.ts` composable is created under `features/admin/composables/` wrapping the API service with reactive state, loading, error handling.

12. **AC12 — Public Articles API Service & Composable**: A public articles service and composable are created for the visitor-facing pages.

13. **AC13 — Router Integration**: All new pages are registered in `router/index.ts`: admin articles under the admin layout children, public articles under the public layout.

### Tests

14. **AC14 — Frontend Tests**: Unit tests for admin articles composable (CRUD operations, error handling). Unit tests for public articles composable (list, detail, filter).

## Tasks / Subtasks

### Backend — Missing Admin List Endpoint

- [x] Task 1 — Add `index` method to `Admin\ArticleController` (AC: 6)
  - [x] 1.1 — Add `index(Request $request)` method with pagination, search by title, filter by category/status
  - [x] 1.2 — Register `GET /api/v1/admin/articles` route in `routes/api/admin.php`
  - [x] 1.3 — Write feature test for the admin articles list endpoint (9 tests, 53 assertions)

### Frontend — Admin API & Composable

- [x] Task 2 — Create admin articles API service (AC: 10)
  - [x] 2.1 — Create `frontend/src/features/admin/services/adminArticlesApi.ts`
  - [x] 2.2 — Define `Article` TypeScript interface matching `ArticleResource` response
  - [x] 2.3 — Implement all CRUD methods + status/category update

- [x] Task 3 — Create admin articles composable (AC: 11)
  - [x] 3.1 — Create `frontend/src/features/admin/composables/useAdminArticles.ts`
  - [x] 3.2 — Wrap API service with reactive state (articles, loading, error, pagination)

### Frontend — Admin Pages

- [x] Task 4 — Create `AdminArticlesListPage.vue` (AC: 1, 4, 5)
  - [x] 4.1 — Paginated table with columns: title, category badge, status badge, author, date, actions
  - [x] 4.2 — Search input and category/status filter dropdowns
  - [x] 4.3 — Status toggle button per row
  - [x] 4.4 — Delete button with ConfirmModal (danger variant)

- [x] Task 5 — Create `AdminArticleCreatePage.vue` (AC: 2)
  - [x] 5.1 — Form with: title, content (textarea), excerpt, category select, featured image upload
  - [x] 5.2 — Submit → POST API → redirect to list with success message

- [x] Task 6 — Create `AdminArticleEditPage.vue` (AC: 3)
  - [x] 6.1 — Load article data on mount, pre-fill form
  - [x] 6.2 — Submit → PUT API → success message

- [x] Task 7 — Register admin article routes (AC: 13)
  - [x] 7.1 — Add children routes under admin layout: `articles`, `articles/create`, `articles/:id/edit`

### Frontend — Public API & Composable

- [x] Task 8 — Create public articles API service and composable (AC: 12)
  - [x] 8.1 — Create `frontend/src/features/public/services/publicArticlesApi.ts`
  - [x] 8.2 — Create `frontend/src/features/public/composables/usePaginatedArticles.ts` and `useArticleDetail.ts`

### Frontend — Public Pages

- [x] Task 9 — Create `RessourcesView.vue` (AC: 7, 9)
  - [x] 9.1 — Grid/card layout of published articles
  - [x] 9.2 — Category filter tabs
  - [x] 9.3 — Pagination

- [x] Task 10 — Create `ArticleDetailView.vue` (AC: 8)
  - [x] 10.1 — Full article content display with DOMPurify sanitization, featured image, author, date, category

- [x] Task 11 — Register public article routes (AC: 13)
  - [x] 11.1 — Add routes: `/ressources` (name: `ressources-list`) and `/ressources/:slug` (name: `ressources-detail`)

### Tests

- [x] Task 12 — Frontend tests (AC: 14)
  - [x] 12.1 — Unit tests for `useAdminArticles` composable (15 tests)
  - [x] 12.2 — Unit tests for `usePaginatedArticles` (12 tests) and `useArticleDetail` (5 tests)
  - [x] 12.3 — Backend feature test for admin articles list endpoint (9 tests, 53 assertions)

## File List

### New Files
- `backend/app/Http/Requests/Admin/IndexArticleRequest.php` — Form Request for admin articles list validation
- `backend/tests/Feature/Admin/AdminArticlesListTest.php` — 9 tests, 53 assertions
- `frontend/src/features/admin/services/adminArticlesApi.ts` — Admin articles API service
- `frontend/src/features/admin/composables/useAdminArticles.ts` — Admin articles composable
- `frontend/src/features/admin/composables/__tests__/useAdminArticles.spec.ts` — 15 tests
- `frontend/src/pages/admin/AdminArticlesListPage.vue` — Admin articles list page
- `frontend/src/pages/admin/AdminArticleCreatePage.vue` — Admin article create page
- `frontend/src/pages/admin/AdminArticleEditPage.vue` — Admin article edit page
- `frontend/src/features/public/services/publicArticlesApi.ts` — Public articles API service
- `frontend/src/features/public/composables/usePaginatedArticles.ts` — Paginated articles composable
- `frontend/src/features/public/composables/useArticleDetail.ts` — Article detail composable
- `frontend/src/features/public/composables/__tests__/usePaginatedArticles.spec.ts` — 12 tests
- `frontend/src/features/public/composables/__tests__/useArticleDetail.spec.ts` — 5 tests
- `frontend/src/views/RessourcesView.vue` — Public articles list page
- `frontend/src/views/ArticleDetailView.vue` — Public article detail page

### Modified Files
- `backend/app/Http/Controllers/Api/V1/Admin/ArticleController.php` — Added `index()`, `show()` methods
- `backend/app/Services/Admin/ArticleService.php` — Added `listArticles()` method
- `backend/routes/api/admin.php` — Added `GET /admin/articles`, `GET /admin/articles/{article}` routes with rate limiting
- `frontend/src/router/index.ts` — Added admin article routes + public article routes
- `frontend/src/pages/admin/AdminLayout.vue` — Restored "Articles" sidebar link
- `frontend/package.json` — Added dompurify dependency
- `frontend/package-lock.json` — Lock file updated

### Dependencies Added
- `dompurify` + `@types/dompurify` — XSS-safe HTML rendering for article content

## Dev Agent Record

### Validation Results
- **TypeScript**: `vue-tsc --noEmit` passes clean
- **Backend tests**: 9/9 pass (AdminArticlesListTest), full suite 1381 passed (3 pre-existing failures)
- **Frontend tests**: 32/32 new tests pass, full suite 1727 passed (22 pre-existing failures in ExperienceForm)
- **No regressions introduced**

### Change Log
| Change | Reason |
|--------|--------|
| Added `index()` to `Admin\ArticleController` | Backend had no admin list endpoint (oversight from Epic 12) |
| Used `DOMPurify` for article HTML content | XSS protection flagged as action item in Epic 12 retrospective |
| Public articles in `features/public/` not `features/blog/` | Follows existing codebase convention (publicFacesApi, publicMissionsApi are in features/public) |
| AdminArticleEditPage uses dedicated show endpoint | Code review fix: replaced list API search (broken beyond page 1) with `GET /admin/articles/{article}` |
| Created `IndexArticleRequest` Form Request | Code review fix: project rule requires Form Requests for all endpoints |
| Moved list logic to `ArticleService::listArticles()` | Code review fix: project rule requires business logic in services, not controllers |
| Added `onUnmounted` URL cleanup to create/edit pages | Code review fix: prevent memory leaks from `URL.createObjectURL` |
| Added rate limiting to article index route | Code review fix: consistency with other admin routes |

## Dev Notes

### Existing Backend API Endpoints

**Admin (authenticated):**
- `POST /api/v1/admin/articles` — create article (with featured_image file upload)
- `PUT /api/v1/admin/articles/{article}` — update article
- `DELETE /api/v1/admin/articles/{article}` — delete article
- `PATCH /api/v1/admin/articles/{article}/category` — update category
- `PATCH /api/v1/admin/articles/{article}/status` — update status
- ~~`GET /api/v1/admin/articles`~~ — **MISSING, needs Task 1**

**Public (no auth):**
- `GET /api/v1/public/articles` — list published articles (paginated, filterable by category)
- `GET /api/v1/public/articles/{slug}` — article detail by slug

### Article Model Fields
`id`, `admin_id`, `title`, `slug`, `content`, `excerpt`, `category`, `status`, `featured_image`, `published_at`, `created_at`, `updated_at`

### Enums
- `ArticleCategory`: `conseils-face`, `guide-producteur`, `actualites`
- `ArticleStatus`: `draft`, `published`

### Resource Response Shape (ArticleResource)
```json
{
  "id": 1,
  "title": "...",
  "slug": "...",
  "content": "...",
  "excerpt": "...",
  "category": { "value": "conseils-face", "label": "Conseils Face" },
  "status": { "value": "draft", "label": "Brouillon" },
  "featured_image": "http://...",
  "published_at": "2026-...",
  "created_at": "2026-...",
  "updated_at": "2026-...",
  "admin": { "id": 1, "name": "..." }
}
```
