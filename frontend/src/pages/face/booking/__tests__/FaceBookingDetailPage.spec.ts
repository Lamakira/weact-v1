import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import FaceBookingDetailPage from '../FaceBookingDetailPage.vue'

const mockBooking = ref<Record<string, unknown> | null>(null)
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockIsConfirming = ref(false)
const mockActionError = ref<string | null>(null)

vi.mock('@/features/booking/composables', () => ({
  useBookingDetail: () => ({
    booking: mockBooking,
    isLoading: mockIsLoading,
    error: mockError,
    fetchBooking: vi.fn(),
  }),
  useBookingActions: () => ({
    isConfirming: mockIsConfirming,
    isAccepting: ref(false),
    isRefusing: ref(false),
    actionError: mockActionError,
    confirm: vi.fn(),
    accept: vi.fn(),
    refuse: vi.fn(),
    clearError: vi.fn(),
  }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { id: 1, userable_type: 'App\\Models\\Face' },
    isFace: true,
    isProducer: false,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
  }),
}))

function makeBooking(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: 1,
    face_id: 1,
    producer_id: 2,
    status: 'paid',
    date_debut: new Date(Date.now() - 86400000).toISOString(), // yesterday
    date_fin: new Date(Date.now() + 86400000).toISOString(),
    duree_heures: 8,
    type_contenu: 'Shooting photo',
    description: 'Test booking',
    montant_base: 50000,
    commission_face: 7500,
    commission_producteur: 7500,
    montant_face_recoit: 42500,
    montant_total_producteur: 57500,
    accepted_at: new Date().toISOString(),
    face: { id: 1, prenom: 'Jane', nom: 'Doe', username: 'jane', profile_photo_url: null, average_rating: 4.5 },
    producer: { id: 2, prenom: 'John', nom: 'Smith', username: 'john', profile_photo_url: null, average_rating: 4.0, agency_name: null },
    can_rate: false,
    existing_rating: null,
    ...overrides,
  }
}

async function mountPage(booking: Record<string, unknown> | null = null) {
  mockBooking.value = booking
  mockIsLoading.value = false
  mockError.value = null

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/face/bookings', name: 'face-bookings', component: { template: '<div/>' } },
      { path: '/face/bookings/:id', name: 'face-booking-detail', component: FaceBookingDetailPage },
    ],
  })

  await router.push('/face/bookings/1')
  await router.isReady()

  const wrapper = mount(FaceBookingDetailPage, {
    global: {
      plugins: [router],
      stubs: {
        BookingTimeline: { template: '<div/>' },
        BookingStatusBadge: { template: '<div/>' },
        PaymentOverlay: { template: '<div/>' },
        BookingPricingBreakdown: { template: '<div/>' },
        BookingChat: { template: '<div/>' },
        CancellationDialog: { template: '<div/>' },
        BookingRatingForm: { template: '<div/>' },
      },
    },
  })

  await flushPromises()
  return wrapper
}

describe('FaceBookingDetailPage — shooting date guard', () => {
  beforeEach(() => {
    mockBooking.value = null
    mockIsLoading.value = false
    mockError.value = null
    mockIsConfirming.value = false
    mockActionError.value = null
  })

  it('disables confirm button when shooting date is in the future', async () => {
    const futureDate = new Date(Date.now() + 7 * 86400000).toISOString() // +7 days
    const wrapper = await mountPage(makeBooking({ date_debut: futureDate }))

    const buttons = wrapper.findAll('button')
    const confirmButton = buttons.find((b) => b.text().includes('Confirmer'))

    expect(confirmButton).toBeDefined()
    expect(confirmButton!.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain("La confirmation n'est possible qu'à partir du jour du tournage")
  })

  it('enables confirm button when shooting date is in the past', async () => {
    const pastDate = new Date(Date.now() - 86400000).toISOString() // yesterday
    const wrapper = await mountPage(makeBooking({ date_debut: pastDate }))

    const buttons = wrapper.findAll('button')
    const confirmButton = buttons.find((b) => b.text().includes('Confirmer'))

    expect(confirmButton).toBeDefined()
    expect(confirmButton!.attributes('disabled')).toBeUndefined()
    expect(wrapper.text()).not.toContain("La confirmation n'est possible qu'à partir du jour du tournage")
  })

  it('re-enables confirm button when the shooting time passes while the page stays open', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-04-05T12:00:00Z'))

    try {
      const wrapper = await mountPage(makeBooking({ date_debut: '2026-04-05T12:00:30Z' }))

      let buttons = wrapper.findAll('button')
      let confirmButton = buttons.find((b) => b.text().includes('Confirmer'))

      expect(confirmButton).toBeDefined()
      expect(confirmButton!.attributes('disabled')).toBeDefined()

      await vi.advanceTimersByTimeAsync(60000)
      await flushPromises()

      buttons = wrapper.findAll('button')
      confirmButton = buttons.find((b) => b.text().includes('Confirmer'))

      expect(confirmButton).toBeDefined()
      expect(confirmButton!.attributes('disabled')).toBeUndefined()
    } finally {
      vi.useRealTimers()
    }
  })
})
