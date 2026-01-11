/**
 * Face profile feature types
 */

// Face profile data from API
export interface FaceProfile {
  id: number
  nom: string
  prenom: string
  username: string
  profile_photo_url: string | null
  thumbnail_url: string | null
}

// Face profile API response
export interface FaceProfileResponse {
  data: FaceProfile
  message?: string
}

// Profile photo upload result
export interface ProfilePhotoResult {
  success: boolean
  data?: FaceProfile
  errors?: Record<string, string[]>
  message?: string
}
