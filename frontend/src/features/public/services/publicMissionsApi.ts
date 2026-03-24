import { AxiosError } from 'axios'
import { publicApiClient } from './apiClient'
import type { PaginationMeta } from './publicFacesApi'

/**
 * Public Mission data exposed by the API
 * Only contains public-safe fields for unauthenticated visitors
 */
export interface PublicMission {
  id: number
  slug: string
  titre: string
  description: string
  date_tournage: string | null
  profil_recherche: string | null
  budget: number
  date_limite_candidature: string | null
  nombre_faces_voulu: number
  type_mission: string
  type_mission_label: string
  type_mission_autre: string | null
  genre_voulu: string
  genre_voulu_label: string
  lieu: string
  duree: string | null
  status: string
  status_label: string
  created_at: string
  producer: PublicMissionProducer | null
}

/**
 * Nested producer info in mission response
 */
export interface PublicMissionProducer {
  id: number
  display_name: string
  profile_photo_thumbnail_url: string | null
  average_rating: number | null
  ratings_count: number
}

/**
 * API response format for paginated missions list
 */
export interface PublicMissionsResponse {
  data: PublicMission[]
  meta: PaginationMeta
  message: string
}

export interface PublicMissionFilters {
  type_mission?: string
  lieu?: string
  budget_min?: number
  budget_max?: number
}

/**
 * API response format for a single mission detail
 */
export interface PublicMissionDetailResponse {
  data: PublicMission
  message: string
}

/**
 * Result type for mission detail fetch with error handling
 */
export interface PublicMissionDetailResult {
  success: boolean
  mission?: PublicMission
  error?: string
  notFound?: boolean
}

/**
 * Fetch paginated list of public missions
 *
 * @param page - Page number (1-indexed)
 * @param perPage - Items per page (default: 15, max: 30)
 * @returns Promise with missions data and pagination meta
 */
export async function fetchPublicMissions(
  page: number = 1,
  perPage: number = 15,
  filters: PublicMissionFilters = {}
): Promise<PublicMissionsResponse> {
  const validPerPage = Math.min(Math.max(1, perPage), 30)

  const params: Record<string, string | number> = {
    page,
    per_page: validPerPage,
  }

  if (filters.type_mission) params.type_mission = filters.type_mission
  if (filters.lieu) params.lieu = filters.lieu.trim()
  if (filters.budget_min != null) params.budget_min = filters.budget_min
  if (filters.budget_max != null) params.budget_max = filters.budget_max

  const response = await publicApiClient.get<PublicMissionsResponse>('/public/missions', {
    params,
  })

  return response.data
}

/**
 * Fetch a single public mission by slug
 *
 * @param slug - Mission slug
 * @returns Promise with mission data or error result
 */
export async function fetchPublicMissionDetail(
  slug: string
): Promise<PublicMissionDetailResult> {
  try {
    const response = await publicApiClient.get<PublicMissionDetailResponse>(
      `/public/missions/${encodeURIComponent(slug)}`
    )

    return {
      success: true,
      mission: response.data.data,
    }
  } catch (err) {
    const axiosError = err as AxiosError

    // Handle 404 - Mission not found
    if (axiosError.response?.status === 404) {
      return {
        success: false,
        notFound: true,
        error: 'Mission non trouvée',
      }
    }

    // Handle network or other errors
    return {
      success: false,
      error: 'Une erreur est survenue. Veuillez réessayer.',
    }
  }
}
