import { publicApiClient } from './apiClient'
import type { PaginationMeta } from './publicFacesApi'

/**
 * Public Mission data exposed by the API
 * Only contains public-safe fields for unauthenticated visitors
 */
export interface PublicMission {
  id: number
  titre: string
  description: string
  date_tournage: string | null
  profil_recherche: string | null
  budget: number
  date_limite_candidature: string | null
  nombre_faces_voulu: number
  type_mission: string
  type_mission_label: string
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

/**
 * Fetch paginated list of public missions
 *
 * @param page - Page number (1-indexed)
 * @param perPage - Items per page (default: 15, max: 30)
 * @returns Promise with missions data and pagination meta
 */
export async function fetchPublicMissions(
  page: number = 1,
  perPage: number = 15
): Promise<PublicMissionsResponse> {
  const validPerPage = Math.min(Math.max(1, perPage), 30)

  const params: Record<string, number> = {
    page,
    per_page: validPerPage,
  }

  const response = await publicApiClient.get<PublicMissionsResponse>('/v1/public/missions', {
    params,
  })

  return response.data
}
