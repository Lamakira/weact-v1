/**
 * Producer profile feature types
 */

// Producer type enum
export type ProducerType = 'agency' | 'particulier'

// Producer profile data from API
export interface Producer {
  id: number
  type: ProducerType
  agency_name: string | null
  first_name: string | null
  last_name: string | null
  display_name: string
  profile_photo_url: string | null
  thumbnail_url: string | null
  created_at: string
  updated_at: string
}

// Producer profile API response
export interface ProducerProfileResponse {
  data: Producer
  message?: string
}

// Profile photo upload result
export interface ProducerProfilePhotoResult {
  success: boolean
  data?: Producer
  errors?: Record<string, string[]>
  message?: string
}
