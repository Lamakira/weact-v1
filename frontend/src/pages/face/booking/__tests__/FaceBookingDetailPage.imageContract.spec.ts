import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import FaceBookingDetailPage from '../FaceBookingDetailPage.vue'

const mockBooking = ref<Record<string, unknown> | null>(null)
const mockActionError = ref<string | null>(null)
const mockActionErrorCode = ref<string | null>(null)
const mockAccept = vi.fn()
const mockUserableType = ref('Face')
const { requestCodeMock, downloadPdfMock } = vi.hoisted(() => ({
  requestCodeMock: vi.fn(),
  downloadPdfMock: vi.fn(),
}))

vi.mock('@/features/booking/services/bookingApi', () => ({
  bookingApi: { checkPaymentStatus: vi.fn(), checkCommissionStatus: vi.fn() },
}))

vi.mock('@/features/imageContract/services/imageContractApi', () => ({
  imageContractApi: { requestCode: requestCodeMock, getTemplate: vi.fn(), downloadPdf: downloadPdfMock },
}))

vi.mock('@/plugins/echo', () => ({
  echo: {
    private: () => {
      const channel = { listen: vi.fn(() => channel), stopListening: vi.fn(() => channel) }
      return channel
    },
  },
}))

vi.mock('@/features/booking/composables', () => ({
  useBookingDetail: () => ({
    booking: mockBooking,
    isLoading: ref(false),
    error: ref(null),
    fetchBooking: vi.fn(),
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
    error: mockActionError,
    errorCode: mockActionErrorCode,
    errorStatus: ref(null),
    confirm: vi.fn(),
    accept: mockAccept,
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
      id: 1,
      get userable_type() { return mockUserableType.value },
      userable: { prenom: 'Aïcha', nom: 'Bello' },
    },
  }),
}))

const mockToastSuccess = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: mockToastSuccess, error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

function makeContract(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: 'ic-1',
    status: 'awaiting_face',
    finalite: 'Publicité pour la marque Shade',
    supports: [{ value: 'social', label: 'Réseaux sociaux' }],
    territoire: { value: 'benin', label: 'Bénin' },
    duree_annees: 3,
    producer_signed_at: '2026-11-01T10:00:00Z',
    producer_signed_name: 'Jean Producteur',
    face_signed_at: null,
    face_signed_name: null,
    guardian_name: null,
    effective_at: null,
    pdf_ready: false,
    requires_guardian: false,
    ...overrides,
  }
}

function makeBooking(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: 'booking-uuid-1',
    realtime_channel_key: 1,
    face_id: 1,
    producer_id: 2,
    status: 'pending',
    date_debut: new Date(Date.now() + 5 * 86400000).toISOString(),
    date_fin: new Date(Date.now() + 5 * 86400000).toISOString(),
    duree_heures: 8,
    type_contenu: 'Publicité',
    lieu: 'Cotonou',
    montant_face_recoit: 42500,
    montant_total_producteur: 57500,
    tarif_base: 50000,
    cancellation_reason: null,
    custom_cancellation_reason: null,
    accepted_at: null,
    face: { id: 1, userable: { prenom: 'Aïcha', nom: 'Bello' } },
    producer: { id: 2, userable: { display_name: 'Shade Studio' } },
    can_accept: true,
    can_refuse: true,
    can_rate: false,
    my_rating: null,
    ...overrides,
  }
}

async function mountPage(booking: Record<string, unknown>) {
  mockBooking.value = booking

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/face/bookings', name: 'face-bookings', component: { template: '<div/>' } },
      { path: '/face/bookings/:id', name: 'face-booking-detail', component: FaceBookingDetailPage },
      { path: '/pricing', name: 'pricing', component: { template: '<div/>' } },
      { path: '/face/profile', name: 'face-profile', component: { template: '<div/>' } },
    ],
  })
  await router.push({ path: '/face/bookings/booking-uuid-1' })
  await router.isReady()

  const wrapper = mount(FaceBookingDetailPage, {
    global: {
      plugins: [router],
      stubs: {
        Teleport: { template: '<slot />' },
        BookingTimeline: { template: '<div/>' },
        UgcBookingTimeline: { template: '<div/>' },
        BookingStatusBadge: { template: '<div/>' },
        PaymentOverlay: { template: '<div/>' },
        UgcPaymentOverlay: { template: '<div/>' },
        UgcEngagementModal: {
          name: 'UgcEngagementModal',
          props: ['isOpen'],
          emits: ['confirm', 'cancel'],
          template: '<div data-testid="ugc-engagement-modal-stub" :data-open="String(isOpen)"/>',
        },
        BookingPricingBreakdown: { template: '<div/>' },
        BookingChat: { template: '<div/>' },
        CancellationDialog: { template: '<div/>' },
        BookingRatingForm: { template: '<div/>' },
        ConfirmModal: { template: '<div/>' },
      },
    },
  })
  await flushPromises()
  return wrapper
}

