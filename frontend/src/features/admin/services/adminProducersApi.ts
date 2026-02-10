import adminApiClient from './adminApiClient'

/**
 * Producer data from admin API
 */
export interface AdminProducerData {
  id: number
  type: string
  agency_name: string | null
  first_name: string | null
  last_name: string | null
  display_name: string
  bio: string | null
  profile_photo_url: string | null
  thumbnail_url: string | null
  agency_logo_url: string | null
  agency_logo_thumbnail_url: string | null
  average_rating: number | null
  ratings_count: number
  missions_count?: number
  created_at: string
  updated_at: string
  email?: string
}

/**
 * Paginated producer list response
 */
export interface AdminProducerListResponse {
  data: AdminProducerData[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

/**
 * Single producer detail response
 */
export interface AdminProducerDetailResponse {
  data: AdminProducerData
  message: string
}

/**
 * Update producer form data
 */
export interface UpdateAdminProducerForm {
  type?: string
  agency_name?: string | null
  first_name?: string | null
  last_name?: string | null
  bio?: string | null
}

/**
 * Query params for listing producers
 */
export interface AdminProducerListParams {
  page?: number
  search?: string
  type?: string
}

/**
 * Admin producer management API service
 */
export const adminProducersApi = {
  /**
   * Get paginated list of producers with optional search/filters
   */
  async getProducers(params?: AdminProducerListParams): Promise<AdminProducerListResponse> {
    const response = await adminApiClient.get<AdminProducerListResponse>('/admin/producers', {
      params,
    })
    return response.data
  },

  /**
   * Get a single producer by ID
   */
  async getProducer(id: number): Promise<AdminProducerDetailResponse> {
    const response = await adminApiClient.get<AdminProducerDetailResponse>(
      `/admin/producers/${id}`,
    )
    return response.data
  },

  /**
   * Update a producer's admin-editable fields
   */
  async updateProducer(
    id: number,
    data: UpdateAdminProducerForm,
  ): Promise<AdminProducerDetailResponse> {
    const response = await adminApiClient.put<AdminProducerDetailResponse>(
      `/admin/producers/${id}`,
      data,
    )
    return response.data
  },

  /**
   * Delete a producer and its associated user
   */
  async deleteProducer(id: number): Promise<{ message: string }> {
    const response = await adminApiClient.delete<{ message: string }>(`/admin/producers/${id}`)
    return response.data
  },
}
