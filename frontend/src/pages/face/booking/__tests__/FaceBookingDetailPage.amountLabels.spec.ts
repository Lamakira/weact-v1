import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import FaceBookingDetailPage from '../FaceBookingDetailPage.vue'

/**
 * Libellés du montant de la fiche booking : « À payer » n'apparaît que tant qu'il reste à payer.
 * BookingPricingBreakdown n'est PAS stubé ici : on vérifie le rendu réel du bloc d'argent.
 */

const mockBooking = ref<Record<string, unknown> | null>(null)
const mockFetchBooking = vi.fn()
const mockUserableType = ref('Face')
const mockUserId = ref(1)

const echoMock = vi.hoisted(() => {
  const channel = {
    listen: vi.fn(() => channel),
    stopListening: vi.fn(() => channel),
  }
  return { channel, privateMock: vi.fn(() => channel) }
})

vi.mock('@/plugins/echo', () => ({ echo: { private: echoMock.privateMock } }))

vi.mock('@/features/booking/services/bookingApi', () => ({
  bookingApi: { checkPaymentStatus: vi.fn(), checkCommissionStatus: vi.fn() },
}))

vi.mock('@/features/booking/composables', () => ({
  useBookingDetail: () => ({
    booking: mockBooking,
    isLoading: ref(false),
    error: ref(null),
    fetchBooking: mockFetchBooking,
    notFound: ref(false),
    refresh: vi.fn(),
  }),
  useBookingActions: () => ({
    isConfirming: ref(false),
    isAccepting: ref(false),
    isRefusing: ref(false),
    isCancelling: ref(false),
    isReportingNoShow: ref(false),
    isContesting: ref(false),
    error: ref(null),
    errorCode: ref(null),
    errorStatus: ref(null),
    confirm: vi.fn(),
    accept: vi.fn(),
    refuse: vi.fn(),
    cancel: vi.fn(),
    reportNoShow: vi.fn(),
    contest: vi.fn(),
    clearError: vi.fn(),
  }),
}))

vi.mock('@/composables/useUgcShipment', () => ({
  useUgcShipment: () => ({
    isSubmitting: ref(false),
    error: ref(null),
    errorCode: ref(null),
    confirmShipment: vi.fn(),
    confirmReceipt: vi.fn(),
    clearError: vi.fn(),
  }),
}))

vi.mock('@/composables/useUgcDeliverable', () => ({
  useUgcDeliverable: () => ({
    isUploading: ref(false),
    uploadProgress: ref(null),
    error: ref(null),
    errorCode: ref(null),
    uploadDeliverable: vi.fn(),
    validateFile: vi.fn(() => ({ valid: true })),
    clearError: vi.fn(),
  }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: {
      get id() {
        return mockUserId.value
      },
      get userable_type() {
        return mockUserableType.value
      },
    },
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

const HOUR = 3600 * 1000

function makeBooking(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: 'booking-uuid-1',
    realtime_channel_key: 7,
    face_id: 1,
    producer_id: 2,
    status: 'pending',
    date_debut: new Date(Date.now() + 5 * 24 * HOUR).toISOString(),
    date_fin: new Date(Date.now() + 5 * 24 * HOUR).toISOString(),
    duree_heures: 8,
    type_contenu: 'Publicité',
    lieu: 'Cotonou',
    tarif_base: 100000,
    montant_face_recoit: 85000,
    montant_total_producteur: 110000,
    fedapay_transaction_id: null,
    cancellation_reason: null,
    custom_cancellation_reason: null,
    accepted_at: null,
    can_accept: false,
    can_refuse: false,
    can_pay: false,
    can_rate: false,
    my_rating: null,
    face: {
      id: 1,
      userable_type: 'Face',
      userable: {
        prenom: 'Adjoua',
        nom: 'Kouassi',
        username: 'adjoua',
        ville: 'Cotonou',
        average_rating: 4.8,
        ratings_count: 23,
        has_elite_badge: true,
        thumbnail_url: null,
        profile_photo_url: null,
      },
    },
    producer: {
      id: 2,
      userable_type: 'Producer',
      userable: {
        display_name: 'Tropical Bénin',
        type: 'agency',
        average_rating: 4.6,
        ratings_count: 9,
        thumbnail_url: null,
        profile_photo_url: null,
      },
    },
    ...overrides,
  }
}

async function mountAs(role: 'producer' | 'face', booking: Record<string, unknown>) {
  mockUserableType.value = role === 'face' ? 'Face' : 'Producer'
  mockUserId.value = role === 'face' ? 1 : 2
  mockBooking.value = booking

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/face/bookings', name: 'face-bookings', component: { template: '<div/>' } },
      { path: '/producer/bookings', name: 'producer-bookings', component: { template: '<div/>' } },
      { path: '/face/bookings/:id', name: 'face-booking-detail', component: FaceBookingDetailPage },
      { path: '/pricing', name: 'pricing', component: { template: '<div/>' } },
    ],
  })
  await router.push('/face/bookings/booking-uuid-1')
  await router.isReady()

  const wrapper = mount(FaceBookingDetailPage, {
    global: {
      plugins: [router],
      stubs: {
        PaymentOverlay: true,
        UgcPaymentOverlay: true,
        UgcEngagementModal: true,
        UgcBookingTimeline: true,
        UgcShipmentForm: true,
        UgcShipmentTrackingCard: true,
        UgcFaceTrackingCard: true,
        ConfirmModal: true,
        BookingChat: true,
        CancellationDialog: true,
        BookingRatingForm: true,
      },
    },
  })
  await flushPromises()
  return wrapper
}

