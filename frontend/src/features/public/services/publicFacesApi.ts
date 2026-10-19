import { AxiosError } from 'axios'
import { publicApiClient } from './apiClient'
import { getAuthToken } from '@/services/apiClient'
import { useAuthStore } from '@/stores/auth'
import type { FacePhoto, FaceExperience } from '@/features/candidature/types'

/**
 * Public Face data exposed by the API
 * Only contains public-safe fields (no sensitive data like full name, tariffs, etc.)
 */
/**
 * Value+label pair for categories and niches
 */
/**
 * Lot B13 : le jeton de session accompagne la liste et le profil publics UNIQUEMENT pour un
 * Producer connecté (seul profil à voir le nom complet). Invité, Face et admin n'envoient
 * rien : le backend n'a alors aucune recherche de jeton inutile à faire.
 */
function viewerHeaders(): Record<string, string> | undefined {
  if (!useAuthStore().isProducer) return undefined
  const token = getAuthToken()
  return token ? { Authorization: `Bearer ${token}` } : undefined
}

/**
 * Nom affiché d'une Face publique : « Prénom Nom » quand l'API a fourni le nom complet
 * (Producer connecté), sinon le prénom seul (l'initiale « K. » n'est pas affichée).
 */
export function publicFaceName(face: Pick<PublicFace, 'prenom' | 'nom'>): string {
  const nom = face.nom?.trim()
  if (!nom || /^\p{Lu}\.$/u.test(nom)) return face.prenom
  return `${face.prenom} ${nom}`
}

export interface ValueLabel {
  value: string
  label: string
}

export interface PublicFace {
  id: string
  username: string
  prenom: string
  nom?: string | null
  display_name?: string
  ville: string | null
  categories: ValueLabel[]
  is_available: boolean
  profile_photo_url: string | null
  profile_photo_medium_url: string | null
  profile_photo_grid_url: string | null
  profile_photo_large_url: string | null
  profile_photo_thumbnail_url: string | null
  average_rating: number | null
  has_elite_badge: boolean
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
  has_elite_badge: boolean
  photos: FacePhoto[]
  experiences: FaceExperience[]
}

/**
 * API response format for a single face profile
 */
interface PublicFaceProfileResponse {
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
  /**
   * Ranking generation the listing was ordered by (public faces carousel).
   * Null while no ranking has ever been built. Optional so the other
   * paginated listings can keep reusing this shape.
   */
  generation?: number | null
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
interface FilterOptionsResponse {
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
 * The public listing rotates every few minutes, so paging through it needs a
 * fixed reference point: `generation` pins the ranking the visitor already saw
 * on page 1. It travels ONLY as an HTTP query parameter — never in the page
 * URL, which useKeepAliveListingGuard signs to decide whether to refetch.
 *
 * @param page - Page number (1-indexed)
 * @param perPage - Items per page (default: 15, max: 30)
 * @param filters - Optional filter parameters
 * @param generation - Optional pinned ranking generation (from a previous meta.generation)
 * @returns Promise with faces data and pagination meta
 */
export async function fetchPublicFaces(
  page: number = 1,
  perPage: number = 15,
  filters: FacesFilterParams = {},
  generation?: number | null
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
  // A purged generation is not an error: the API silently falls back to the
  // current one and reports it back in meta.generation.
  if (typeof generation === 'number') params.generation = generation

  const headers = viewerHeaders()
  const response = await publicApiClient.get<PublicFacesResponse>('/public/faces', {
    params,
    ...(headers ? { headers } : {}),
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
    const headers = viewerHeaders()
    const response = await publicApiClient.get<PublicFaceProfileResponse>(
      `/public/faces/${encodeURIComponent(username)}`,
      headers ? { headers } : undefined,
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
