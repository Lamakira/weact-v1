import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useAdminBookings } from '../useAdminBookings'
import { adminBookingsApi } from '../../services/adminBookingsApi'
import type {
  AdminBookingListResponse,
  AdminBookingDetailResponse,
  AdminBookingData,
} from '../../services/adminBookingsApi'

vi.mock('../../services/adminBookingsApi', () => ({
  adminBookingsApi: {
    getBookings: vi.fn(),
    getBooking: vi.fn(),
  },
}))

vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorMessage: vi.fn(
    (err: unknown) =>
      (err as { response?: { data?: { message?: unknown } } })?.response?.data?.message ?? null,
  ),
}))

function makeBooking(overrides: Partial<AdminBookingData> = {}): AdminBookingData {
  return {
    id: 'bk-1',
    status: 'pending',
    status_label: 'En attente',
    face: { id: 5, name: 'Adjoua Dossou', email: 'face@example.com', role: 'Face' },
    producer: { id: 9, name: 'Studio Lumière', email: 'prod@example.com', role: 'Producer' },
    type_contenu: 'Publicité',
    type_compensation: null,
    type_compensation_label: null,
    nom_produit: null,
    valeur_produit: null,
    nombre_videos: null,
    lieu: 'Cotonou',
    message: null,
    tarif_base: 100000,
    montant_total_producteur: 110000,
    montant_face_recoit: 90000,
    montant_remuneration: null,
    commission_ugc: null,
    payment_mode: null,
    fedapay_transaction_id: null,
    commission_paid_at: null,
    commission_refund_requested_at: null,
    commission_refunded_at: null,
    commission_refund_reason: null,
    commission_refund_reason_label: null,
    escrow: null,
    cancellation_reason: null,
    custom_cancellation_reason: null,
    date_debut: '2026-03-01T00:00:00.000Z',
    date_fin: '2026-03-02T00:00:00.000Z',
    duree_heures: 8,
    accepted_at: null,
    created_at: '2026-01-15T00:00:00.000Z',
    updated_at: '2026-01-16T00:00:00.000Z',
    ...overrides,
  }
}

describe('useAdminBookings', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('fetchBookings', () => {
    it('fetches paginated bookings and sets state', async () => {
      const mockResponse: AdminBookingListResponse = {
        data: [makeBooking({ id: 'bk-1' }), makeBooking({ id: 'bk-2', status: 'completed' })],
        meta: { current_page: 1, last_page: 2, per_page: 15, total: 20 },
      }
      vi.mocked(adminBookingsApi.getBookings).mockResolvedValue(mockResponse)

      const { bookings, pagination, isLoading, error, fetchBookings } = useAdminBookings()

      expect(bookings.value).toEqual([])
      expect(isLoading.value).toBe(false)

      const promise = fetchBookings({ page: 1 })
      expect(isLoading.value).toBe(true)

      await promise

      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(bookings.value).toHaveLength(2)
      expect(bookings.value[0].id).toBe('bk-1')
      expect(pagination.value?.total).toBe(20)
    })

    it('passes search and status params to the API', async () => {
      vi.mocked(adminBookingsApi.getBookings).mockResolvedValue({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
      })

      const { fetchBookings } = useAdminBookings()
      await fetchBookings({ page: 2, search: 'face@example.com', status: 'no_show' })

      expect(adminBookingsApi.getBookings).toHaveBeenCalledWith({
        page: 2,
        search: 'face@example.com',
        status: 'no_show',
      })
    })

    it('handles fetch error and clears data', async () => {
      vi.mocked(adminBookingsApi.getBookings).mockRejectedValue({
        response: { data: { message: 'Erreur serveur' } },
      })

      const { bookings, pagination, error, fetchBookings } = useAdminBookings()
      await fetchBookings()

      expect(error.value).toBe('Erreur serveur')
      expect(bookings.value).toEqual([])
      expect(pagination.value).toBeNull()
    })
  })

  describe('fetchBooking', () => {
    it('fetches a single booking by ID', async () => {
      const mockResponse: AdminBookingDetailResponse = {
        data: makeBooking({ id: 'bk-42', status: 'accepted', status_label: 'Acceptée' }),
        message: 'OK',
      }
      vi.mocked(adminBookingsApi.getBooking).mockResolvedValue(mockResponse)

      const { booking, error, fetchBooking } = useAdminBookings()
      await fetchBooking('bk-42')

      expect(adminBookingsApi.getBooking).toHaveBeenCalledWith('bk-42')
      expect(booking.value?.id).toBe('bk-42')
      expect(booking.value?.status).toBe('accepted')
      expect(error.value).toBeNull()
    })

    it('handles fetch booking error and clears booking', async () => {
      vi.mocked(adminBookingsApi.getBooking).mockRejectedValue({
        response: { data: { message: 'Réservation introuvable' } },
      })

      const { booking, error, fetchBooking } = useAdminBookings()
      await fetchBooking('bk-x')

      expect(error.value).toBe('Réservation introuvable')
      expect(booking.value).toBeNull()
    })
  })
})
