import apiClient from '@/services/apiClient'
import type { PublicProducerResponse } from '../types'
import type { ReviewsListResponse } from '@/features/rating/types'

/**
 * Public API service
 * Endpoints that don't require authentication
 */
export const publicApi = {
  /**
   * Get a public Producer profile by slug
   * No authentication required
   * @param slug The Producer slug
   */
  async getProducer(slug: string): Promise<PublicProducerResponse> {
    const response = await apiClient.get<PublicProducerResponse>(`/public/producers/${slug}`)
    return response.data
  },

  /**
   * Get reviews for a Producer
   * No authentication required
   * @param slug The Producer slug
   * @param page The page number (default 1)
   */
  async getProducerReviews(slug: string, page: number = 1): Promise<ReviewsListResponse> {
    const response = await apiClient.get<ReviewsListResponse>(`/public/producers/${slug}/reviews`, {
      params: { page },
    })
    return response.data
  },

  /**
   * Get reviews for a Face
   * No authentication required
   * @param username The Face username
   * @param page The page number (default 1)
   */
  async getFaceReviews(username: string, page: number = 1): Promise<ReviewsListResponse> {
    const response = await apiClient.get<ReviewsListResponse>(`/public/faces/${username}/reviews`, {
      params: { page },
    })
    return response.data
  },
}
