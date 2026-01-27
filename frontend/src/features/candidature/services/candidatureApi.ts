import apiClient from '@/services/apiClient'
import type { ApplyToMissionData, CandidatureResponse } from '../types'

/**
 * Candidature API service
 * Endpoints for Face users to manage their candidatures
 */
export const candidatureApi = {
  /**
   * Apply to a mission as a Face
   * @param missionId The mission ID to apply to
   * @param data Optional motivation message
   * @returns Candidature data with success message
   */
  async applyToMission(
    missionId: number,
    data?: ApplyToMissionData,
  ): Promise<CandidatureResponse> {
    const response = await apiClient.post<CandidatureResponse>(
      `/face/missions/${missionId}/apply`,
      data || {},
    )
    return response.data
  },
}
