import { AxiosError } from 'axios'
import { publicApiClient } from './apiClient'
import type { FacePhoto, FaceExperience } from '@/features/candidature/types'

/**
 * Public Face data exposed by the API
 * Only contains public-safe fields (no sensitive data like full name, tariffs, etc.)
 */
/**
 * Value+label pair for categories and niches
 */
export interface ValueLabel {
  value: string
  label: string
}

export interface PublicFace {
  id: number
  user_id: number
  username: string
  prenom: string
  nom: string
  ville: string | null
  categories: ValueLabel[]
  is_available: boolean
  profile_photo_url: string | null
  profile_photo_thumbnail_url: string | null
  average_rating: number | null
}

/**
 * Public Face Profile data (extended for profile detail view).
 * Includes all public-safe fields: bio, photos, videos, experiences.
 * Tariffs are excluded — only accessible to authenticated producers.
 */
export interface PublicFaceProfile extends PublicFace {
  niches: ValueLabel[]
  profile_photo_url: string | null
  ratings_count: number
  sexe: string | null
  sexe_label: string | null
  age: number | null
  nationalite: string | null
  langues: string[]
  taille: number | null
  bio: string | null
  quartier: string | null
  pays: string | null
  formatted_location: string | null
  presentation_video_url: string | null
  presentation_video_thumbnail_url: string | null
  acting_video_url: string | null
  acting_video_thumbnail_url: string | null
  has_album_photos: boolean
  album_photos_count: number
  has_presentation_video: boolean
  has_acting_video: boolean
  photos: FacePhoto[]
  experiences: FaceExperience[]
}

/**
 * API response format for a single face profile
 */
export interface PublicFaceProfileResponse {
  data: PublicFaceProfile
  message: string
}

/**
 * Result type for face profile fetch with error handling
 */
export interface PublicFaceProfileResult {
  success: boolean
  profile?: PublicFaceProfile
  error?: string
  notFound?: boolean
}

/**
 * Pagination metadata from the API response
 */
export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

/**
 * Filter parameters for the public faces list
 */
export interface FacesFilterParams {
  categorie?: string
  niche?: string
  ville?: string
  search?: string
}

/**
 * A filter option with value and display label
 */
export interface FilterOption {
  value: string
  label: string
}

/**
 * API response for filter options endpoint
 */
export interface FilterOptionsResponse {
  data: {
    categories: FilterOption[]
    niches: FilterOption[]
    cities: string[]
  }
  message: string
}

/**
 * API response format for paginated faces list
 */
export interface PublicFacesResponse {
  data: PublicFace[]
  meta: PaginationMeta
  message: string
}

/**
 * Fetch paginated list of public faces with optional filters
 *
 * @param page - Page number (1-indexed)
 * @param perPage - Items per page (default: 15, max: 30)
 * @param filters - Optional filter parameters
 * @returns Promise with faces data and pagination meta
 */
export async function fetchPublicFaces(
  page: number = 1,
  perPage: number = 15,
  filters: FacesFilterParams = {}
): Promise<PublicFacesResponse> {
  // Clamp perPage to max 30
  const validPerPage = Math.min(Math.max(1, perPage), 30)

  // Build params, only including non-empty filter values
  const params: Record<string, string | number> = {
    page,
    per_page: validPerPage,
  }
  if (filters.categorie) params.categorie = filters.categorie
  if (filters.niche) params.niche = filters.niche
  if (filters.ville) params.ville = filters.ville
  if (filters.search && filters.search.length >= 2) params.search = filters.search

  const response = await publicApiClient.get<PublicFacesResponse>('/public/faces', {
    params,
  })

  return response.data
}

/**
 * Fetch available filter options (categories, niches, cities)
 *
 * @returns Promise with filter options data
 */
export async function fetchFilterOptions(): Promise<FilterOptionsResponse> {
  const response = await publicApiClient.get<FilterOptionsResponse>('/public/faces/options')

  return response.data
}

/**
 * Fetch a single public face profile by username
 *
 * @param username - Face username
 * @returns Promise with face profile data or error result
 */
export async function fetchPublicFaceProfile(
  username: string
): Promise<PublicFaceProfileResult> {
  try {
    const response = await publicApiClient.get<PublicFaceProfileResponse>(
      `/public/faces/${encodeURIComponent(username)}`
    )

    return {
      success: true,
      profile: response.data.data,
    }
  } catch (err) {
    const axiosError = err as AxiosError

    // Handle 404 - Face not found
    if (axiosError.response?.status === 404) {
      return {
        success: false,
        notFound: true,
        error: 'Face non trouvée',
      }
    }

    // Handle network or other errors
    return {
      success: false,
      error: 'Une erreur est survenue. Veuillez réessayer.',
    }
  }
}
