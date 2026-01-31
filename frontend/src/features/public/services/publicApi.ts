import apiClient from '@/services/apiClient'
import type { PublicProducerResponse } from '../types'
import type { ReviewsListResponse } from '@/features/rating/types'

/**
 * Public API service
 * Endpoints that don't require authentication
 */
export const publicApi = {
  /**
   * Get a public Producer profile by ID
   * No authentication required
   * @param id The Producer ID
   */
  async getProducer(id: number): Promise<PublicProducerResponse> {
    const response = await apiClient.get<PublicProducerResponse>(`/public/producers/${id}`)
    return response.data
  },

  /**
   * Get reviews for a Producer
   * No authentication required
   * @param id The Producer ID
   * @param page The page number (default 1)
   */
  async getProducerReviews(id: number, page: number = 1): Promise<ReviewsListResponse> {
    const response = await apiClient.get<ReviewsListResponse>(`/public/producers/${id}/reviews`, {
      params: { page },
    })
    return response.data
  },

  /**
   * Get reviews for a Face
   * No authentication required
   * @param id The Face ID
   * @param page The page number (default 1)
   */
  async getFaceReviews(id: number, page: number = 1): Promise<ReviewsListResponse> {
    const response = await apiClient.get<ReviewsListResponse>(`/public/faces/${id}/reviews`, {
      params: { page },
    })
    return response.data
  },
}
