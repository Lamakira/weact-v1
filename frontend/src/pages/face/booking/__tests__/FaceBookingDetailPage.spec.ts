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
const mockActionErrorCode = ref<string | null>(null)
const mockIsReportingNoShow = ref(false)
const mockAccept = vi.fn()
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
    errorCode: mockActionErrorCode,
    confirm: vi.fn(),
    accept: mockAccept,
    refuse: vi.fn(),
    cancel: vi.fn(),
    reportNoShow: mockReportNoShow,
    clearError: vi.fn(),
  }),
}))

const mockConfirmShipment = vi.fn()
const mockConfirmReceipt = vi.fn()
const mockShipmentError = ref<string | null>(null)
const mockShipmentErrorCode = ref<string | null>(null)

vi.mock('@/composables/useUgcShipment', () => ({
  useUgcShipment: () => ({
    isSubmitting: ref(false),
    error: mockShipmentError,
    errorCode: mockShipmentErrorCode,
    confirmShipment: mockConfirmShipment,
    confirmReceipt: mockConfirmReceipt,
    clearError: vi.fn(),
  }),
}))

// Upload livrable (4.2) — factory COMPLÈTE (piège n°6) : tous les membres
// destructurés par la page doivent exister, sinon TOUS ses tests crashent.
const mockUploadDeliverable = vi.fn()
const mockDeliverableError = ref<string | null>(null)
const mockDeliverableErrorCode = ref<string | null>(null)

vi.mock('@/composables/useUgcDeliverable', () => ({
  useUgcDeliverable: () => ({
    isUploading: ref(false),
    uploadProgress: ref(null),
    error: mockDeliverableError,
    errorCode: mockDeliverableErrorCode,
    uploadDeliverable: mockUploadDeliverable,
    validateFile: vi.fn(() => ({ valid: true })),
    clearError: vi.fn(),
  }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { get id() { return mockUserId.value }, get userable_type() { return mockUserableType.value } },
  }),
}))

const mockToastSuccess = vi.fn()
const mockToastError = vi.fn()
const mockToastWarning = vi.fn()
const mockToastInfo = vi.fn()

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: mockToastSuccess,
    error: mockToastError,
    warning: mockToastWarning,
    info: mockToastInfo,
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
    lieu: null,
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

function makeUgcBooking(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return makeBooking({
    status: 'accepted',
    type_contenu: 'UGC',
    type_compensation: 'product',
    type_compensation_label: 'Produit seul',
    nom_produit: 'Sneakers Shade Fit',
    valeur_produit: 20000,
    nombre_videos: 2,
    montant_remuneration: null,
    commission_ugc: 2500,
    date_debut: null,
    date_fin: null,
    duree_heures: null,
    ...overrides,
  })
}

function makeShipment(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: 'shipment-uuid-1',
    transporteur: 'Gozem',
    numero_suivi: 'GZM-COT-882194',
    note_envoi: null,
    tunnel_status: 'shipped',
    tunnel_status_label: 'Produit expédié',
    shipped_at: '2026-06-12T10:00:00+00:00',
    recu_le: null,
    unboxing_deadline_at: null,
    destinataire: { nom: 'Aïcha Bello', ville: 'Cotonou', pays: 'Bénin' },
    created_at: '2026-06-12T10:00:00+00:00',
    ...overrides,
  }
}

