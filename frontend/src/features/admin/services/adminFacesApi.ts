import adminApiClient from './adminApiClient'

/**
 * Face data from admin API
 */
export interface AdminFaceData {
  id: number
  nom: string
  prenom: string
  username: string
  bio: string | null
  ville: string | null
  quartier: string | null
  pays: string | null
  categorie: string | null
  niche: string | null
  is_available: boolean
  profile_completion_percentage: number
  average_rating: number | null
  ratings_count: number | null
  profile_photo_url: string | null
  created_at: string
  email?: string
}

/**
 * Paginated face list response
 */
export interface AdminFaceListResponse {
  data: AdminFaceData[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

/**
 * Single face detail response
 */
export interface AdminFaceDetailResponse {
  data: AdminFaceData
  message: string
}

/**
 * Update face form data
 */
export interface UpdateAdminFaceForm {
  nom?: string
  prenom?: string
  username?: string
  bio?: string | null
  ville?: string | null
  quartier?: string | null
  pays?: string | null
  categorie?: string | null
  niche?: string | null
  is_available?: boolean
}

/**
 * Query params for listing faces
 */
export interface AdminFaceListParams {
  page?: number
  search?: string
  category?: string
  is_available?: string
}

/**
 * Admin face management API service
 */
export const adminFacesApi = {
  /**
   * Get paginated list of faces with optional search/filters
   */
  async getFaces(params?: AdminFaceListParams): Promise<AdminFaceListResponse> {
    const response = await adminApiClient.get<AdminFaceListResponse>('/admin/faces', {
      params,
    })
    return response.data
  },

  /**
   * Get a single face by ID
   */
  async getFace(id: number): Promise<AdminFaceDetailResponse> {
    const response = await adminApiClient.get<AdminFaceDetailResponse>(`/admin/faces/${id}`)
    return response.data
  },

  /**
   * Update a face's admin-editable fields
   */
  async updateFace(id: number, data: UpdateAdminFaceForm): Promise<AdminFaceDetailResponse> {
    const response = await adminApiClient.put<AdminFaceDetailResponse>(
      `/admin/faces/${id}`,
      data,
    )
    return response.data
  },

  /**
   * Delete a face and its associated user
   */
  async deleteFace(id: number): Promise<{ message: string }> {
    const response = await adminApiClient.delete<{ message: string }>(`/admin/faces/${id}`)
    return response.data
  },
}
