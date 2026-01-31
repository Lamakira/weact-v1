# Story 8.6: View Reviews List

Status: done

## Story

As a **user (Face, Producer, or public visitor)**,
I want **to see all reviews received by a Face or Producer**,
So that **I can read detailed feedback before deciding to work with them**.

## Acceptance Criteria

1. **Given** a Face has received ratings **When** I view their profile (Producer viewing candidate, or public view) **Then** I see a list of reviews with rater display name, star rating, comment (if any), and date

2. **Given** a Producer has received ratings **When** I view their public profile **Then** I see a list of reviews with rater display name, star rating, comment (if any), and date

3. **Given** a Face or Producer has no ratings **When** I view their profile **Then** I see "Aucune note pour le moment" message (already implemented in previous stories)

4. **Given** a profile has many reviews **When** I view the reviews list **Then** reviews are paginated (10 per page) and ordered by most recent first

5. **Given** I am viewing a review **When** the rater left no comment **Then** the comment area is not displayed (graceful handling)

**(Implements FR50)**

## Tasks / Subtasks

- [x] Task 1: Create backend API endpoint for Face reviews (AC: #1, #3, #4)
  - [x] Create `GET /api/v1/public/faces/{id}/reviews` endpoint
  - [x] Create `ReviewResource` API resource for review transformation
  - [x] Return paginated list of reviews with rater info, score, comment, created_at
  - [x] Order by created_at DESC (most recent first)
  - [x] Include rater's display_name and profile_photo_url (hide identity for privacy? - check business rules)

- [x] Task 2: Create backend API endpoint for Producer reviews (AC: #2, #3, #4)
  - [x] Create `GET /api/v1/public/producers/{id}/reviews` endpoint
  - [x] Reuse `ReviewResource` for consistent response format
  - [x] Return paginated list with same fields as Face reviews

- [x] Task 3: Create backend tests for review endpoints (AC: #1-5)
  - [x] Test Face reviews endpoint returns correct data structure
  - [x] Test Producer reviews endpoint returns correct data structure
  - [x] Test pagination (10 per page, page navigation)
  - [x] Test ordering (most recent first)
  - [x] Test empty state (no reviews returns empty array)
  - [x] Test review with comment vs without comment

- [x] Task 4: Create ReviewCard Vue component (AC: #1, #2, #5)
  - [x] Create `frontend/src/components/ReviewCard.vue`
  - [x] Display rater avatar, display name, star rating, comment, date
  - [x] Handle null/empty comment gracefully (don't show comment section)
  - [x] Format date in French locale ("15 janvier 2026")
  - [x] Style with Tailwind CSS matching existing design system

- [x] Task 5: Create ReviewsList Vue component (AC: #4)
  - [x] Create `frontend/src/components/ReviewsList.vue`
  - [x] Accept `reviews` prop (array) and `pagination` meta
  - [x] Display ReviewCard for each review
  - [x] Include pagination controls (if more than one page)
  - [x] Handle loading and empty states

- [x] Task 6: Integrate reviews in ProducerProfilePage (AC: #2)
  - [x] Update `frontend/src/pages/public/ProducerProfilePage.vue`
  - [x] Replace MVP placeholder in "Avis et évaluations" section with ReviewsList
  - [x] Fetch reviews from `/api/v1/public/producers/{id}/reviews`
  - [x] Handle loading state during fetch

- [x] Task 7: Integrate reviews in CandidateProfilePage (AC: #1)
  - [x] Producer candidate view shows Face reviews
  - [x] Integrated ReviewsList in `CandidateProfilePage.vue`

- [x] Task 8: Create frontend tests (AC: #1-5)
  - [x] Test ReviewCard renders correctly with all props
  - [x] Test ReviewCard handles null comment
  - [x] Test ReviewsList renders multiple reviews
  - [x] Test ReviewsList pagination
  - [x] Test empty state display

- [x] Task 9: Create frontend TypeScript types
  - [x] Add `Review` interface to `frontend/src/features/rating/types.ts`
  - [x] Add `ReviewsListResponse` interface with pagination meta

- [x] Task 10: Update sprint-status.yaml

## Dev Notes

### Rating Model (Already Exists)

From Story 8-1, the Rating model has these fields:
```php
// backend/app/Models/Rating.php
protected $fillable = [
    'candidature_id',
    'rater_id',
    'rated_id',
    'rated_type',
    'score',
    'comment',
];
```

Relationships:
- `rater()` → BelongsTo User (who gave the rating)
- `rated()` → MorphTo Face|Producer (who received the rating)
- `candidature()` → BelongsTo Candidature

### API Response Format

```json
{
  "data": [
    {
      "id": 1,
      "score": 5,
      "comment": "Excellent travail, très professionnel!",
      "created_at": "2026-01-15T14:30:00Z",
      "formatted_date": "15 janvier 2026",
      "rater": {
        "display_name": "Jean Dupont",
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
    "per_page": 10,
    "total": 25
  }
}
```

### CRITICAL: Rater Identity Privacy

**Decision needed:** Should we show the rater's full identity?
- **Option A:** Show rater display_name and photo (transparent feedback)
- **Option B:** Show only "Un producteur" / "Une Face" (anonymous)
- **Option C:** Show rater's first name only ("Jean D.")

Based on similar platforms (Fiverr, Upwork), transparent feedback is standard. **Recommend Option A** unless business rules dictate otherwise.

### Frontend Patterns to Follow

**Existing Rating Display Pattern** (from Story 8-4):
```vue
// frontend/src/components/RatingDisplay.vue
<template>
  <div class="flex items-center gap-2">
    <!-- Stars and count display -->
  </div>
</template>
```

**Existing ProducerProfilePage "Avis et évaluations" section:**
```vue
// frontend/src/pages/public/ProducerProfilePage.vue (lines 346-375)
<!-- Rating Section (MVP placeholder) -->
<div class="bg-white rounded-xl shadow-sm p-6">
  <h2 class="text-lg font-semibold text-gray-900 mb-4">Avis et évaluations</h2>
  <div v-if="producer.average_rating === null" class="text-center py-8">
    <!-- No ratings message -->
  </div>
</div>
```

**This section needs to be enhanced to show actual reviews list.**

### Where Reviews Will Be Displayed

1. **Public Producer Profile** (`/producers/:id`)
   - File: `frontend/src/pages/public/ProducerProfilePage.vue`
   - Section: "Avis et évaluations" (lines 346-375)
   - API: `GET /api/v1/public/producers/{id}/reviews`

2. **Producer viewing Face candidate** (`/producer/candidates/:id`)
   - File: `frontend/src/pages/producer/candidature/CandidateProfilePage.vue`
   - Add new reviews section below profile info
   - API: `GET /api/v1/public/faces/{id}/reviews`

3. **Public Face Profile** (if implemented in Epic 11)
   - Will use same `GET /api/v1/public/faces/{id}/reviews` endpoint

### Project Structure (Files to Create/Modify)

```
backend/
├── app/Http/Controllers/Api/V1/Public/
│   ├── FaceReviewController.php (NEW)
│   └── ProducerReviewController.php (NEW)
├── app/Http/Resources/
│   └── ReviewResource.php (NEW)
├── routes/api/public.php (MODIFY - add review routes)
└── tests/Feature/Rating/
    ├── FaceReviewsListTest.php (NEW)
    └── ProducerReviewsListTest.php (NEW)

frontend/
├── src/components/
│   ├── ReviewCard.vue (NEW)
│   └── ReviewsList.vue (NEW)
├── src/features/rating/types.ts (NEW or add to existing)
├── src/pages/public/ProducerProfilePage.vue (MODIFY)
└── src/pages/producer/candidature/CandidateProfilePage.vue (MODIFY - optional)
```

### Testing Standards

**Backend (PHPUnit):**
- Use `RefreshDatabase` trait
- Test pagination with 15+ ratings
- Test ordering by created_at DESC
- Test review with/without comment
- Test rater info is included

**Frontend (Vitest):**
- Test ReviewCard with all props
- Test ReviewCard handles null comment
- Test ReviewsList pagination rendering
- Test empty state

### Dependencies

- **Depends on**: Story 8-1 (Rating model) ✓ COMPLETED
- **Depends on**: Story 8-2 (Face rates Producer) ✓ COMPLETED
- **Depends on**: Story 8-3 (Producer rates Face) ✓ COMPLETED
- **Depends on**: Story 8-4, 8-5 (Rating display) ✓ COMPLETED

### Git Intelligence (Recent Commits)

```
5e678b6 test(ratings): add Producer rating display tests (Story 8-5)
16c5b5e feat(ratings): expose Producer average rating in public API (Story 8-5)
bc1df13 docs: mark Story 8-4 as done
cb79f97 feat(ratings): display ratings on Face and Producer profile pages
```

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 8.6 - View Reviews List]
- [Source: backend/app/Models/Rating.php - Rating model with rater/rated relationships]
- [Source: frontend/src/pages/public/ProducerProfilePage.vue#lines 346-375 - MVP placeholder section]
- [Source: frontend/src/components/RatingDisplay.vue - Existing rating component pattern]
- [Source: _bmad-output/implementation-artifacts/8-5-display-producer-average-rating.md - Previous story]

## Dev Agent Record

### File List

**Backend - New Files:**
- `backend/app/Http/Controllers/Api/V1/Public/FaceReviewController.php` - Face reviews list endpoint
- `backend/app/Http/Controllers/Api/V1/Public/ProducerReviewController.php` - Producer reviews list endpoint
- `backend/app/Http/Resources/ReviewResource.php` - API resource for review transformation
- `backend/tests/Feature/Rating/FaceReviewsListTest.php` - 11 tests for Face reviews endpoint
- `backend/tests/Feature/Rating/ProducerReviewsListTest.php` - 9 tests for Producer reviews endpoint

**Backend - Modified Files:**
- `backend/routes/api/public.php` - Added review routes

**Frontend - New Files:**
- `frontend/src/components/ReviewCard.vue` - Individual review display component
- `frontend/src/components/ReviewsList.vue` - Paginated reviews list component
- `frontend/src/components/__tests__/ReviewCard.spec.ts` - 12 tests for ReviewCard
- `frontend/src/components/__tests__/ReviewsList.spec.ts` - 8 tests for ReviewsList
- `frontend/src/features/rating/types.ts` - TypeScript types for reviews

**Frontend - Modified Files:**
- `frontend/src/features/public/services/publicApi.ts` - Added getProducerReviews and getFaceReviews methods
- `frontend/src/pages/public/ProducerProfilePage.vue` - Integrated ReviewsList component
- `frontend/src/pages/public/__tests__/ProducerProfilePage.spec.ts` - Updated tests for reviews integration
- `frontend/src/pages/producer/candidature/CandidateProfilePage.vue` - Added reviews section for Face candidates

### Test Results
- Backend: 20 new tests passing (FaceReviewsListTest: 11, ProducerReviewsListTest: 9)
- Frontend: 20 new tests passing (ReviewCard: 12, ReviewsList: 8)
- All 804 frontend tests passing
- All 132 backend Rating tests passing

## Senior Developer Review (AI)

**Reviewed:** 2026-01-29
**Status:** APPROVED

### Issues Found & Fixed

| Severity | Issue | Resolution |
|----------|-------|------------|
| CRITICAL | Story missing File List section | Added comprehensive Dev Agent Record with File List |
| MEDIUM | Pagination renders ALL page numbers (perf issue for large page counts) | Implemented windowed pagination with ellipsis |
| MEDIUM | No tests for invalid page parameters | Added 4 new backend tests for edge cases (page=0, -1, 999, abc) |
| MEDIUM | Reviews fetch silently fails with empty state | Added reviewsError state with retry button |
| LOW | ReviewCard missing aria-label (accessibility) | Noted for future improvement |
| LOW | Test files in `__tests__` vs colocated | Consistent within project, accepted |
| LOW | Design system differences between pages | Intentional context differences, accepted |
| LOW | Controllers don't use Form Request | Acceptable for simple read-only endpoints |

### OWASP Security Review
- ✅ A01 Broken Access Control: N/A - Public read-only endpoints
- ✅ A03 Injection: Using Laravel ORM with parameterized queries
- ✅ A04 Insecure Design: Rate limiting applied (60 req/min)
- ✅ A05 Security Misconfiguration: No debug info leaked in responses

### Final Test Results
- Backend: 15 FaceReviewsListTest + 9 ProducerReviewsListTest = 24 tests passing
- Frontend: 806 tests passing (14 ReviewsList, 12 ReviewCard, 26 ProducerProfilePage)

## Change Log

| Date | Author | Change |
|------|--------|--------|
| 2026-01-29 | SM Agent | Story created - ready-for-dev |
| 2026-01-29 | Dev Agent | Story completed - all tasks done, 20 backend tests + 36 frontend tests passing |
| 2026-01-29 | Code Review | Added File List, fixed pagination UX, added error handling, added edge case tests |
