import apiClient from '@/services/apiClient'
import type {
  ApplyToMissionData,
  CandidatureResponse,
  CandidatureStatusType,
  FaceCandidatureListResponse,
} from '../types'

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

  /**
   * Get paginated list of Face's candidatures
   * @param page Page number (default 1)
   * @param status Optional status filter
   * @returns Paginated candidatures with mission and producer data
   */
  async getCandidatures(
    page: number = 1,
    status?: CandidatureStatusType | '',
  ): Promise<FaceCandidatureListResponse> {
    const params = new URLSearchParams()
    params.append('page', String(page))
    if (status) {
      params.append('status', status)
    }

    const response = await apiClient.get<FaceCandidatureListResponse>(
      `/face/candidatures?${params.toString()}`,
    )
    return response.data
  },
}
