/**
 * Public feature types
 * Types for publicly accessible producer/face profiles
 */

// Producer type enum
export type ProducerType = 'agency' | 'particulier'

// Public Producer profile data from API
export interface PublicProducer {
  id: string
  slug: string | null
  type: ProducerType
  display_name: string
  agency_name: string | null
  bio: string | null
  profile_photo_url: string | null
  thumbnail_url: string | null
  agency_logo_url: string | null
  agency_logo_thumbnail_url: string | null
  missions_count: number
  in_progress_missions_count: number
  completed_missions_count: number
  average_rating: number | null
  ratings_count: number
  member_since: string
}

// Public Producer API response
export interface PublicProducerResponse {
  data: PublicProducer
}

// Public Producer fetch result
export interface PublicProducerResult {
  success: boolean
  producer?: PublicProducer
  error?: string
  notFound?: boolean
}