async function clickAccept(wrapper: Awaited<ReturnType<typeof mountPage>>): Promise<void> {
  const acceptButton = wrapper.findAll('button').find((b) => b.text() === 'Accepter')
  expect(acceptButton).toBeDefined()
  await acceptButton!.trigger('click')
  await flushPromises()
}

async function signAndConfirm(wrapper: Awaited<ReturnType<typeof mountPage>>, code = '123456'): Promise<void> {
  await wrapper.find('[data-testid="image-contract-accept"]').setValue(true)
  await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
  await flushPromises()
  const paste = new Event('paste', { bubbles: true, cancelable: true }) as Event & { clipboardData: unknown }
  paste.clipboardData = { getData: () => code }
  wrapper.find('[data-testid="code-digit-0"]').element.dispatchEvent(paste)
  await flushPromises()
  await wrapper.find('[data-testid="image-contract-confirm"]').trigger('click')
  await flushPromises()
}

describe('FaceBookingDetailPage : contrat d\'image', () => {
  beforeEach(() => {
    mockUserableType.value = 'Face'
    mockAccept.mockReset()
    mockToastSuccess.mockReset()
    requestCodeMock.mockReset()
    downloadPdfMock.mockReset()
    mockActionError.value = null
    mockActionErrorCode.value = null
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'a•••••@gmail.com', expires_at: 'x', retry_after: 60 } })
  })

  describe('booking sans contrat (acceptation inchangée)', () => {
    it('cash : récapitulatif puis acceptation directe, sans fenêtre de signature ni carte', async () => {
      mockAccept.mockResolvedValueOnce(makeBooking({ status: 'accepted' }))
      const wrapper = await mountPage(makeBooking())

      await clickAccept(wrapper)
      expect(wrapper.find('[data-testid="accept-recap-dialog"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="accept-recap-confirm"]').text()).toBe('Accepter le booking')

      await wrapper.find('[data-testid="accept-recap-confirm"]').trigger('click')
      await flushPromises()

      expect(mockAccept).toHaveBeenCalledTimes(1)
      expect(mockAccept).toHaveBeenCalledWith('booking-uuid-1')
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="image-contract-card"]').exists()).toBe(false)
    })

    it('image_contract null : même comportement', async () => {
      mockAccept.mockResolvedValueOnce(makeBooking({ status: 'accepted' }))
      const wrapper = await mountPage(makeBooking({ image_contract: null }))

      await clickAccept(wrapper)
      await wrapper.find('[data-testid="accept-recap-confirm"]').trigger('click')
      await flushPromises()

      expect(mockAccept).toHaveBeenCalledWith('booking-uuid-1')
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    })

    it('UGC : l\'engagement déclenche l\'acceptation directe', async () => {
      mockAccept.mockResolvedValueOnce(makeBooking({ status: 'accepted', type_contenu: 'UGC' }))
      const wrapper = await mountPage(makeBooking({ status: 'commission_paid', type_contenu: 'UGC', nombre_videos: 2 }))

      wrapper.findComponent({ name: 'UgcEngagementModal' }).vm.$emit('confirm')
      await flushPromises()

      expect(mockAccept).toHaveBeenCalledWith('booking-uuid-1')
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    })
  })

  describe('booking avec contrat non signé par la Face', () => {
    it('cash : le récapitulatif ouvre la fenêtre de signature sans accepter', async () => {
      const wrapper = await mountPage(makeBooking({ image_contract: makeContract() }))

      await clickAccept(wrapper)
      expect(wrapper.find('[data-testid="accept-recap-confirm"]').text()).toBe('Continuer')
      await wrapper.find('[data-testid="accept-recap-confirm"]').trigger('click')
      await flushPromises()

      expect(mockAccept).not.toHaveBeenCalled()
      expect(wrapper.find('[data-testid="accept-recap-dialog"]').exists()).toBe(false)
      const dialog = wrapper.find('[data-testid="image-contract-dialog"]')
      expect(dialog.exists()).toBe(true)
      expect(wrapper.find('[data-testid="summary-finalite"]').text()).toBe('Publicité pour la marque Shade')
      expect(wrapper.find('[data-testid="summary-contrepartie"]').text()).toContain('net')
      expect(wrapper.find('[data-testid="image-contract-protected"]').exists()).toBe(true)
      expect((wrapper.find('[data-testid="image-contract-name"]').element as HTMLInputElement).value).toBe('Aïcha Bello')
    })

    it('cash : accepte avec la signature depuis la fenêtre', async () => {
      mockAccept.mockResolvedValueOnce(makeBooking({ status: 'accepted', image_contract: makeContract({ status: 'signed' }) }))
      const wrapper = await mountPage(makeBooking({ image_contract: makeContract() }))

      await clickAccept(wrapper)
      await wrapper.find('[data-testid="accept-recap-confirm"]').trigger('click')
      await flushPromises()
      await signAndConfirm(wrapper)

      expect(requestCodeMock).toHaveBeenCalledWith({ purpose: 'booking_accept', booking_id: 'booking-uuid-1' })
      expect(mockAccept).toHaveBeenCalledTimes(1)
      expect(mockAccept).toHaveBeenCalledWith('booking-uuid-1', { name: 'Aïcha Bello', code: '123456' })
      expect(mockToastSuccess).toHaveBeenCalledWith('Booking accepté avec succès !')
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    })

    it('UGC : l\'engagement ouvre la fenêtre de signature, puis l\'acceptation part avec la signature', async () => {
      mockAccept.mockResolvedValueOnce(makeBooking({ status: 'accepted', type_contenu: 'UGC' }))
      const wrapper = await mountPage(
        makeBooking({
          status: 'commission_paid',
          type_contenu: 'UGC',
          nombre_videos: 2,
          nom_produit: 'Tenue Shade Fit',
          type_compensation: 'product',
          image_contract: makeContract(),
        }),
      )

      wrapper.findComponent({ name: 'UgcEngagementModal' }).vm.$emit('confirm')
      await flushPromises()

      expect(mockAccept).not.toHaveBeenCalled()
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="summary-contrepartie"]').text()).toBe('Tenue Shade Fit')

      await signAndConfirm(wrapper, '654321')

      expect(mockAccept).toHaveBeenCalledWith('booking-uuid-1', { name: 'Aïcha Bello', code: '654321' })
    })

    it('code refusé : la fenêtre reste ouverte avec le message traduit', async () => {
      mockAccept.mockImplementationOnce(async () => {
        mockActionErrorCode.value = 'SIGNATURE_CODE_EXPIRED'
        mockActionError.value = 'expired'
        return null
      })
      const wrapper = await mountPage(makeBooking({ image_contract: makeContract() }))

      await clickAccept(wrapper)
      await wrapper.find('[data-testid="accept-recap-confirm"]').trigger('click')
      await flushPromises()
      await signAndConfirm(wrapper)

      expect(wrapper.find('[data-testid="image-contract-error"]').text()).toBe('Ce code a expiré. Demandez-en un nouveau.')
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(true)
    })

    it('Face mineure : étape parent avant l\'envoi du code', async () => {
      const wrapper = await mountPage(makeBooking({ image_contract: makeContract({ requires_guardian: true }) }))

      await clickAccept(wrapper)
      await wrapper.find('[data-testid="accept-recap-confirm"]').trigger('click')
      await flushPromises()
      await wrapper.find('[data-testid="image-contract-accept"]').setValue(true)
      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
      await flushPromises()

      expect(requestCodeMock).not.toHaveBeenCalled()
      expect(wrapper.find('[data-testid="image-contract-title"]').text()).toBe('Un parent doit signer pour vous')
    })
  })

  describe('carte « Contrat d\'image »', () => {
    it.each([
      ['awaiting_face', 'Pas encore autorisée'],
      ['signed', 'Pas encore autorisée'],
      ['effective', 'Autorisée depuis le 10 novembre 2026'],
      ['void', 'Annulée'],
    ])('statut %s : « %s »', async (status, label) => {
      const wrapper = await mountPage(
        makeBooking({
          status: 'paid',
          can_accept: false,
          image_contract: makeContract({ status, effective_at: status === 'effective' ? '2026-11-10T08:00:00Z' : null }),
        }),
      )

      expect(wrapper.find('[data-testid="image-contract-card"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="contract-usage"]').text()).toBe(label)
    })

    it('côté Producteur : phrase d\'attente adaptée et carte visible', async () => {
      mockUserableType.value = 'Producer'
      const wrapper = await mountPage(
        makeBooking({ status: 'accepted', can_accept: false, image_contract: makeContract({ status: 'signed' }) }),
      )

      expect(wrapper.find('[data-testid="contract-usage-hint"]').text()).toBe('Elle devient possible une fois la Face payée.')
    })

    it('bouton PDF seulement quand le PDF est prêt', async () => {
      const notReady = await mountPage(
        makeBooking({ status: 'accepted', can_accept: false, image_contract: makeContract({ status: 'signed', pdf_ready: false }) }),
      )
      expect(notReady.find('[data-testid="contract-download"]').exists()).toBe(false)

      const ready = await mountPage(
        makeBooking({ status: 'accepted', can_accept: false, image_contract: makeContract({ status: 'signed', pdf_ready: true }) }),
      )
      expect(ready.find('[data-testid="contract-download"]').text()).toContain('Télécharger le contrat (PDF)')
    })
  })
})
