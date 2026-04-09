import apiClient from '@/services/apiClient'
import type {
  ApplyToMissionData,
  CandidatureResponse,
  CandidatureStatusType,
  FaceCandidatureListResponse,
  ProducerCandidatureListResponse,
  CandidateFullProfileResponse,
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
    missionId: string,
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

  /**
   * Get paginated list of candidatures for a Producer's mission
   * @param missionId The mission ID to get candidatures for
   * @param page Page number (default 1)
   * @param status Optional status filter
   * @returns Paginated candidatures with face data
   */
  async getMissionCandidatures(
    missionId: string,
    page: number = 1,
    status?: CandidatureStatusType | '',
  ): Promise<ProducerCandidatureListResponse> {
    const params = new URLSearchParams()
    params.append('page', String(page))
    if (status) {
      params.append('status', status)
    }

    const response = await apiClient.get<ProducerCandidatureListResponse>(
      `/producer/missions/${missionId}/candidatures?${params.toString()}`,
    )
    return response.data
  },

  /**
   * Get candidate's full profile (Producer only)
   * Producers can only view profiles of Faces who applied to their missions
   * @param faceId The Face ID to get profile for
   * @returns Full candidate profile data
   */
  async getCandidateProfile(faceId: string): Promise<CandidateFullProfileResponse> {
    const response = await apiClient.get<CandidateFullProfileResponse>(
      `/producer/candidates/${faceId}`,
    )
    return response.data
  },

  /**
   * Accept a candidature (Producer only)
   * Changes candidature status from "pending" to "accepted"
   * @param candidatureId The candidature ID to accept
   * @returns Updated candidature data with success message
   */
  async acceptCandidature(candidatureId: string): Promise<CandidatureResponse> {
    const response = await apiClient.post<CandidatureResponse>(
      `/producer/candidatures/${candidatureId}/accept`,
    )
    return response.data
  },

  /**
   * Reject a candidature (Producer only)
   * Changes candidature status from "pending" to "rejected"
   * @param candidatureId The candidature ID to reject
   * @returns Updated candidature data with success message
   */
  async rejectCandidature(candidatureId: string): Promise<CandidatureResponse> {
    const response = await apiClient.post<CandidatureResponse>(
      `/producer/candidatures/${candidatureId}/reject`,
    )
    return response.data
  },

  /**
   * Confirm participation in a mission (Face only)
   * Changes candidature status from "accepted" to "confirmed"
   * @param candidatureId The candidature ID to confirm
   * @returns Updated candidature data with success message
   */
  async confirmCandidature(candidatureId: string): Promise<CandidatureResponse> {
    const response = await apiClient.post<CandidatureResponse>(
      `/face/candidatures/${candidatureId}/confirm`,
    )
    return response.data
  },

  /**
   * Cancel a pending candidature (Face only)
   * Changes candidature status from "pending" to "cancelled"
   * @param candidatureId The candidature ID to cancel
   * @returns Success message
   */
  async cancelCandidature(candidatureId: string): Promise<{ message: string }> {
    const response = await apiClient.post<{ message: string }>(
      `/face/candidatures/${candidatureId}/cancel`,
    )
    return response.data
  },
}
