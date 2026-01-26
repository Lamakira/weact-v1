import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type {
  CreateMissionData,
  MissionResponse,
  MissionsListResponse,
  UpdateMissionData,
} from '../types'

/**
 * Mission API service
 */
export const missionApi = {
  /**
   * Get all missions for the authenticated producer
   * @returns List of missions ordered by most recent first
   */
  async getMissions(): Promise<MissionsListResponse> {
    const response = await apiClient.get<MissionsListResponse>('/producer/missions')
    return response.data
  },

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

  /**
   * Delete a mission
   * @param id The mission ID to delete
   */
  async deleteMission(id: number): Promise<{ message: string }> {
    await getCsrfCookie()

    const response = await apiClient.delete<{ message: string }>(`/producer/missions/${id}`)
    return response.data
  },
}
