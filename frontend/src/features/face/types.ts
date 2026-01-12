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

// Presentation video info from API
export interface PresentationVideoInfo {
  presentation_video_url: string | null
  presentation_video_thumbnail_url: string | null
}

// Presentation video API response
export interface PresentationVideoResponse {
  data: PresentationVideoInfo
  message?: string
}

// Presentation video operation result
export interface PresentationVideoResult {
  success: boolean
  data?: PresentationVideoInfo
  errors?: Record<string, string[]>
  message?: string
}

// Video upload progress
export interface VideoUploadProgress {
  loaded: number
  total: number
  percentage: number
}

// Acting video info from API
export interface ActingVideoInfo {
  acting_video_url: string | null
  acting_video_thumbnail_url: string | null
}

// Acting video API response
export interface ActingVideoResponse {
  data: ActingVideoInfo
  message?: string
}

// Acting video operation result
export interface ActingVideoResult {
  success: boolean
  data?: ActingVideoInfo
  errors?: Record<string, string[]>
  message?: string
}

// Bio and location info from API
export interface BioLocationInfo {
  bio: string | null
  ville: string | null
  quartier: string | null
  pays: string | null
  formatted_location: string | null
}

// Bio and location API response
export interface BioLocationResponse {
  data: BioLocationInfo
  message?: string
}

// Bio and location operation result
export interface BioLocationResult {
  success: boolean
  data?: BioLocationInfo
  errors?: Record<string, string[]>
  message?: string
}
