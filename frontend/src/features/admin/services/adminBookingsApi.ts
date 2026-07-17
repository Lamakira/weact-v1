import adminApiClient from './adminApiClient'

/**
 * A party (Face or Producer) as summarised in the admin booking payload.
 */
export interface AdminBookingParty {
  id: number
  name: string | null
  email: string
  role: string | null
}

/**
 * Escrow snapshot attached to a booking (null when no escrow row exists).
 */
export interface AdminBookingEscrow {
  status: string
  amount: number
  locked_at: string | null
  released_at: string | null
  refunded_at: string | null
}

/**
 * Booking data from the admin API (oversight view — both parties, full money).
 */
export interface AdminBookingData {
  id: string
  status: string
  status_label: string
  face: AdminBookingParty | null
  producer: AdminBookingParty | null
  type_contenu: string | null
  type_compensation: string | null
  type_compensation_label: string | null
  nom_produit: string | null
  valeur_produit: number | null
  nombre_videos: number | null
  lieu: string | null
  message: string | null
  tarif_base: number | null
  montant_total_producteur: number | null
  montant_face_recoit: number | null
  montant_remuneration: number | null
  commission_ugc: number | null
  payment_mode: string | null
  fedapay_transaction_id: string | null
  commission_paid_at: string | null
  commission_refund_requested_at: string | null
  commission_refunded_at: string | null
  commission_refund_reason: string | null
  commission_refund_reason_label: string | null
  escrow: AdminBookingEscrow | null
  cancellation_reason: string | null
  custom_cancellation_reason: string | null
  date_debut: string | null
  date_fin: string | null
  duree_heures: number | null
  accepted_at: string | null
  created_at: string
  updated_at: string
}

/**
 * Paginated booking list response.
 */
export interface AdminBookingListResponse {
  data: AdminBookingData[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

/**
 * Single booking detail response.
 */
export interface AdminBookingDetailResponse {
  data: AdminBookingData
  message: string
}

/**
 * Query params for listing bookings.
 */
export interface AdminBookingListParams {
  page?: number
  search?: string
  status?: string
}

/**
 * Admin booking management API service (read-only).
 */
export const adminBookingsApi = {
  /**
   * Get paginated list of bookings with optional search/filter.
   */
  async getBookings(params?: AdminBookingListParams): Promise<AdminBookingListResponse> {
    const response = await adminApiClient.get<AdminBookingListResponse>('/admin/bookings', {
      params,
    })
    return response.data
  },

  /**
   * Get a single booking by ID.
   */
  async getBooking(id: string): Promise<AdminBookingDetailResponse> {
    const response = await adminApiClient.get<AdminBookingDetailResponse>(`/admin/bookings/${id}`)
    return response.data
  },
}
