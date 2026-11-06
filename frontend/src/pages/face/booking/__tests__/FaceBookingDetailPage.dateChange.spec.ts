import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import FaceBookingDetailPage from '../FaceBookingDetailPage.vue'
import { dateChangeApi } from '@/features/dateChange/services/dateChangeApi'
import type { BookingDateChange, BookingDateChangeLatest } from '@/features/dateChange/types'

/** Changement de date d'un booking : bouton d'en-tête, bloc au-dessus de « Et ensuite ? », réponses. */

const mockBooking = ref<Record<string, unknown> | null>(null)
const mockFetchBooking = vi.fn()
const mockUserableType = ref('Face')
const mockUserId = ref(1)
const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }))

vi.mock('@/plugins/echo', () => ({
  echo: { private: vi.fn(() => ({ listen: vi.fn().mockReturnThis(), stopListening: vi.fn().mockReturnThis() })) },
}))
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
vi.mock('@/composables/useToast', () => ({ useToast: () => mockToast }))
vi.mock('@/features/dateChange/services/dateChangeApi', () => ({
  dateChangeApi: {
    proposeMission: vi.fn(),
    respondMission: vi.fn(),
    proposeBooking: vi.fn(),
    respondBooking: vi.fn(),
    withdrawBooking: vi.fn(),
  },
}))

const FloatingSelectStub = {
  props: ['modelValue', 'options', 'id'],
  emits: ['update:modelValue'],
  template:
    '<select :id="id" data-testid="date-change-reason" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option value="">-</option><option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option></select>',
}

const HOUR = 3600 * 1000

function makeLatest(overrides: Partial<BookingDateChangeLatest> = {}): BookingDateChangeLatest {
  return {
    old_date: '2026-11-15',
    new_date: '2026-11-20',
    reason_label: 'Lieu indisponible',
    status: 'pending',
    respond_by: new Date(Date.now() + 40 * HOUR).toISOString(),
    proposed_by_me: false,
    can_respond: true,
    can_withdraw: false,
    conflict: false,
    ...overrides,
  }
}

function makeDateChange(overrides: Partial<BookingDateChange> = {}): BookingDateChange {
  return { can_propose: true, used: 0, max: 2, latest: null, ...overrides }
}

function makeBooking(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: 'booking-uuid-1',
    realtime_channel_key: 7,
    face_id: 1,
    producer_id: 2,
    status: 'paid',
    status_label: 'Payé',
    date_debut: '2026-11-15T09:00:00.000000Z',
    date_fin: '2026-11-15T17:00:00.000000Z',
    duree_heures: 8,
    type_contenu: 'Photo',
    lieu: 'Akpakpa',
    tarif_base: 100000,
    montant_face_recoit: 85000,
    montant_total_producteur: 110000,
    cancellation_reason: null,
    custom_cancellation_reason: null,
    accepted_at: null,
    can_accept: false,
    can_refuse: false,
    can_pay: false,
    can_rate: false,
    my_rating: null,
    date_change: makeDateChange(),
    face: {
      id: 1,
      userable_type: 'Face',
      userable: { prenom: 'Awa', nom: 'Dossou', username: 'awa', ville: 'Cotonou', thumbnail_url: null, profile_photo_url: null },
    },
    producer: {
      id: 2,
      userable_type: 'Producer',
      userable: { display_name: 'Tropical Bénin', type: 'agency', thumbnail_url: null, profile_photo_url: null },
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
        teleport: true,
        FloatingSelect: FloatingSelectStub,
        PaymentOverlay: true,
        UgcPaymentOverlay: true,
        UgcEngagementModal: true,
        UgcBookingTimeline: true,
        UgcShipmentForm: true,
        UgcShipmentTrackingCard: true,
        UgcFaceTrackingCard: true,
        ConfirmModal: true,
        BookingPricingBreakdown: true,
        BookingChat: true,
        CancellationDialog: true,
        BookingRatingForm: true,
      },
    },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  mockBooking.value = null
  mockFetchBooking.mockReset()
  mockToast.success.mockClear()
  mockToast.error.mockClear()
  for (const fn of Object.values(dateChangeApi)) vi.mocked(fn).mockReset()
})