type Mounted = Awaited<ReturnType<typeof mountAs>>
const title = (wrapper: Mounted) => wrapper.find('[data-testid="booking-amount-title"]').text()
const totalLabel = (wrapper: Mounted) => wrapper.find('[data-testid="booking-amount-total-label"]').text()

beforeEach(() => {
  mockBooking.value = null
  mockFetchBooking.mockReset()
  echoMock.privateMock.mockClear()
})

describe('fiche booking : libellés du montant selon le statut', () => {
  it('Producteur, booking payé : « Total payé », jamais « À payer »', async () => {
    const wrapper = await mountAs('producer', makeBooking({ status: 'paid', was_paid: true }))

    expect(title(wrapper)).toBe('Votre paiement')
    expect(totalLabel(wrapper)).toBe('Total payé')
    expect(wrapper.text()).not.toContain('À payer')
    expect(wrapper.text()).not.toContain('Total à payer')
    wrapper.unmount()
  })

  it('Producteur, booking accepté : « Total à payer »', async () => {
    const wrapper = await mountAs('producer', makeBooking({ status: 'accepted', accepted_at: '2026-10-19T10:00:00Z' }))

    expect(title(wrapper)).toBe('À payer')
    expect(totalLabel(wrapper)).toBe('Total à payer')
    wrapper.unmount()
  })

  it('Producteur, booking annulé sans paiement : « Montant prévu » et « Non payé »', async () => {
    const wrapper = await mountAs('producer', makeBooking({ status: 'cancelled_by_producer' }))

    expect(title(wrapper)).toBe('Montant prévu')
    expect(totalLabel(wrapper)).toBe('Non payé')
    expect(wrapper.text()).not.toContain('Total à payer')
    wrapper.unmount()
  })

  it('Face, booking terminé : « Vous avez reçu »', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'completed', was_paid: true }))

    expect(title(wrapper)).toBe('Votre rémunération')
    expect(totalLabel(wrapper)).toBe('Vous avez reçu')
    expect(wrapper.text()).not.toContain('Vous recevrez')
    wrapper.unmount()
  })

  it('Face, booking payé en cours : « Vous recevrez »', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'paid', was_paid: true }))

    expect(totalLabel(wrapper)).toBe('Vous recevrez')
    wrapper.unmount()
  })
})

describe('garde-fou : aucun libellé « à payer » écrit en dur dans la fiche booking', () => {
  it('FaceBookingDetailPage.vue ne contient ni « Total à payer » ni « À payer » hors commentaires', () => {
    const source = readFileSync(resolve(__dirname, '../FaceBookingDetailPage.vue'), 'utf8')
    const code = source
      .replace(/<!--[\s\S]*?-->/g, '')
      .replace(/\/\*[\s\S]*?\*\//g, '')
      .replace(/^\s*\/\/.*$/gm, '')

    expect(code).not.toContain('Total à payer')
    expect(code).not.toContain('À payer')
  })
})
