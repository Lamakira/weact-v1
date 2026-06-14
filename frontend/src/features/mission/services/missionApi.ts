import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type {
  CreateMissionData,
  MissionResponse,
  MissionsListResponse,
  UpdateMissionData,
} from '../types'
import type {
  AttendanceFormResponse,
  ValidateAttendancePayload,
  ValidateAttendanceResponse,
} from '../types/attendance'

interface MissionPaymentData {
  payment_id: number
  montant_total: number
  nombre_faces: number
  checkout_url: string
  status: string
}

interface ConfirmSelectionResponse {
  data: MissionPaymentData
  message: string
}

export interface PaymentStatusData {
  has_payment: boolean
  /**
   * True only when the mission payment is still pending AND a FedaPay
   * transaction id is on file. Drives the SPA's decision to start polling
   * and to render the "Paiement en attente de confirmation..." banner.
   * See FIX-19.3 — Guard false pending payment UI.
   */
  is_trackable: boolean
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
  async getMission(id: string): Promise<MissionResponse> {
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
  async updateMission(id: string, data: UpdateMissionData): Promise<MissionResponse> {
    await getCsrfCookie()

    const response = await apiClient.put<MissionResponse>(`/producer/missions/${id}`, data)
    return response.data
  },

  /**
   * Delete a mission
   * @param id The mission ID to delete
   */
  async deleteMission(id: string): Promise<{ message: string }> {
    await getCsrfCookie()

    const response = await apiClient.delete<{ message: string }>(`/producer/missions/${id}`)
    return response.data
  },

  /**
   * Close a mission to stop accepting new candidatures
   * @param id The mission ID to close
   */
  async closeMission(id: string): Promise<MissionResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<MissionResponse>(`/producer/missions/${id}/close`)
    return response.data
  },

  /**
   * Reopen a closed mission to accept candidatures again
   * @param id The mission ID to reopen
   */
  async reopenMission(id: string): Promise<MissionResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<MissionResponse>(`/producer/missions/${id}/reopen`)
    return response.data
  },

  /**
   * Mark a mission as completed
   * @param id The mission ID to complete
   */
  async completeMission(id: string): Promise<MissionResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<MissionResponse>(`/producer/missions/${id}/complete`)
    return response.data
  },

  /**
   * Get the attendance validation form for a mission (FIX-26.7)
   * @param missionUuid The mission UUID
   */
  async getAttendanceForm(missionUuid: string): Promise<AttendanceFormResponse> {
    const response = await apiClient.get<AttendanceFormResponse>(
      `/producer/missions/${missionUuid}/attendance-form`
    )
    return response.data
  },

  /**
   * Submit Producer attendance decisions for a mission (FIX-26.7)
   * @param missionUuid The mission UUID
   * @param payload The entries decisions
   */
  async validateAttendance(
    missionUuid: string,
    payload: ValidateAttendancePayload
  ): Promise<ValidateAttendanceResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<ValidateAttendanceResponse>(
      `/producer/missions/${missionUuid}/validate-attendance`,
      payload
    )
    return response.data
  },

  /**
   * Confirm face selection and initiate payment
   */
  async confirmSelection(missionId: string, candidatureIds: string[]): Promise<ConfirmSelectionResponse> {
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
  async getPaymentStatus(missionId: string): Promise<PaymentStatusResponse> {
    const response = await apiClient.get<PaymentStatusResponse>(
      `/producer/missions/${missionId}/payment-status`
    )
    return response.data
  },

  /**
   * Initiate the WeAct commission payment to publish a UGC mission (Producer only).
   * Charges `commission_ugc` only. Returns the mission + FedaPay checkout URL.
   */
  async payCommission(missionId: string): Promise<MissionResponse & { checkout_url: string }> {
    await getCsrfCookie()

    const response = await apiClient.post<MissionResponse & { checkout_url: string }>(
      `/producer/missions/${missionId}/pay-commission`,
    )
    return response.data
  },

  /**
   * Poll FedaPay and publish the UGC mission if the commission is approved
   * (fallback when the webhook is delayed). Idempotent. Mission settles to `published`.
   */
  async getCommissionStatus(
    missionId: string,
  ): Promise<MissionResponse & { commission_payment_status?: 'paid' | 'pending' | 'failed' }> {
    const response = await apiClient.get<
      MissionResponse & { commission_payment_status?: 'paid' | 'pending' | 'failed' }
    >(`/producer/missions/${missionId}/commission-status`)
    return response.data
  },
}
