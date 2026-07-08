/**
 * Rating feature types
 */

// Rater info for review display
export interface ReviewRater {
  display_name: string
  profile_photo_url: string | null
  // 150px avatar variant — server falls back to the original while the
  // variant job is pending
  profile_photo_thumbnail_url: string | null
}

// Single review from API
export interface Review {
  id: number
  score: number
  comment: string | null
  created_at: string
  formatted_date: string
  rater: ReviewRater
}

// Paginated reviews response
export interface ReviewsListResponse {
  data: Review[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
