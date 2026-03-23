import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type {
  CreateMissionData,
  MissionResponse,
  MissionsListResponse,
  UpdateMissionData,
} from '../types'

export interface MissionPaymentData {
  payment_id: number
  montant_total: number
  nombre_faces: number
  checkout_url: string
  status: string
}

export interface ConfirmSelectionResponse {
  data: MissionPaymentData
  message: string
}

export interface PaymentStatusData {
  has_payment: boolean
  payment_id?: number
  status?: string
  paid_at?: string | null
  montant_total?: number
  mission_status: string
}

export interface PaymentStatusResponse {
  data: PaymentStatusData
}

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

  /**
   * Close a mission to stop accepting new candidatures
   * @param id The mission ID to close
   */
  async closeMission(id: number): Promise<MissionResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<MissionResponse>(`/producer/missions/${id}/close`)
    return response.data
  },

  /**
   * Reopen a closed mission to accept candidatures again
   * @param id The mission ID to reopen
   */
  async reopenMission(id: number): Promise<MissionResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<MissionResponse>(`/producer/missions/${id}/reopen`)
    return response.data
  },

  /**
   * Mark a mission as completed
   * @param id The mission ID to complete
   */
  async completeMission(id: number): Promise<MissionResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<MissionResponse>(`/producer/missions/${id}/complete`)
    return response.data
  },

  /**
   * Confirm face selection and initiate payment
   */
  async confirmSelection(missionId: number, candidatureIds: number[]): Promise<ConfirmSelectionResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<ConfirmSelectionResponse>(
      `/producer/missions/${missionId}/confirm-selection`,
      { candidature_ids: candidatureIds }
    )
    return response.data
  },

  /**
   * Poll payment status for a mission
   */
  async getPaymentStatus(missionId: number): Promise<PaymentStatusResponse> {
    const response = await apiClient.get<PaymentStatusResponse>(
      `/producer/missions/${missionId}/payment-status`
    )
    return response.data
  },
}
