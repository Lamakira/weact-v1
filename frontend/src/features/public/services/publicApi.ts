import apiClient from '@/services/apiClient'
import type { PublicProducerResponse } from '../types'

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
}
