import apiClient from '@/services/apiClient'
import type { PaginatedMissionsResponse } from '../types'

/**
 * Face Mission API service
 * Endpoints for Face users to browse available missions
 */
export const faceMissionApi = {
  /**
   * Get paginated list of available (published) missions for Faces
   * @param page The page number (default: 1)
   * @returns Paginated list of missions ordered by most recent first
   */
  async getAvailableMissions(page: number = 1): Promise<PaginatedMissionsResponse> {
    const response = await apiClient.get<PaginatedMissionsResponse>('/face/missions', {
      params: { page },
    })
    return response.data
  },
}
