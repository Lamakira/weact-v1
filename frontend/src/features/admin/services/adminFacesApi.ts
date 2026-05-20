import adminApiClient from './adminApiClient'

/**
 * Face data from admin API
 */
export interface AdminFacePhoto {
  id: string
  photo_url: string
  thumbnail_url: string
  position: number
}

export interface AdminFaceExperience {
  id: string
  titre: string
  description: string | null
  date_debut: string | null
  date_fin: string | null
  is_ongoing: boolean
  formatted_period: string
}

export interface AdminFaceData {
  id: string
  nom: string
  prenom: string
  username: string
  bio: string | null
  ville: string | null
  pays: string | null
  whatsapp_number: string | null
  categories: Array<{ value: string; label: string }>
  niches: Array<{ value: string; label: string }>
  is_available: boolean
  is_featured: boolean
  is_featured_by_subscription?: boolean
  profile_completion_percentage: number
  profile_completion_missing: Array<{ key: string; label: string }>
  profile_completion_is_complete: boolean
  average_rating: number | null
  ratings_count: number | null
  profile_photo_url: string | null
  thumbnail_url: string | null
  presentation_video_url: string | null
  presentation_video_thumbnail_url: string | null
  acting_video_url: string | null
  acting_video_thumbnail_url: string | null
  taille: number | null
  poids: number | null
  tarif_horaire: number | null
  tarif_journalier: number | null
  formatted_tarif_horaire: string | null
  formatted_tarif_journalier: string | null
  formatted_location: string | null
  photos: AdminFacePhoto[]
  experiences: AdminFaceExperience[]
  photos_count: number
  experiences_count: number
  created_at: string
  updated_at: string
  email?: string
  is_active?: boolean
}

/**
 * Paginated face list response
 */
interface AdminFaceListResponse {
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
interface AdminFaceDetailResponse {
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
  pays?: string | null
  categories?: string[]
  niches?: string[]
  is_available?: boolean
  is_featured?: boolean
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
  async getFace(id: string): Promise<AdminFaceDetailResponse> {
    const response = await adminApiClient.get<AdminFaceDetailResponse>(`/admin/faces/${id}`)
    return response.data
  },

  /**
   * Update a face's admin-editable fields
   */
  async updateFace(id: string, data: UpdateAdminFaceForm): Promise<AdminFaceDetailResponse> {
    const response = await adminApiClient.put<AdminFaceDetailResponse>(
      `/admin/faces/${id}`,
      data,
    )
    return response.data
  },

  /**
   * Toggle a face's account activation status
   */
  async toggleActive(id: string): Promise<AdminFaceDetailResponse> {
    const response = await adminApiClient.patch<AdminFaceDetailResponse>(
      `/admin/faces/${id}/toggle-active`,
    )
    return response.data
  },

  /**
   * Delete a face and its associated user
   */
  async deleteFace(id: string): Promise<{ message: string }> {
    const response = await adminApiClient.delete<{ message: string }>(`/admin/faces/${id}`)
    return response.data
  },
}
