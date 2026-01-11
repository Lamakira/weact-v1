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

// Album photo from API
export interface FacePhoto {
  id: number
  photo_url: string
  thumbnail_url: string
  position: number
}

// Album photos API response
export interface FacePhotosResponse {
  data: FacePhoto[]
  message?: string
}

// Single album photo API response
export interface FacePhotoResponse {
  data: FacePhoto
  message?: string
}

// Album photo operation result
export interface AlbumPhotoResult {
  success: boolean
  data?: FacePhoto
  errors?: Record<string, string[]>
  message?: string
}