async function mountPage(
  booking: Record<string, unknown> | null = null,
  query: Record<string, string> = {},
) {
  mockBooking.value = booking
  mockIsLoading.value = false
  mockError.value = null

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/face/bookings', name: 'face-bookings', component: { template: '<div/>' } },
      { path: '/face/bookings/:id', name: 'face-booking-detail', component: FaceBookingDetailPage },
      { path: '/pricing', name: 'pricing', component: { template: '<div/>' } },
    ],
  })

  await router.push({ path: '/face/bookings/booking-uuid-1', query })
  await router.isReady()

  const wrapper = mount(FaceBookingDetailPage, {
    global: {
      plugins: [router],
      stubs: {
        BookingTimeline: { template: '<div data-testid="cash-timeline-stub"/>' },
        UgcBookingTimeline: {
          props: ['current', 'variant'],
          template: '<div data-testid="ugc-timeline-stub" :data-current="current"/>',
        },
        UgcShipmentForm: {
          emits: ['submit'],
          template:
            '<button data-testid="shipment-form-stub" @click="$emit(\'submit\', { transporteur: \'Gozem\', numero_suivi: \'GZM-1\' })"/>',
        },
        UgcShipmentTrackingCard: {
          props: ['shipment'],
          template: '<div data-testid="tracking-card-stub"/>',
        },
        UgcFaceTrackingCard: {
          props: ['shipment', 'current', 'isSubmitting', 'isUploading', 'uploadProgress', 'deliverables'],
          emits: ['confirm-receipt', 'upload'],
          // `new File` vit dans methods (scope JS) — indisponible dans le scope template Vue.
          methods: {
            emitUpload() {
              this.$emit('upload', new File(['x'], 'unboxing.mp4', { type: 'video/mp4' }))
            },
          },
          template: `<div>
            <button data-testid="face-tracking-card-stub" :data-current="current" :data-deliverables-count="deliverables.length" @click="$emit('confirm-receipt')"></button>
            <button data-testid="face-tracking-upload-stub" @click="emitUpload"></button>
          </div>`,
        },
        ConfirmModal: {
          props: ['isOpen', 'confirmDisabled'],
          emits: ['confirm', 'cancel'],
          // Rend le slot (le sélecteur de photos de réception y vit) + reflète confirmDisabled.
          template: `<div v-if="isOpen">
            <slot />
            <button data-testid="receipt-modal-confirm" :disabled="confirmDisabled" @click="$emit('confirm')"></button>
          </div>`,
        },
        ProductPhotosUpload: {
          props: ['modelValue', 'maxPhotos', 'title', 'hint'],
          emits: ['update:modelValue'],
          methods: {
            selectPhoto() {
              this.$emit('update:modelValue', [new File(['x'], 'reception.jpg', { type: 'image/jpeg' })])
            },
          },
          template: '<button data-testid="select-reception-photo" @click="selectPhoto"></button>',
        },
        BookingStatusBadge: { template: '<div/>' },
        PaymentOverlay: { template: '<div/>' },
        UgcPaymentOverlay: {
          props: ['modelValue', 'amount'],
          template: '<div data-testid="ugc-overlay-stub" :data-open="String(modelValue)" :data-amount="String(amount)"/>',
        },
        UgcEngagementModal: {
          name: 'UgcEngagementModal',
          props: ['isOpen', 'nombreVideos', 'nomProduit', 'isSubmitting'],
          emits: ['confirm', 'cancel'],
          template: '<div data-testid="ugc-engagement-modal-stub" :data-open="String(isOpen)"/>',
        },
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

  it('displays the shooting location when present', async () => {
    const wrapper = await mountPage(makeBooking({ lieu: 'Cotonou' }))

    expect(wrapper.text()).toContain('Lieu de tournage')
    expect(wrapper.text()).toContain('Cotonou')
  })

  it('hides the shooting location block when absent', async () => {
    const wrapper = await mountPage(makeBooking({ lieu: null }))

    expect(wrapper.text()).not.toContain('Lieu de tournage')
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

describe('FaceBookingDetailPage — UGC commission CTA (story 1.6)', () => {
  beforeEach(() => {
    mockBooking.value = null
    mockIsLoading.value = false
    mockError.value = null
    mockActionError.value = null
    mockActionErrorCode.value = null
    mockUserableType.value = 'Face'
    mockUserId.value = 1
    mockFetchBooking.mockReset()
    mockRefreshBooking.mockReset()
    mockAccept.mockReset()
  })

  it('shows the "Payer la commission" CTA for a Producer viewing a pending UGC booking', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const wrapper = await mountPage(
      makeBooking({ status: 'pending', type_contenu: 'UGC', commission_ugc: 2500, producer_id: 2 }),
    )

    const cta = wrapper.find('[data-testid="ugc-pay-commission-cta"]')
    expect(cta.exists()).toBe(true)
    expect(cta.text()).toContain('Payer la commission')
  })

  it('hides the UGC commission CTA from the Face', async () => {
    mockUserableType.value = 'Face'
    mockUserId.value = 1
    const wrapper = await mountPage(
      makeBooking({ status: 'pending', type_contenu: 'UGC', commission_ugc: 2500 }),
    )

    expect(wrapper.find('[data-testid="ugc-pay-commission-cta"]').exists()).toBe(false)
  })

  it('hides the UGC commission CTA on a non-UGC (cash) booking', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const wrapper = await mountPage(
      makeBooking({ status: 'pending', type_contenu: 'Shooting photo', producer_id: 2 }),
    )

    expect(wrapper.find('[data-testid="ugc-pay-commission-cta"]').exists()).toBe(false)
  })

  it('opens the UgcPaymentOverlay when the Producer clicks the commission CTA', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const wrapper = await mountPage(
      makeBooking({ status: 'pending', type_contenu: 'UGC', commission_ugc: 2500, producer_id: 2 }),
    )

    const overlay = wrapper.find('[data-testid="ugc-overlay-stub"]')
    expect(overlay.exists()).toBe(true)
    expect(overlay.attributes('data-open')).toBe('false')

    await wrapper.find('[data-testid="ugc-pay-commission-cta"] button').trigger('click')

    const opened = wrapper.find('[data-testid="ugc-overlay-stub"]')
    expect(opened.attributes('data-open')).toBe('true')
    // RH.2 : l'overlay reçoit le règlement complet (montant_total_producteur), pas la commission.
    expect(opened.attributes('data-amount')).toBe('57500')
  })

  it('auto-opens the UgcPaymentOverlay on arrival with ?pay=1 for an eligible Producer', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const wrapper = await mountPage(
      makeBooking({ status: 'pending', type_contenu: 'UGC', commission_ugc: 2500, producer_id: 2 }),
      { pay: '1' },
    )
    await flushPromises()

    const overlay = wrapper.find('[data-testid="ugc-overlay-stub"]')
    expect(overlay.exists()).toBe(true)
    expect(overlay.attributes('data-open')).toBe('true')
  })

  it('opens the engagement modal on Accepter for a UGC booking without calling accept (story 2.4)', async () => {
    const wrapper = await mountPage(
      makeBooking({
        status: 'commission_paid',
        type_contenu: 'UGC',
        can_accept: true,
        can_refuse: true,
        nombre_videos: 2,
        nom_produit: 'Tenue Shade Fit',
        commission_ugc: 2500,
        accepted_at: null,
      }),
    )

    const modal = wrapper.find('[data-testid="ugc-engagement-modal-stub"]')
    expect(modal.attributes('data-open')).toBe('false')

    const acceptButton = wrapper.findAll('button').find((b) => b.text() === 'Accepter')
    expect(acceptButton).toBeDefined()
    await acceptButton!.trigger('click')

    expect(wrapper.find('[data-testid="ugc-engagement-modal-stub"]').attributes('data-open')).toBe('true')
    expect(mockAccept).not.toHaveBeenCalled()
  })

  it('calls accept when the engagement modal is confirmed (story 2.4)', async () => {
    mockAccept.mockResolvedValueOnce(
      makeBooking({ status: 'accepted', type_contenu: 'UGC', can_accept: false }),
    )
    const wrapper = await mountPage(
      makeBooking({
        status: 'commission_paid',
        type_contenu: 'UGC',
        can_accept: true,
        nombre_videos: 2,
        nom_produit: 'Tenue Shade Fit',
        accepted_at: null,
      }),
    )

    const stub = wrapper.findComponent({ name: 'UgcEngagementModal' })
    stub.vm.$emit('confirm')
    await flushPromises()

    expect(mockAccept).toHaveBeenCalledWith('booking-uuid-1')
  })

  it('accepts a cash booking directly without opening the engagement modal (story 2.4 witness)', async () => {
    mockAccept.mockResolvedValueOnce(makeBooking({ status: 'accepted' }))
    const wrapper = await mountPage(
      makeBooking({ status: 'pending', can_accept: true, accepted_at: null }),
    )

    const acceptButton = wrapper.findAll('button').find((b) => b.text() === 'Accepter')
    expect(acceptButton).toBeDefined()
    await acceptButton!.trigger('click')
    await flushPromises()

    expect(mockAccept).toHaveBeenCalledWith('booking-uuid-1')
    expect(wrapper.find('[data-testid="ugc-engagement-modal-stub"]').attributes('data-open')).toBe('false')
  })

  it('hides the 24h payment deadline for an accepted UGC booking and keeps it for cash (story 2.4)', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2

    const ugcWrapper = await mountPage(
      makeBooking({
        status: 'accepted',
        type_contenu: 'UGC',
        producer_id: 2,
        accepted_at: new Date().toISOString(),
      }),
    )
    expect(ugcWrapper.text()).not.toContain('Paiement requis avant')

    const cashWrapper = await mountPage(
      makeBooking({
        status: 'accepted',
        producer_id: 2,
        accepted_at: new Date().toISOString(),
      }),
    )
    expect(cashWrapper.text()).toContain('Paiement requis avant')
  })

  it('routes to pricing on a UGC_SUBSCRIPTION_REQUIRED accept failure (story 2.4)', async () => {
    mockAccept.mockImplementationOnce(async () => {
      mockActionError.value = "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus)."
      mockActionErrorCode.value = 'UGC_SUBSCRIPTION_REQUIRED'
      return null
    })
    const wrapper = await mountPage(
      makeBooking({
        status: 'commission_paid',
        type_contenu: 'UGC',
        can_accept: true,
        accepted_at: null,
      }),
    )

    const stub = wrapper.findComponent({ name: 'UgcEngagementModal' })
    stub.vm.$emit('confirm')
    await flushPromises()

    expect(wrapper.vm.$router.currentRoute.value.name).toBe('pricing')
  })

  it('shows the UGC dotation summary and hides the shoot fields + cash finances for a UGC booking', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const wrapper = await mountPage(
      makeBooking({
        status: 'commission_paid',
        type_contenu: 'UGC',
        commission_ugc: 2500,
        nom_produit: 'Tenue Shade Fit',
        valeur_produit: 45000,
        type_compensation_label: 'Produit seul',
        date_debut: null,
        date_fin: null,
        duree_heures: null,
        lieu: null,
        producer_id: 2,
      }),
    )

    const text = wrapper.text()
    expect(text).toContain('Dotation UGC')
    expect(text).toContain('Tenue Shade Fit')
    expect(text).not.toContain('Date de début')
    expect(text).not.toContain('Invalid Date')
    expect(text).not.toContain('Finances') // cash finances card hidden for UGC
  })
})

