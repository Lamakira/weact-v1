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
const mockIsReportingNoShow = ref(false)
const mockReportNoShow = vi.fn()
const mockFetchBooking = vi.fn()
const mockRefreshBooking = vi.fn()
const mockUserableType = ref('Face')
const mockUserId = ref(1)

vi.mock('@/features/booking/composables', () => ({
  useBookingDetail: () => ({
    booking: mockBooking,
    isLoading: mockIsLoading,
    error: mockError,
    fetchBooking: mockFetchBooking,
    notFound: ref(false),
    refresh: mockRefreshBooking,
  }),
  useBookingActions: () => ({
    isConfirming: mockIsConfirming,
    isAccepting: ref(false),
    isRefusing: ref(false),
    isCancelling: ref(false),
    isReportingNoShow: mockIsReportingNoShow,
    error: mockActionError,
    confirm: vi.fn(),
    accept: vi.fn(),
    refuse: vi.fn(),
    cancel: vi.fn(),
    reportNoShow: mockReportNoShow,
    clearError: vi.fn(),
  }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { get id() { return mockUserId.value }, get userable_type() { return mockUserableType.value } },
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
    id: 'booking-uuid-1',
    realtime_channel_key: 1,
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
    cancellation_reason: null,
    custom_cancellation_reason: null,
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

  await router.push('/face/bookings/booking-uuid-1')
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
    mockIsReportingNoShow.value = false
    mockUserableType.value = 'Face'
    mockUserId.value = 1
    mockFetchBooking.mockReset()
    mockRefreshBooking.mockReset()
    mockReportNoShow.mockReset()
  })

  it('loads the booking with the UUID route param on mount', async () => {
    await mountPage(makeBooking())

    expect(mockFetchBooking).toHaveBeenCalledWith('booking-uuid-1')
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

  it('shows a generic booking heading without exposing the booking id', async () => {
    const wrapper = await mountPage(makeBooking({ id: 42 }))

    expect(wrapper.text()).toContain('Demande de booking')
    expect(wrapper.text()).not.toContain('Demande de booking #42')
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

describe('FaceBookingDetailPage — duration display', () => {
  beforeEach(() => {
    mockBooking.value = null
    mockIsLoading.value = false
    mockError.value = null
    mockIsConfirming.value = false
    mockActionError.value = null
    mockIsReportingNoShow.value = false
    mockFetchBooking.mockReset()
    mockRefreshBooking.mockReset()
    mockReportNoShow.mockReset()
  })

  it('displays duration with "max" prefix', async () => {
    const wrapper = await mountPage(makeBooking({ duree_heures: 4 }))
    expect(wrapper.text()).toContain('max 4h')
  })
})

describe('FaceBookingDetailPage — no-show report button', () => {
  beforeEach(() => {
    mockBooking.value = null
    mockIsLoading.value = false
    mockError.value = null
    mockIsConfirming.value = false
    mockActionError.value = null
    mockIsReportingNoShow.value = false
    mockFetchBooking.mockReset()
    mockRefreshBooking.mockReset()
    mockReportNoShow.mockReset()
  })

  it('shows "Signaler une absence" button when Producer, status=paid, date_debut past', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const pastDate = new Date(Date.now() - 86400000).toISOString()
    const wrapper = await mountPage(makeBooking({ date_debut: pastDate, producer_id: 2 }))

    const btn = wrapper.find('[data-testid="report-no-show-btn"]')
    expect(btn.exists()).toBe(true)
    expect(btn.text()).toContain('Signaler une absence')
  })

  it('hides "Signaler une absence" button when user is Face', async () => {
    mockUserableType.value = 'Face'
    mockUserId.value = 1
    const pastDate = new Date(Date.now() - 86400000).toISOString()
    const wrapper = await mountPage(makeBooking({ date_debut: pastDate }))

    const btn = wrapper.find('[data-testid="report-no-show-btn"]')
    expect(btn.exists()).toBe(false)
  })

  it('hides "Signaler une absence" button when status is not paid', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const pastDate = new Date(Date.now() - 86400000).toISOString()
    const wrapper = await mountPage(makeBooking({ status: 'completed', date_debut: pastDate, producer_id: 2 }))

    const btn = wrapper.find('[data-testid="report-no-show-btn"]')
    expect(btn.exists()).toBe(false)
  })

  it('hides "Signaler une absence" button when date_debut is in the future', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const futureDate = new Date(Date.now() + 7 * 86400000).toISOString()
    const wrapper = await mountPage(makeBooking({ date_debut: futureDate, producer_id: 2 }))

    const btn = wrapper.find('[data-testid="report-no-show-btn"]')
    expect(btn.exists()).toBe(false)
  })

  it('shows the custom cancellation reason when present on the booking', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const wrapper = await mountPage(makeBooking({
      status: 'cancelled_by_producer',
      producer_id: 2,
      cancellation_reason: 'other',
      custom_cancellation_reason: 'Le client final a déplacé le tournage.',
    }))

    expect(wrapper.text()).toContain('Autre raison')
    expect(wrapper.text()).toContain('Le client final a déplacé le tournage.')
  })

  it('shows a friendly label for legacy price disagreement cancellations', async () => {
    const wrapper = await mountPage(makeBooking({
      status: 'cancelled_by_producer',
      cancellation_reason: 'price_disagreement',
    }))

    expect(wrapper.text()).toContain('Désaccord sur le prix')
    expect(wrapper.text()).not.toContain('price_disagreement')
  })
})
