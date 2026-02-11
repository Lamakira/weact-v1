# Story 12.10: Articles Frontend Pages

Status: ready-for-dev

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

- [ ] Task 1 — Add `index` method to `Admin\ArticleController` (AC: 6)
  - [ ] 1.1 — Add `index(Request $request)` method with pagination, search by title, filter by category/status
  - [ ] 1.2 — Register `GET /api/v1/admin/articles` route in `routes/api/admin.php`
  - [ ] 1.3 — Write feature test for the admin articles list endpoint

### Frontend — Admin API & Composable

- [ ] Task 2 — Create admin articles API service (AC: 10)
  - [ ] 2.1 — Create `frontend/src/features/admin/services/adminArticlesApi.ts`
  - [ ] 2.2 — Define `Article` TypeScript interface matching `ArticleResource` response
  - [ ] 2.3 — Implement all CRUD methods + status/category update

- [ ] Task 3 — Create admin articles composable (AC: 11)
  - [ ] 3.1 — Create `frontend/src/features/admin/composables/useAdminArticles.ts`
  - [ ] 3.2 — Wrap API service with reactive state (articles, loading, error, pagination)

### Frontend — Admin Pages

- [ ] Task 4 — Create `AdminArticlesListPage.vue` (AC: 1, 4, 5)
  - [ ] 4.1 — Paginated table with columns: title, category badge, status badge, author, date, actions
  - [ ] 4.2 — Search input and category/status filter dropdowns
  - [ ] 4.3 — Status toggle button per row
  - [ ] 4.4 — Delete button with ConfirmModal (danger variant)

- [ ] Task 5 — Create `AdminArticleCreatePage.vue` (AC: 2)
  - [ ] 5.1 — Form with: title, content (textarea), excerpt, category select, featured image upload
  - [ ] 5.2 — Submit → POST API → redirect to list with success message

- [ ] Task 6 — Create `AdminArticleEditPage.vue` (AC: 3)
  - [ ] 6.1 — Load article data on mount, pre-fill form
  - [ ] 6.2 — Submit → PUT API → success message

- [ ] Task 7 — Register admin article routes (AC: 13)
  - [ ] 7.1 — Add children routes under admin layout: `articles`, `articles/create`, `articles/:id/edit`

### Frontend — Public API & Composable

- [ ] Task 8 — Create public articles API service and composable (AC: 12)
  - [ ] 8.1 — Create `frontend/src/features/blog/services/publicArticlesApi.ts`
  - [ ] 8.2 — Create `frontend/src/features/blog/composables/usePublicArticles.ts`

### Frontend — Public Pages

- [ ] Task 9 — Create `RessourcesPage.vue` (AC: 7, 9)
  - [ ] 9.1 — Grid/card layout of published articles
  - [ ] 9.2 — Category filter tabs/dropdown
  - [ ] 9.3 — Pagination

- [ ] Task 10 — Create `ArticleDetailPage.vue` (AC: 8)
  - [ ] 10.1 — Full article content display with featured image, author, date, category

- [ ] Task 11 — Register public article routes (AC: 13)
  - [ ] 11.1 — Add routes: `/ressources` and `/ressources/:slug`

### Tests

- [ ] Task 12 — Frontend tests (AC: 14)
  - [ ] 12.1 — Unit tests for `useAdminArticles` composable
  - [ ] 12.2 — Unit tests for `usePublicArticles` composable
  - [ ] 12.3 — Backend feature test for admin articles list endpoint

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
