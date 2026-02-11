import adminApiClient from './adminApiClient'

/**
 * Producer data nested in mission response
 */
export interface AdminMissionProducer {
  id: number
  type: string
  agency_name: string | null
  first_name: string
  last_name: string
  display_name: string
  profile_photo_url: string | null
  thumbnail_url: string | null
}

/**
 * Mission data from admin API
 */
export interface AdminMissionData {
  id: number
  titre: string
  description: string | null
  date_tournage: string | null
  profil_recherche: string | null
  budget: number | null
  date_limite_candidature: string | null
  nombre_faces_voulu: number | null
  type_mission: string | null
  type_mission_label: string | null
  genre_voulu: string | null
  genre_voulu_label: string | null
  lieu: string | null
  duree: string | null
  status: string
  status_label: string
  is_accepting_candidatures: boolean
  candidatures_count: number
  producer: AdminMissionProducer | null
  created_at: string
  updated_at: string
}

/**
 * Paginated mission list response
 */
export interface AdminMissionListResponse {
  data: AdminMissionData[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

/**
 * Single mission detail response
 */
export interface AdminMissionDetailResponse {
  data: AdminMissionData
  message: string
}

/**
 * Query params for listing missions
 */
export interface AdminMissionListParams {
  page?: number
  search?: string
  status?: string
  type_mission?: string
}

/**
 * Admin mission management API service (read-only)
 */
export const adminMissionsApi = {
  /**
   * Get paginated list of missions with optional search/filters
   */
  async getMissions(params?: AdminMissionListParams): Promise<AdminMissionListResponse> {
    const response = await adminApiClient.get<AdminMissionListResponse>('/admin/missions', {
      params,
    })
    return response.data
  },

  /**
   * Get a single mission by ID
   */
  async getMission(id: number): Promise<AdminMissionDetailResponse> {
    const response = await adminApiClient.get<AdminMissionDetailResponse>(`/admin/missions/${id}`)
    return response.data
  },
}
