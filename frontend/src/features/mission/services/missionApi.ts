import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { CreateMissionData, MissionResponse, UpdateMissionData } from '../types'

/**
 * Mission API service
 */
export const missionApi = {
  /**
   * Get a mission by ID
   * @param id The mission ID
   */
  async getMission(id: number): Promise<MissionResponse> {
    const response = await apiClient.get<MissionResponse>(`/producer/missions/${id}`)
    return response.data
  },

  /**
   * Create a new mission
   * @param data The mission data to create
   */
  async createMission(data: CreateMissionData): Promise<MissionResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<MissionResponse>('/producer/missions', data)
    return response.data
  },

  /**
   * Update an existing mission
   * @param id The mission ID
   * @param data The mission data to update
   */
  async updateMission(id: number, data: UpdateMissionData): Promise<MissionResponse> {
    await getCsrfCookie()

    const response = await apiClient.put<MissionResponse>(`/producer/missions/${id}`, data)
    return response.data
  },
}
