import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import type { AdminBookingData } from '@/features/admin/services/adminBookingsApi'
import AdminBookingDetailPage from '../AdminBookingDetailPage.vue'

const mockFetchBooking = vi.fn()

let bookingRef = ref<AdminBookingData | null>(null)
let isLoadingRef = ref(false)
let errorRef = ref<string | null>(null)

vi.mock('@/features/admin/composables/useAdminBookings', () => ({
  useAdminBookings: () => ({
    booking: bookingRef,
    isLoading: isLoadingRef,
    error: errorRef,
    fetchBooking: mockFetchBooking,
  }),
}))

function makeBooking(overrides: Partial<AdminBookingData> = {}): AdminBookingData {
  return {
    id: 'bk-1',
    status: 'accepted',
    status_label: 'Acceptée',
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
    accepted_at: '2026-02-01T00:00:00.000Z',
    created_at: '2026-01-15T00:00:00.000Z',
    updated_at: '2026-01-16T00:00:00.000Z',
    ...overrides,
  }
}

async function mountPage(booking: AdminBookingData) {
  bookingRef.value = booking
  isLoadingRef.value = false
  errorRef.value = null

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/bookings', name: 'admin-bookings-list', component: { template: '<div>List</div>' } },
      { path: '/admin/bookings/:id', name: 'admin-booking-detail', component: AdminBookingDetailPage },
    ],
  })

  await router.push('/admin/bookings/bk-1')
  await router.isReady()

  const wrapper = mount(AdminBookingDetailPage, {
    global: { plugins: [router] },
  })

  await flushPromises()
  return wrapper
}

describe('AdminBookingDetailPage', () => {
  beforeEach(() => {
    bookingRef = ref<AdminBookingData | null>(null)
    isLoadingRef = ref(false)
    errorRef = ref<string | null>(null)
    mockFetchBooking.mockReset()
  })

  it('fetches the booking by the route id on mount', async () => {
    await mountPage(makeBooking())
    expect(mockFetchBooking).toHaveBeenCalledWith('bk-1')
  })

  it('renders both parties with their emails, and the full money breakdown', async () => {
    const wrapper = await mountPage(makeBooking())
    const text = wrapper.text().replace(/\s+/g, ' ')

    expect(text).toContain('Adjoua Dossou')
    expect(text).toContain('face@example.com')
    expect(text).toContain('Studio Lumière')
    expect(text).toContain('prod@example.com')
    expect(text).toContain('100 000 FCFA') // tarif_base
    expect(text).toContain('110 000 FCFA') // montant_total_producteur
    expect(text).toContain('90 000 FCFA') // montant_face_recoit
    expect(text).toContain('Acceptée')
  })

  it('shows the escrow card only when escrow data is present', async () => {
    const withoutEscrow = await mountPage(makeBooking({ escrow: null }))
    expect(withoutEscrow.text()).not.toContain('Escrow')

    const withEscrow = await mountPage(
      makeBooking({
        escrow: {
          status: 'locked',
          amount: 90000,
          locked_at: '2026-02-02T00:00:00.000Z',
          released_at: null,
          refunded_at: null,
        },
      }),
    )
    const text = withEscrow.text().replace(/\s+/g, ' ')
    expect(text).toContain('Escrow')
    expect(text).toContain('locked')
    expect(text).toContain('90 000 FCFA')
  })

  it('shows the cancellation block only when a cancellation reason is set', async () => {
    const active = await mountPage(makeBooking({ cancellation_reason: null }))
    expect(active.text()).not.toContain('Annulation')

    const cancelled = await mountPage(
      makeBooking({
        status: 'cancelled_by_face',
        status_label: 'Annulée par la Face',
        cancellation_reason: 'schedule_conflict',
      }),
    )
    expect(cancelled.text()).toContain('Annulation')
  })
})