describe('page booking — bouton « Proposer une autre date »', () => {
  it.each(['producer', 'face'] as const)('%s : visible dans la zone d’actions d’en-tête si can_propose', async (role) => {
    const wrapper = await mountAs(role, makeBooking())
    const button = wrapper.get('[data-testid="booking-head-actions"] [data-testid="booking-propose-date-btn"]')
    expect(button.text()).toBe('Proposer une autre date')
    expect(button.classes()).not.toContain('hidden')
    wrapper.unmount()
  })

  it.each([
    ['can_propose faux', makeDateChange({ can_propose: false })],
    ['date_change absent', undefined],
  ])('absent : %s', async (_label, dateChange) => {
    const wrapper = await mountAs('producer', makeBooking({ date_change: dateChange }))
    expect(wrapper.find('[data-testid="booking-propose-date-btn"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('ouvre la fenêtre, envoie la proposition et met la page à jour', async () => {
    const updated = makeBooking({
      date_change: makeDateChange({
        can_propose: false,
        used: 1,
        latest: makeLatest({ proposed_by_me: true, can_respond: false, can_withdraw: true }),
      }),
    })
    vi.mocked(dateChangeApi.proposeBooking).mockResolvedValue({ data: updated, message: 'Proposition envoyée.' } as never)
    const wrapper = await mountAs('producer', makeBooking())

    await wrapper.get('[data-testid="booking-propose-date-btn"]').trigger('click')
    const dialog = wrapper.get('[data-testid="date-change-dialog"]')
    expect(dialog.get('[data-testid="date-change-current"]').text()).toBe('Dimanche 15 novembre 2026')
    expect(dialog.get('[data-testid="date-change-subtitle"]').text()).toBe('La date change seulement si Awa Dossou accepte.')

    await wrapper.get('input#date-change-new-date').setValue('2099-01-10')
    await wrapper.get('[data-testid="date-change-reason"]').setValue('equipe_indisponible')
    await wrapper.get('[data-testid="date-change-submit"]').trigger('click')
    await flushPromises()

    expect(dateChangeApi.proposeBooking).toHaveBeenCalledWith('booking-uuid-1', {
      new_date: '2099-01-10',
      reason: 'equipe_indisponible',
    })
    expect(mockToast.success).toHaveBeenCalledWith('Proposition envoyée.')
    expect(wrapper.find('[data-testid="date-change-dialog"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="booking-date-change-title"]').text()).toContain('En attente de la réponse d’')
    wrapper.unmount()
  })
})

describe('page booking — bloc de réponse', () => {
  it('le bloc passe avant « Et ensuite ? », en tête de page sur téléphone', async () => {
    const wrapper = await mountAs('face', makeBooking({ date_change: makeDateChange({ latest: makeLatest() }) }))
    const block = wrapper.get('[data-testid="booking-date-change"]')
    const next = wrapper.get('[data-testid="booking-next"]').element
    expect(block.element.compareDocumentPosition(next) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    expect(block.element.closest('aside')).toBeNull()
    wrapper.unmount()
  })

  it('destinataire : « J’accepte » envoie la décision et affiche le résultat', async () => {
    const accepted = makeBooking({
      date_debut: '2026-11-20T09:00:00.000000Z',
      date_change: makeDateChange({ latest: makeLatest({ status: 'accepted', can_respond: false }) }),
    })
    vi.mocked(dateChangeApi.respondBooking).mockResolvedValue({ data: accepted } as never)
    const wrapper = await mountAs('face', makeBooking({ date_change: makeDateChange({ can_propose: false, latest: makeLatest() }) }))

    expect(wrapper.get('[data-testid="booking-date-change-accept"]').text()).toBe('J’accepte le 20 novembre')
    await wrapper.get('[data-testid="booking-date-change-accept"]').trigger('click')
    await flushPromises()

    expect(dateChangeApi.respondBooking).toHaveBeenCalledWith('booking-uuid-1', 'accept')
    expect(wrapper.get('[data-testid="booking-date-change-title"]').text()).toBe('Le tournage passe au vendredi 20 novembre')
    wrapper.unmount()
  })

  it('destinataire : « Je garde » envoie le refus', async () => {
    vi.mocked(dateChangeApi.respondBooking).mockResolvedValue({
      data: makeBooking({ date_change: makeDateChange({ latest: makeLatest({ status: 'refused', can_respond: false }) }) }),
    } as never)
    const wrapper = await mountAs('face', makeBooking({ date_change: makeDateChange({ latest: makeLatest() }) }))

    await wrapper.get('[data-testid="booking-date-change-refuse"]').trigger('click')
    await flushPromises()

    expect(dateChangeApi.respondBooking).toHaveBeenCalledWith('booking-uuid-1', 'refuse')
    wrapper.unmount()
  })

  it('refus du serveur (date prise) : message affiché et booking relu', async () => {
    vi.mocked(dateChangeApi.respondBooking).mockRejectedValue({
      isAxiosError: true,
      response: { status: 422, data: { error: { message: 'Les données fournies ne sont pas valides', details: { date: ['Cette date est déjà prise.'] } } } },
    })
    const wrapper = await mountAs('face', makeBooking({ date_change: makeDateChange({ latest: makeLatest() }) }))

    await wrapper.get('[data-testid="booking-date-change-accept"]').trigger('click')
    await flushPromises()

    expect(mockToast.error).toHaveBeenCalledWith('Cette date est déjà prise.')
    expect(mockFetchBooking).toHaveBeenCalled()
    wrapper.unmount()
  })

  it('auteur : attente puis retrait de la proposition', async () => {
    vi.mocked(dateChangeApi.withdrawBooking).mockResolvedValue({
      data: makeBooking({ date_change: makeDateChange({ latest: makeLatest({ status: 'withdrawn', proposed_by_me: true, can_respond: false }) }) }),
    } as never)
    const wrapper = await mountAs(
      'producer',
      makeBooking({
        date_change: makeDateChange({
          can_propose: false,
          latest: makeLatest({ proposed_by_me: true, can_respond: false, can_withdraw: true }),
        }),
      }),
    )

    expect(wrapper.find('[data-testid="booking-date-change-accept"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="booking-date-change-countdown"]').text()).toMatch(/^Il lui reste/)
    await wrapper.get('[data-testid="booking-date-change-withdraw"]').trigger('click')
    await flushPromises()

    expect(dateChangeApi.withdrawBooking).toHaveBeenCalledWith('booking-uuid-1')
    expect(wrapper.find('[data-testid="booking-date-change"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('Producteur, ancienne date gardée : « Annuler le booking » ouvre l’annulation existante', async () => {
    const wrapper = await mountAs(
      'producer',
      makeBooking({
        status: 'paid',
        date_change: makeDateChange({
          latest: makeLatest({ status: 'refused', proposed_by_me: true, can_respond: false }),
        }),
      }),
    )

    const cancelDialog = () => wrapper.findComponent({ name: 'CancellationDialog' })
    expect(cancelDialog().props('isOpen')).toBe(false)
    await wrapper.get('[data-testid="booking-date-change-cancel"]').trigger('click')
    expect(cancelDialog().props('isOpen')).toBe(true)
    wrapper.unmount()
  })

  it('Producteur : pas d’annulation proposée si le booking ne l’autorise pas', async () => {
    const wrapper = await mountAs(
      'producer',
      makeBooking({
        status: 'confirmed_by_face',
        date_change: makeDateChange({
          latest: makeLatest({ status: 'refused', proposed_by_me: true, can_respond: false }),
        }),
      }),
    )
    expect(wrapper.find('[data-testid="booking-date-change"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="booking-date-change-cancel"]').exists()).toBe(false)
    wrapper.unmount()
  })
})
