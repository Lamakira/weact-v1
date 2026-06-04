import adminApiClient from './adminApiClient'

/**
 * Face data nested in an engagement row.
 *
 * `id` is the Face uuid (used to link to the admin Face detail page) and may be
 * null when the profile cannot be resolved. `whatsapp_number` is admin-only.
 */
export interface AdminEngagementFace {
  id: string | null
  display_name: string
  whatsapp_number: string | null
  has_whatsapp: boolean
}

export interface AdminEngagementProducer {
  display_name: string
}

/**
 * The engaged object: a mission (with a detail_id linking to the admin mission
 * page) or a booking (detail_id null — no admin booking detail page).
 */
export interface AdminEngagementObjet {
  label: string | null
  date: string | null
  detail_id: string | null
}

/**
 * A unified engagement row (one engaged Face on a booking or a mission).
 */
export interface AdminEngagementData {
  id: string
  type: 'booking' | 'mission'
  status: string
  status_label: string
  engaged_since: string | null
  montant_face_recoit: number | null
  face: AdminEngagementFace
  producer: AdminEngagementProducer
  objet: AdminEngagementObjet
}

export interface AdminEngagementListResponse {
  data: AdminEngagementData[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface AdminEngagementListParams {
  page?: number
  type?: 'booking' | 'mission'
  status?: string
  search?: string
}

/**
 * Admin engagements API service (read-only "Faces à contacter").
 */
export const adminEngagementsApi = {
  /**
   * Get the paginated, unified list of active engagements (bookings + missions).
   */
  async getEngagements(params?: AdminEngagementListParams): Promise<AdminEngagementListResponse> {
    const response = await adminApiClient.get<AdminEngagementListResponse>('/admin/engagements', {
      params,
    })
    return response.data
  },
}
