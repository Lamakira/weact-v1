import adminApiClient, { getCsrfCookie } from './adminApiClient'

export interface AdminBookingDisputeParty {
  display_name: string
}

// Litige ouvert par la Face (absence signalée / annulation Producteur tardive) — à trancher.
export interface AdminBookingDispute {
  id: string
  status: string
  face: AdminBookingDisputeParty
  producer: AdminBookingDisputeParty
  date_debut: string | null
  date_fin: string | null
  montant_total_producteur: number
  montant_face_recoit: number
  reported_at: string | null
  settlement_due_at: string | null
  disputed_at: string | null
  dispute_message: string | null
  cancellation_reason: string | null
}

// Booking payé sans suite (lecture seule) — jamais payé automatiquement s'il est « legacy ».
export interface AdminStalePaidBooking {
  id: string
  face: AdminBookingDisputeParty
  producer: AdminBookingDisputeParty
  date_debut: string | null
  date_fin: string | null
  montant_total_producteur: number
  montant_face_recoit: number
  days_since_date_fin: number
  // Échéance du paiement automatique (null : ancien booking ou relance pas encore partie)
  auto_complete_due_at: string | null
  // Fin du tournage de plus de 30 jours : jamais payé automatiquement
  is_legacy: boolean
}

export interface AdminBookingDisputesPayload {
  disputes: AdminBookingDispute[]
  stale_paid: AdminStalePaidBooking[]
}

export type BookingDisputeOutcome = 'favor_face' | 'favor_producer'

export const adminBookingDisputesApi = {
  async getDisputes(): Promise<{ data: AdminBookingDisputesPayload; message: string }> {
    const response = await adminApiClient.get('/admin/booking-disputes')
    return response.data
  },

  async resolveDispute(
    id: string,
    outcome: BookingDisputeOutcome,
    notes: string,
  ): Promise<{ data: AdminBookingDispute | null; message: string }> {
    await getCsrfCookie()
    const response = await adminApiClient.post(`/admin/booking-disputes/${id}/resolve`, {
      outcome,
      notes,
    })
    return response.data
  },
}
