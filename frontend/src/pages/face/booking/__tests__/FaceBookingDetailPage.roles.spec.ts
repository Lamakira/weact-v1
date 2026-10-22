import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import FaceBookingDetailPage from '../FaceBookingDetailPage.vue'

/**
 * Lot C6 — page booking adaptée au rôle : titre, carte de l'autre partie, « Et ensuite ? »,
 * action principale unique et temps réel, par rôle et par statut.
 */

const mockBooking = ref<Record<string, unknown> | null>(null)
const mockFetchBooking = vi.fn()
const mockUserableType = ref('Face')
const mockUserId = ref(1)

const echoMock = vi.hoisted(() => {
  const handlers: Record<string, () => void> = {}
  const channel = {
    listen: vi.fn((event: string, callback: () => void) => {
      handlers[event] = callback
      return channel
    }),
    stopListening: vi.fn(() => channel),
  }
  return { handlers, channel, privateMock: vi.fn(() => channel) }
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

const text = (wrapper: Awaited<ReturnType<typeof mountAs>>, testid: string) => wrapper.find(`[data-testid="${testid}"]`).text()
const primaryButtons = (wrapper: Awaited<ReturnType<typeof mountAs>>) =>
  wrapper.findAll('[data-testid="booking-primary-action"] button')

beforeEach(() => {
  mockBooking.value = null
  mockFetchBooking.mockReset()
  echoMock.privateMock.mockClear()
  echoMock.channel.listen.mockClear()
  echoMock.channel.stopListening.mockClear()
  for (const key of Object.keys(echoMock.handlers)) delete echoMock.handlers[key]
})

describe('page booking — titre et pastille selon le rôle', () => {
  it('Producer, demande en attente : « Demande envoyée à {prénom} »', async () => {
    const wrapper = await mountAs('producer', makeBooking({ status: 'pending' }))

    expect(text(wrapper, 'booking-title')).toBe('Demande envoyée à Adjoua')
    expect(text(wrapper, 'booking-pill')).toBe('En attente de réponse')
    expect(wrapper.text()).not.toContain('Demande de booking')
    wrapper.unmount()
  })

  it('Face, demande en attente : « Nouvelle demande de {producteur} »', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'pending', can_accept: true, can_refuse: true }))

    expect(text(wrapper, 'booking-title')).toBe('Nouvelle demande de Tropical Bénin')
    expect(text(wrapper, 'booking-pill')).toBe('Réponse attendue')
    wrapper.unmount()
  })

  it('Producer, booking accepté : « {prénom} a accepté : payez avant {heure} »', async () => {
    const wrapper = await mountAs(
      'producer',
      makeBooking({
        status: 'accepted',
        can_pay: true,
        accepted_at: new Date().toISOString(),
        payment_deadline: '2026-10-20T14:00:00+01:00',
      }),
    )

    expect(text(wrapper, 'booking-title')).toBe('Adjoua a accepté : payez avant 14:00')
    wrapper.unmount()
  })

  it('Face, booking accepté : « Booking accepté : en attente du paiement »', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'accepted', accepted_at: new Date().toISOString() }))

    expect(text(wrapper, 'booking-title')).toBe('Booking accepté : en attente du paiement')
    wrapper.unmount()
  })

  it.each([
    ['producer', 'paid', 'Booking confirmé avec Adjoua', 'Paiement sécurisé'],
    ['face', 'paid', 'Booking confirmé avec Tropical Bénin', 'Paiement sécurisé'],
    ['producer', 'completed', 'Booking terminé avec Adjoua', 'Terminé'],
    ['face', 'completed', 'Booking terminé avec Tropical Bénin', 'Terminé'],
    ['face', 'expired', 'Demande expirée sans réponse', 'Expirée'],
    ['producer', 'refused', 'Adjoua a refusé la demande', 'Refusée'],
    ['face', 'cancelled_by_producer', 'Booking annulé par Tropical Bénin', 'Annulée'],
    ['producer', 'no_show', 'Absence de Adjoua signalée', 'Absence signalée'],
  ] as const)('%s, statut %s : « %s »', async (role, status, expectedTitle, expectedPill) => {
    const wrapper = await mountAs(role, makeBooking({ status, date_debut: new Date(Date.now() - 24 * HOUR).toISOString() }))

    expect(text(wrapper, 'booking-title')).toBe(expectedTitle)
    expect(text(wrapper, 'booking-pill')).toBe(expectedPill)
    wrapper.unmount()
  })
})