describe('FaceBookingDetailPage — UGC shipment & timeline (story 3.2)', () => {
  beforeEach(() => {
    mockBooking.value = null
    mockIsLoading.value = false
    mockError.value = null
    mockActionError.value = null
    mockActionErrorCode.value = null
    mockShipmentError.value = null
    mockShipmentErrorCode.value = null
    mockUserableType.value = 'Face'
    mockUserId.value = 1
    mockFetchBooking.mockReset()
    mockRefreshBooking.mockReset()
    mockConfirmShipment.mockReset()
  })

  it('renders the UGC timeline instead of the cash timeline for UGC bookings', async () => {
    const wrapper = await mountPage(makeUgcBooking())

    expect(wrapper.find('[data-testid="ugc-booking-timeline-card"]').exists()).toBe(true)
    // accepted sans shipment → étape 3 (fix du « recul » defer 2.4)
    expect(wrapper.find('[data-testid="ugc-timeline-stub"]').attributes('data-current')).toBe('3')
    expect(wrapper.find('[data-testid="cash-timeline-stub"]').exists()).toBe(false)
  })

  it('keeps the cash timeline for non-UGC bookings', async () => {
    const wrapper = await mountPage(makeBooking())

    expect(wrapper.find('[data-testid="cash-timeline-stub"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="ugc-booking-timeline-card"]').exists()).toBe(false)
  })

  it('shows the shipment panel to the producer on an accepted UGC booking', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const wrapper = await mountPage(makeUgcBooking({ producer_id: 2 }))

    const panel = wrapper.find('[data-testid="ugc-shipment-panel"]')
    expect(panel.exists()).toBe(true)
    expect(panel.text()).toContain('Étape 3 sur 6 · Expédition')
    expect(panel.text()).toContain("Confirmer l'envoi du produit")
    expect(panel.text()).toContain('Sneakers Shade Fit')
  })

  it('hides the shipment panel from the Face', async () => {
    mockUserableType.value = 'Face'
    mockUserId.value = 1
    const wrapper = await mountPage(makeUgcBooking())

    expect(wrapper.find('[data-testid="ugc-shipment-panel"]').exists()).toBe(false)
  })

  it('shows the tracking card instead of the panel once shipped', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const wrapper = await mountPage(makeUgcBooking({ producer_id: 2, shipment: makeShipment() }))

    expect(wrapper.find('[data-testid="tracking-card-stub"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="ugc-shipment-panel"]').exists()).toBe(false)
    // Le shipment fait avancer la timeline à l'étape 4.
    expect(wrapper.find('[data-testid="ugc-timeline-stub"]').attributes('data-current')).toBe('4')
  })

  it('shows the chat section on an accepted UGC booking', async () => {
    const wrapper = await mountPage(makeUgcBooking())

    expect(wrapper.find('[data-testid="booking-chat-section"]').exists()).toBe(true)
  })

  it('does not show the chat section on an accepted cash booking', async () => {
    const wrapper = await mountPage(makeBooking({ status: 'accepted' }))

    expect(wrapper.find('[data-testid="booking-chat-section"]').exists()).toBe(false)
  })

  it('applies the shipment locally on success without refetching (D-3.1.c)', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    mockConfirmShipment.mockResolvedValue(makeShipment())
    const wrapper = await mountPage(makeUgcBooking({ producer_id: 2 }))

    expect(wrapper.find('[data-testid="ugc-shipment-panel"]').exists()).toBe(true)

    await wrapper.find('[data-testid="shipment-form-stub"]').trigger('click')
    await flushPromises()

    // Le statut booking ne change pas au confirm : assignation locale, AUCUN refetch.
    expect(mockFetchBooking).toHaveBeenCalledTimes(1) // mount uniquement
    expect(wrapper.find('[data-testid="ugc-shipment-panel"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="tracking-card-stub"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="ugc-timeline-stub"]').attributes('data-current')).toBe('4')
  })

  it('refetches the booking when confirmation fails with ALREADY_SHIPPED', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    mockConfirmShipment.mockImplementation(async () => {
      mockShipmentError.value = "L'expédition de ce deal a déjà été confirmée."
      mockShipmentErrorCode.value = 'ALREADY_SHIPPED'
      return null
    })
    const wrapper = await mountPage(makeUgcBooking({ producer_id: 2 }))

    expect(mockFetchBooking).toHaveBeenCalledTimes(1) // mount

    await wrapper.find('[data-testid="shipment-form-stub"]').trigger('click')
    await flushPromises()

    expect(mockConfirmShipment).toHaveBeenCalledWith('booking', 'booking-uuid-1', {
      transporteur: 'Gozem',
      numero_suivi: 'GZM-1',
    })
    expect(mockFetchBooking).toHaveBeenCalledTimes(2) // refetch D-3.2.e
    expect(mockFetchBooking).toHaveBeenLastCalledWith('booking-uuid-1')
  })
})

