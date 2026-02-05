import axios from 'axios'

/**
 * Public Face data exposed by the API
 * Only contains public-safe fields (no sensitive data like full name, tariffs, etc.)
 */
export interface PublicFace {
  id: number
  prenom: string
  ville: string | null
  categorie: string
  categorie_label: string
  is_available: boolean
  profile_photo_thumbnail_url: string | null
  average_rating: number | null
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
 * API response format for paginated faces list
 */
export interface PublicFacesResponse {
  data: PublicFace[]
  meta: PaginationMeta
  message: string
}

/**
 * API client instance configured for public endpoints
 */
const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

/**
 * Fetch paginated list of public faces
 *
 * @param page - Page number (1-indexed)
 * @param perPage - Items per page (default: 15, max: 30)
 * @returns Promise with faces data and pagination meta
 */
export async function fetchPublicFaces(
  page: number = 1,
  perPage: number = 15
): Promise<PublicFacesResponse> {
  // Clamp perPage to max 30
  const validPerPage = Math.min(Math.max(1, perPage), 30)

  const response = await apiClient.get<PublicFacesResponse>('/v1/public/faces', {
    params: {
      page,
      per_page: validPerPage,
    },
  })

  return response.data
}