describe('page booking — carte de l’autre partie', () => {
  it('le Producer voit la Face : nom complet, palier, ville, note et nombre d’avis', async () => {
    const wrapper = await mountAs('producer', makeBooking())

    expect(text(wrapper, 'booking-other-party-name')).toContain('Adjoua Kouassi')
    const meta = text(wrapper, 'booking-other-party-meta')
    expect(meta).toContain('Face Élite')
    expect(meta).toContain('Cotonou')
    expect(meta).toContain('4,8')
    expect(meta).toContain('(23 avis)')
    wrapper.unmount()
  })

  it('la Face voit le Producteur : nom, type, note', async () => {
    const wrapper = await mountAs('face', makeBooking())

    expect(text(wrapper, 'booking-other-party-name')).toContain('Tropical Bénin')
    const meta = text(wrapper, 'booking-other-party-meta')
    expect(meta).toContain('Producteur')
    expect(meta).toContain('Agence')
    expect(meta).toContain('4,6')
    expect(meta).toContain('(9 avis)')
    wrapper.unmount()
  })

  it('sans avis : « Pas encore noté »', async () => {
    const booking = makeBooking()
    ;(booking.face as { userable: Record<string, unknown> }).userable.average_rating = null
    ;(booking.face as { userable: Record<string, unknown> }).userable.ratings_count = 0
    const wrapper = await mountAs('producer', booking)

    expect(text(wrapper, 'booking-other-party-meta')).toContain('Pas encore noté')
    wrapper.unmount()
  })
})

describe('page booking — « Et ensuite ? »', () => {
  it('Face, demande en attente : à elle d’agir, avec un décompte', async () => {
    const wrapper = await mountAs(
      'face',
      makeBooking({
        status: 'pending',
        can_accept: true,
        face_response_deadline: new Date(Date.now() + 26 * HOUR).toISOString(),
      }),
    )

    expect(text(wrapper, 'booking-next-headline')).toMatch(/^Répondez avant le /)
    expect(text(wrapper, 'booking-next-who')).toBe("À vous d'agir")
    expect(text(wrapper, 'booking-next-countdown')).toMatch(/^Reste 1 j/)
    wrapper.unmount()
  })

  it('Producer, booking accepté : « Payez pour confirmer le booking », décompte jusqu’à l’échéance', async () => {
    const wrapper = await mountAs(
      'producer',
      makeBooking({
        status: 'accepted',
        can_pay: true,
        accepted_at: new Date().toISOString(),
        payment_deadline: new Date(Date.now() + 3 * HOUR + 40 * 60000).toISOString(),
      }),
    )

    expect(text(wrapper, 'booking-next-headline')).toBe('Payez pour confirmer le booking')
    expect(text(wrapper, 'booking-next-countdown')).toMatch(/^Reste 3 h/)
    wrapper.unmount()
  })

  it('booking terminé : aucun décompte', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'completed' }))

    expect(wrapper.find('[data-testid="booking-next-countdown"]').exists()).toBe(false)
    wrapper.unmount()
  })
})

describe('page booking — une seule action principale par état', () => {
  it('Face, demande en attente : Accepter (Refuser reste secondaire)', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'pending', can_accept: true, can_refuse: true }))

    const buttons = primaryButtons(wrapper)
    expect(buttons).toHaveLength(1)
    expect(buttons[0].text()).toBe('Accepter')
    expect(wrapper.findAll('button').some((b) => b.text().startsWith('Refuser'))).toBe(true)
    expect(text(wrapper, 'booking-action-bar')).toContain('Accepter')
    wrapper.unmount()
  })

  it('Producer, booking accepté : Payer {montant}', async () => {
    const wrapper = await mountAs(
      'producer',
      makeBooking({ status: 'accepted', can_pay: true, accepted_at: new Date().toISOString() }),
    )

    const buttons = primaryButtons(wrapper)
    expect(buttons).toHaveLength(1)
    expect(buttons[0].text()).toMatch(/^Payer\s+110\s000\sF\sCFA$/)
    wrapper.unmount()
  })

  it('Producer, demande en attente (cash) : seule action possible = Annuler la demande, en retrait', async () => {
    const wrapper = await mountAs('producer', makeBooking({ status: 'pending' }))

    const buttons = primaryButtons(wrapper)
    expect(buttons).toHaveLength(1)
    expect(buttons[0].text()).toBe('Annuler la demande')
    expect(buttons[0].classes()).toContain('border-gray-300')
    wrapper.unmount()
  })

  it('Producer, tournage passé : Confirmer la réalisation, Signaler une absence reste secondaire', async () => {
    const wrapper = await mountAs(
      'producer',
      makeBooking({ status: 'paid', date_debut: new Date(Date.now() - 3 * 24 * HOUR).toISOString() }),
    )

    const buttons = primaryButtons(wrapper)
    expect(buttons).toHaveLength(1)
    expect(buttons[0].text()).toBe('Confirmer la réalisation')
    expect(wrapper.find('[data-testid="report-no-show-btn"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('Producer, booking payé avant le tournage : Ouvrir le chat (confirmer reste désactivé)', async () => {
    const wrapper = await mountAs('producer', makeBooking({ status: 'paid' }))

    const buttons = primaryButtons(wrapper)
    expect(buttons).toHaveLength(1)
    expect(buttons[0].text()).toBe('Ouvrir le chat')
    // Masqué en CSS (container query) quand le chat est dans la colonne de droite : classe dédiée sur le conteneur.
    expect(wrapper.find('[data-testid="booking-primary-action"]').classes()).toContain('booking-primary-action--chat')
    wrapper.unmount()
  })

  it('l\'en-tête ne porte la classe de masquage que pour l\'action « chat »', async () => {
    const wrapper = await mountAs('producer', makeBooking({ status: 'completed', can_rate: true }))

    expect(wrapper.find('[data-testid="booking-primary-action"]').classes()).not.toContain('booking-primary-action--chat')
    wrapper.unmount()
  })

  it('booking terminé à noter : Noter {nom}', async () => {
    const wrapper = await mountAs('producer', makeBooking({ status: 'completed', can_rate: true }))

    const buttons = primaryButtons(wrapper)
    expect(buttons).toHaveLength(1)
    expect(buttons[0].text()).toBe('Noter Adjoua Kouassi')
    wrapper.unmount()
  })

  it('booking clos : aucune action principale ni barre d’action', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'expired' }))

    expect(primaryButtons(wrapper)).toHaveLength(0)
    expect(wrapper.find('[data-testid="booking-action-bar"]').exists()).toBe(false)
    wrapper.unmount()
  })
})

