import adminApiClient, { getCsrfCookie } from './adminApiClient'
import type { PaginationMeta } from './adminFinanceApi'

/**
 * Service admin de revue des appels de suspension UGC (story 5.4). Calque
 * adminAttendanceDisputesApi : GET sans CSRF, POST avec getCsrfCookie(). Le
 * contrat miroite EXACTEMENT AdminUgcSuspensionResource (5.3) — plus pauvre que
 * la Resource Face : deal n'a que owner_kind + product_name ; face n'a que
 * id/prenom/nom (PAS display_name, PAS owner_uuid, PAS missed_deadline_at).
 */
export interface AdminUgcSuspensionFace {
  id: number
  prenom: string
  nom: string
}

export interface AdminUgcSuspensionDeal {
  owner_kind: 'booking' | 'candidature'
  product_name: string
}

export interface AdminUgcSuspension {
  uuid: string
  reason: 'unboxing_deadline_missed' | 'avis_deadline_missed'
  reason_label: string
  suspended_at: string
  reactivated_at: string | null
  appeal_status: 'none' | 'pending' | 'accepted' | 'rejected'
  appeal_status_label: string
  face: AdminUgcSuspensionFace | null
  deal: AdminUgcSuspensionDeal | null
}

export const adminUgcSuspensionsApi = {
  async getSuspensions(params?: { page?: number }): Promise<{
    data: AdminUgcSuspension[]
    meta: PaginationMeta
    message: string
  }> {
    const response = await adminApiClient.get('/admin/ugc/suspensions', { params })
    return response.data
  },

  async reactivate(uuid: string): Promise<{ data: AdminUgcSuspension; message: string }> {
    await getCsrfCookie()
    const response = await adminApiClient.post(`/admin/ugc/suspensions/${uuid}/reactivate`)
    return response.data
  },

  async rejectAppeal(uuid: string): Promise<{ data: AdminUgcSuspension; message: string }> {
    await getCsrfCookie()
    const response = await adminApiClient.post(`/admin/ugc/suspensions/${uuid}/reject-appeal`)
    return response.data
  },
}
