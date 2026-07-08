import adminApiClient, { getCsrfCookie } from './adminApiClient'
import type { PaginationMeta } from './adminFinanceApi'

export interface AdminDisputeMissionPayload {
  id: string
  titre: string
  date_tournage: string | null
  producer: { id: string; display_name: string } | null
}

export interface AdminDisputeFacePayload {
  id: string
  display_name: string
  profile_photo_url: string | null
  // 150px avatar variant — server falls back to the original while pending
  profile_photo_thumbnail_url: string | null
}

export interface AdminDispute {
  id: number
  mission: AdminDisputeMissionPayload | null
  face: AdminDisputeFacePayload | null
  montant_face_recoit: number
  attendance_status: string
  escrow_status: string
  notified_at: string | null
  disputed_at: string | null
}

export type DisputeOutcome = 'face' | 'producer'

export const adminAttendanceDisputesApi = {
  async getDisputes(params?: { page?: number }): Promise<{
    data: AdminDispute[]
    meta: PaginationMeta
    message: string
  }> {
    const response = await adminApiClient.get('/admin/attendance-disputes', { params })
    return response.data
  },

  async resolveDispute(
    id: number,
    outcome: DisputeOutcome,
    notes: string,
  ): Promise<{ data: AdminDispute; message: string }> {
    await getCsrfCookie()
    const response = await adminApiClient.post(`/admin/attendance-disputes/${id}/resolve`, {
      outcome,
      notes,
    })
    return response.data
  },
}