describe('page booking — messagerie fermée avant paiement', () => {
  it('affiche la note de messagerie verrouillée tant que le booking cash n’est pas payé', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'accepted', accepted_at: new Date().toISOString() }))

    expect(text(wrapper, 'booking-chat-locked')).toContain("La messagerie s'ouvre après le paiement")
    expect(wrapper.find('[data-testid="booking-chat-section"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('la note disparaît une fois la messagerie ouverte', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'paid' }))

    expect(wrapper.find('[data-testid="booking-chat-locked"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="booking-chat-section"]').exists()).toBe(true)
    wrapper.unmount()
  })
})

describe('page booking — temps réel', () => {
  it('s’abonne au canal du booking dès un statut non terminal (pas seulement « accepté »)', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'pending' }))
    await vi.waitFor(() => expect(echoMock.channel.listen).toHaveBeenCalled())

    expect(echoMock.privateMock).toHaveBeenCalledWith('booking.7')
    expect(Object.keys(echoMock.handlers).sort()).toEqual([
      '.booking.cancelled',
      '.booking.expired',
      '.booking.no_show_reported',
      '.booking.status_changed',
    ])
    wrapper.unmount()
  })

  it('recharge le booking à chaque événement de changement d’état', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'pending' }))
    await vi.waitFor(() => expect(echoMock.channel.listen).toHaveBeenCalled())
    mockFetchBooking.mockClear()

    for (const event of ['.booking.status_changed', '.booking.expired', '.booking.cancelled', '.booking.no_show_reported']) {
      echoMock.handlers[event]()
    }
    await flushPromises()

    expect(mockFetchBooking).toHaveBeenCalledTimes(4)
    expect(mockFetchBooking).toHaveBeenCalledWith('booking-uuid-1')
    wrapper.unmount()
  })

  it('ne s’abonne pas à un booking déjà terminal', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'completed' }))
    await flushPromises()

    expect(echoMock.channel.listen).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('se désabonne au démontage', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'accepted' }))
    await vi.waitFor(() => expect(echoMock.channel.listen).toHaveBeenCalled())

    wrapper.unmount()
    await vi.waitFor(() => expect(echoMock.channel.stopListening).toHaveBeenCalledTimes(4))
  })

  it('se désabonne quand le booking devient terminal, puis ne réagit plus', async () => {
    const wrapper = await mountAs('face', makeBooking({ status: 'pending' }))
    await vi.waitFor(() => expect(echoMock.channel.listen).toHaveBeenCalled())

    mockBooking.value = makeBooking({ status: 'refused' })
    await flushPromises()

    await vi.waitFor(() => expect(echoMock.channel.stopListening).toHaveBeenCalledTimes(4))
    wrapper.unmount()
  })

  it('un changement de statut reçu met à jour titre et pastille', async () => {
    const wrapper = await mountAs('producer', makeBooking({ status: 'pending' }))
    expect(text(wrapper, 'booking-title')).toBe('Demande envoyée à Adjoua')

    mockBooking.value = makeBooking({ status: 'accepted', can_pay: true, accepted_at: new Date().toISOString() })
    await flushPromises()

    expect(text(wrapper, 'booking-title')).toContain('Adjoua a accepté')
    expect(text(wrapper, 'booking-pill')).toBe('À payer')
    wrapper.unmount()
  })
})
