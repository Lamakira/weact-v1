import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { CreateMissionData, MissionResponse } from '../types'

/**
 * Mission API service
 */
export const missionApi = {
  /**
   * Create a new mission
   * @param data The mission data to create
   */
  async createMission(data: CreateMissionData): Promise<MissionResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<MissionResponse>('/producer/missions', data)
    return response.data
  },
}
