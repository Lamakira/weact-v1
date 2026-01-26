import apiClient from '@/services/apiClient'
import type { MissionFilters, MissionResponse, PaginatedMissionsResponse } from '../types'

/**
 * Face Mission API service
 * Endpoints for Face users to browse available missions
 */
export const faceMissionApi = {
  /**
   * Get paginated list of available (published) missions for Faces
   * @param page The page number (default: 1)
   * @param filters Optional filters (lieu, budget_min, budget_max, date_tournage, type_mission)
   * @returns Paginated list of missions ordered by most recent first
   */
  async getAvailableMissions(
    page: number = 1,
    filters?: MissionFilters,
  ): Promise<PaginatedMissionsResponse> {
    // Build params object, only including non-empty values
    const params: Record<string, string | number> = { page }

    if (filters) {
      if (filters.lieu) params.lieu = filters.lieu
      if (filters.budget_min !== undefined) params.budget_min = filters.budget_min
      if (filters.budget_max !== undefined) params.budget_max = filters.budget_max
      if (filters.date_tournage) params.date_tournage = filters.date_tournage
      if (filters.type_mission) params.type_mission = filters.type_mission
    }

    const response = await apiClient.get<PaginatedMissionsResponse>('/face/missions', {
      params,
    })
    return response.data
  },

  /**
   * Get a single mission detail by ID
   * @param id The mission ID
   * @returns Mission detail with producer info
   */
  async getMissionDetail(id: number): Promise<MissionResponse> {
    const response = await apiClient.get<MissionResponse>(`/face/missions/${id}`)
    return response.data
  },
}