describe('FaceBookingDetailPage — carte de suivi Face & réception (story 3.4)', () => {
  beforeEach(() => {
    mockBooking.value = null
    mockIsLoading.value = false
    mockError.value = null
    mockShipmentError.value = null
    mockShipmentErrorCode.value = null
    mockUserableType.value = 'Face'
    mockUserId.value = 1
    mockFetchBooking.mockReset()
    mockRefreshBooking.mockReset()
    mockConfirmShipment.mockReset()
    mockConfirmReceipt.mockReset()
    mockUploadDeliverable.mockReset()
    mockDeliverableError.value = null
    mockDeliverableErrorCode.value = null
    mockToastSuccess.mockReset()
    mockToastError.mockReset()
    mockToastWarning.mockReset()
    mockToastInfo.mockReset()
  })

  it('shows the Face tracking card instead of the horizontal timeline once shipped', async () => {
    const wrapper = await mountPage(makeUgcBooking({ shipment: makeShipment() }))

    const card = wrapper.find('[data-testid="face-tracking-card-stub"]')
    expect(card.exists()).toBe(true)
    // La timeline verticale vit DANS la carte : la H disparaît (D-3.4.e).
    expect(wrapper.find('[data-testid="ugc-booking-timeline-card"]').exists()).toBe(false)
    expect(card.attributes('data-current')).toBe('4')
  })

  it('keeps the horizontal timeline for the Face before shipment', async () => {
    const wrapper = await mountPage(makeUgcBooking())

    expect(wrapper.find('[data-testid="ugc-booking-timeline-card"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="face-tracking-card-stub"]').exists()).toBe(false)
  })

  it('does not show the Face tracking card to the producer', async () => {
    mockUserableType.value = 'Producer'
    mockUserId.value = 2
    const wrapper = await mountPage(makeUgcBooking({ producer_id: 2, shipment: makeShipment() }))

    // Le Producteur garde timeline H + tracking card 3.2 (D-3.4.e).
    expect(wrapper.find('[data-testid="face-tracking-card-stub"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="ugc-booking-timeline-card"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="tracking-card-stub"]').exists()).toBe(true)
  })

  it('confirms the receipt through the modal and applies the shipment locally without refetching (D-3.3.i)', async () => {
    mockConfirmReceipt.mockResolvedValue(
      makeShipment({
        tunnel_status: 'received',
        tunnel_status_label: 'Produit reçu',
        recu_le: '2026-06-12T12:00:00+00:00',
        unboxing_deadline_at: '2026-06-19T12:00:00+00:00',
      }),
    )
    const wrapper = await mountPage(makeUgcBooking({ shipment: makeShipment() }))

    // Le CTA de la carte ouvre la modal — pas de POST direct (D-3.4.c).
    await wrapper.find('[data-testid="face-tracking-card-stub"]').trigger('click')
    expect(mockConfirmReceipt).not.toHaveBeenCalled()

    // Photos de réception obligatoires (spec réception) : confirmation bloquée tant
    // qu'aucune photo n'est jointe, puis les photos partent avec la confirmation.
    expect(wrapper.find('[data-testid="receipt-modal-confirm"]').attributes('disabled')).toBeDefined()
    await wrapper.find('[data-testid="select-reception-photo"]').trigger('click')
    expect(wrapper.find('[data-testid="receipt-modal-confirm"]').attributes('disabled')).toBeUndefined()

    await wrapper.find('[data-testid="receipt-modal-confirm"]').trigger('click')
    await flushPromises()

    expect(mockConfirmReceipt).toHaveBeenCalledWith('shipment-uuid-1', [expect.any(File)])
    expect(mockFetchBooking).toHaveBeenCalledTimes(1) // mount uniquement — pas de refetch
    expect(mockToastSuccess).toHaveBeenCalledWith('Réception confirmée — le chrono Unboxing démarre')
    expect((mockBooking.value?.shipment as Record<string, unknown>).tunnel_status).toBe('received')
  })

  it('refetches the booking when receipt fails with ALREADY_RECEIVED', async () => {
    mockConfirmReceipt.mockImplementation(async () => {
      mockShipmentError.value = 'La réception de ce produit a déjà été confirmée.'
      mockShipmentErrorCode.value = 'ALREADY_RECEIVED'
      return null
    })
    const wrapper = await mountPage(makeUgcBooking({ shipment: makeShipment() }))

    await wrapper.find('[data-testid="face-tracking-card-stub"]').trigger('click')
    await wrapper.find('[data-testid="select-reception-photo"]').trigger('click')
    await wrapper.find('[data-testid="receipt-modal-confirm"]').trigger('click')
    await flushPromises()

    expect(mockToastInfo).toHaveBeenCalledWith('La réception de ce produit a déjà été confirmée.')
    expect(mockFetchBooking).toHaveBeenCalledTimes(2) // mount + refetch multi-onglets (D-3.2.e)
    expect(mockFetchBooking).toHaveBeenLastCalledWith('booking-uuid-1')
  })

  it('shows an error toast when receipt fails with another error', async () => {
    mockConfirmReceipt.mockImplementation(async () => {
      mockShipmentError.value = 'La commission de ce deal est en cours de remboursement — action impossible.'
      mockShipmentErrorCode.value = 'INVALID_STATUS'
      return null
    })
    const wrapper = await mountPage(makeUgcBooking({ shipment: makeShipment() }))

    await wrapper.find('[data-testid="face-tracking-card-stub"]').trigger('click')
    await wrapper.find('[data-testid="select-reception-photo"]').trigger('click')
    await wrapper.find('[data-testid="receipt-modal-confirm"]').trigger('click')
    await flushPromises()

    expect(mockToastError).toHaveBeenCalledWith(
      'La commission de ce deal est en cours de remboursement — action impossible.',
    )
    expect(mockFetchBooking).toHaveBeenCalledTimes(1) // pas de refetch hors ALREADY_RECEIVED
  })

  it('uploads the unboxing video and refetches the booking on success', async () => {
    mockUploadDeliverable.mockResolvedValue({ id: 'deliverable-uuid-1', kind: 'unboxing' })
    const wrapper = await mountPage(makeUgcBooking({ shipment: makeShipment() }))

    await wrapper.find('[data-testid="face-tracking-upload-stub"]').trigger('click')
    await flushPromises()

    expect(mockUploadDeliverable).toHaveBeenCalledWith('shipment-uuid-1', expect.any(File))
    expect(mockToastSuccess).toHaveBeenCalledWith('Vidéo Unboxing déposée — en attente de validation')
    // D-4.2.d : le 201 porte la DeliverableResource, PAS le shipment → refetch.
    expect(mockFetchBooking).toHaveBeenCalledTimes(2) // mount + refetch
    expect(mockFetchBooking).toHaveBeenLastCalledWith('booking-uuid-1')
  })

  it('refetches the booking when the upload reports ALREADY_UPLOADED', async () => {
    mockUploadDeliverable.mockImplementation(async () => {
      mockDeliverableError.value = 'Votre vidéo Unboxing a déjà été déposée pour ce deal.'
      mockDeliverableErrorCode.value = 'ALREADY_UPLOADED'
      return null
    })
    const wrapper = await mountPage(makeUgcBooking({ shipment: makeShipment() }))

    await wrapper.find('[data-testid="face-tracking-upload-stub"]').trigger('click')
    await flushPromises()

    expect(mockToastInfo).toHaveBeenCalledWith('Votre vidéo Unboxing a déjà été déposée pour ce deal.')
    expect(mockFetchBooking).toHaveBeenCalledTimes(2) // mount + resync multi-onglets
    expect(mockFetchBooking).toHaveBeenLastCalledWith('booking-uuid-1')
  })

  it('refetches the booking when the upload reports INVALID_STATUS', async () => {
    mockUploadDeliverable.mockImplementation(async () => {
      mockDeliverableError.value = "La vidéo ne peut pas être déposée dans l'état actuel de ce deal."
      mockDeliverableErrorCode.value = 'INVALID_STATUS'
      return null
    })
    const wrapper = await mountPage(makeUgcBooking({ shipment: makeShipment() }))

    await wrapper.find('[data-testid="face-tracking-upload-stub"]').trigger('click')
    await flushPromises()

    expect(mockToastError).toHaveBeenCalledWith(
      "La vidéo ne peut pas être déposée dans l'état actuel de ce deal.",
    )
    expect(mockFetchBooking).toHaveBeenCalledTimes(2) // mount + resync (fenêtre fermée)
  })

  it('shows an error toast without refetching when the upload fails', async () => {
    mockUploadDeliverable.mockImplementation(async () => {
      mockDeliverableError.value = 'Format non supporté.'
      mockDeliverableErrorCode.value = null // validation client/errors.video → branche « autre »
      return null
    })
    const wrapper = await mountPage(makeUgcBooking({ shipment: makeShipment() }))

    await wrapper.find('[data-testid="face-tracking-upload-stub"]').trigger('click')
    await flushPromises()

    expect(mockToastError).toHaveBeenCalledWith('Format non supporté.')
    expect(mockFetchBooking).toHaveBeenCalledTimes(1) // pas de refetch : l'état du deal n'a pas changé
  })

  it('passes the booking deliverables to the Face tracking card (4.6)', async () => {
    const wrapper = await mountPage(
      makeUgcBooking({
        shipment: makeShipment({ tunnel_status: 'avis_pending', avis_deadline_at: '2026-06-26T12:00:00+00:00' }),
        deliverables: [
          { id: 'd1', kind: 'unboxing', validation_status: 'validated' },
          { id: 'd2', kind: 'avis', validation_status: 'rejected' },
        ],
      }),
    )

    const card = wrapper.find('[data-testid="face-tracking-card-stub"]')
    expect(card.exists()).toBe(true)
    expect(card.attributes('data-deliverables-count')).toBe('2')
  })

  it('uploads in the Avis phase and refetches the booking (handler réutilisé, kind dérivé serveur)', async () => {
    mockUploadDeliverable.mockResolvedValue({ id: 'deliverable-uuid-2', kind: 'avis' })
    const wrapper = await mountPage(
      makeUgcBooking({
        shipment: makeShipment({ tunnel_status: 'avis_pending', avis_deadline_at: '2026-06-26T12:00:00+00:00' }),
        deliverables: [{ id: 'd1', kind: 'unboxing', validation_status: 'validated' }],
      }),
    )

    await wrapper.find('[data-testid="face-tracking-upload-stub"]').trigger('click')
    await flushPromises()

    expect(mockUploadDeliverable).toHaveBeenCalledWith('shipment-uuid-1', expect.any(File))
    // D-4.2.d : le 201 porte la DeliverableResource → refetch (mount + refetch).
    expect(mockFetchBooking).toHaveBeenCalledTimes(2)
    expect(mockFetchBooking).toHaveBeenLastCalledWith('booking-uuid-1')
  })
})
