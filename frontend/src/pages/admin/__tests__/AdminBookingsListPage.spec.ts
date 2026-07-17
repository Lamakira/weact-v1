import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import type { AdminBookingData } from '@/features/admin/services/adminBookingsApi'
import { BookingStatusLabel } from '@/features/booking/types/booking'
import AdminBookingsListPage from '../AdminBookingsListPage.vue'

const mockFetchBookings = vi.fn()

let bookingsRef = ref<AdminBookingData[]>([])
let paginationRef = ref<{
  current_page: number
  last_page: number
  per_page: number
  total: number
} | null>(null)
let isLoadingRef = ref(false)
let errorRef = ref<string | null>(null)

vi.mock('@/features/admin/composables/useAdminBookings', () => ({
  useAdminBookings: () => ({
    bookings: bookingsRef,
    pagination: paginationRef,
    isLoading: isLoadingRef,
    error: errorRef,
    fetchBookings: mockFetchBookings,
  }),
}))

function makeBooking(overrides: Partial<AdminBookingData> = {}): AdminBookingData {
  return {
    id: 'bk-base',
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
    date_debut: null,
    date_fin: null,
    duree_heures: 8,
    accepted_at: null,
    created_at: '2026-01-15T00:00:00.000Z',
    updated_at: '2026-01-16T00:00:00.000Z',
    ...overrides,
  }
}

async function mountPage() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/bookings', name: 'admin-bookings-list', component: AdminBookingsListPage },
      {
        path: '/admin/bookings/:id',
        name: 'admin-booking-detail',
        component: { template: '<div>Detail</div>' },
      },
    ],
  })

  await router.push('/admin/bookings')
  await router.isReady()

  const pushSpy = vi.spyOn(router, 'push')

  const wrapper = mount(AdminBookingsListPage, {
    global: { plugins: [router] },
  })

  await flushPromises()
  return { wrapper, pushSpy }
}

describe('AdminBookingsListPage', () => {
  beforeEach(() => {
    bookingsRef = ref<AdminBookingData[]>([])
    paginationRef = ref(null)
    isLoadingRef = ref(false)
    errorRef = ref<string | null>(null)
    vi.clearAllMocks()
  })

  it('fetches bookings on mount', async () => {
    await mountPage()
    expect(mockFetchBookings).toHaveBeenCalledWith({ page: 1 })
  })

  it('renders a row per booking with both parties, amount and status', async () => {
    bookingsRef.value = [
      makeBooking({ id: 'bk-1' }),
      makeBooking({ id: 'bk-2', status: 'completed', status_label: 'Terminée' }),
    ]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 2 }

    const { wrapper } = await mountPage()

    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(2)
    const text = wrapper.text()
    expect(text).toContain('Adjoua Dossou')
    expect(text).toContain('face@example.com')
    expect(text).toContain('Studio Lumière')
    expect(text).toContain('prod@example.com')
    // Amount = montant_total_producteur, formatted FCFA (non-breaking space grouping).
    expect(text.replace(/\s+/g, ' ')).toContain('110 000 FCFA')
    expect(text).toContain('En attente')
    expect(text).toContain('Terminée')
  })

  it('offers all 13 booking statuses in the filter', async () => {
    const { wrapper } = await mountPage()
    const options = wrapper.find('select').findAll('option')
    // 13 statuses + the "Tous les statuts" default.
    expect(options).toHaveLength(Object.keys(BookingStatusLabel).length + 1)
    expect(options[0].text()).toBe('Tous les statuts')
  })

  it('navigates to the detail page when a row is clicked', async () => {
    bookingsRef.value = [makeBooking({ id: 'bk-42' })]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 1 }

    const { wrapper, pushSpy } = await mountPage()
    await wrapper.find('tbody tr').trigger('click')

    expect(pushSpy).toHaveBeenCalledWith({ name: 'admin-booking-detail', params: { id: 'bk-42' } })
  })

  it('re-fetches from page 1 with the status param when the filter changes', async () => {
    const { wrapper } = await mountPage()
    mockFetchBookings.mockClear()

    await wrapper.find('select').setValue('no_show')
    await flushPromises()

    expect(mockFetchBookings).toHaveBeenCalledWith({ page: 1, status: 'no_show' })
  })

  it('shows the empty state when there are no bookings', async () => {
    bookingsRef.value = []
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 0 }

    const { wrapper } = await mountPage()
    expect(wrapper.text()).toContain('Aucune réservation trouvée')
  })
})
