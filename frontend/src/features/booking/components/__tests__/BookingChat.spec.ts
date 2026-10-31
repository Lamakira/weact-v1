import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import BookingChat from '../BookingChat.vue'
import { messagingApi } from '@/features/messaging/services/messagingApi'
import type { Booking } from '@/features/booking/types'

const messagesRef = ref<unknown[]>([])

vi.mock('../../composables/useBookingChat', () => ({
  useBookingChat: () => ({
    messages: messagesRef,
    isLoading: ref(false),
    isSending: ref(false),
    reverbError: ref(false),
    sendMessage: vi.fn(),
    refreshMessages: vi.fn(),
  }),
}))

vi.mock('@/features/messaging/services/messagingApi', () => ({
  messagingApi: { getBookingFaceResponse: vi.fn(), getConversationFaceResponse: vi.fn() },
}))

const api = vi.mocked(messagingApi)

const booking = {
  id: 'booking-uuid-1',
  realtime_channel_key: 1,
  face_id: 2,
  producer_id: 3,
  status: 'paid',
} as unknown as Booking

const mounted: Array<{ unmount: () => void }> = []

function mountChat(currentUserId: number) {
  const wrapper = mount(BookingChat, {
    props: { booking, currentUserId },
    global: { stubs: { ChatHeader: true, BookingChatMessage: true } },
  })
  mounted.push(wrapper)
  return wrapper
}

// Les messages sont un ref partagé : on démonte pour qu'un test n'en réveille pas un autre.
afterEach(() => {
  mounted.splice(0).forEach((w) => w.unmount())
})

beforeEach(() => {
  vi.clearAllMocks()
  messagesRef.value = []
  api.getBookingFaceResponse.mockResolvedValue({
    face_first_name: 'Aminata',
    unread_email_after_minutes: 60,
    urgent: false,
    typical_response_minutes: 165,
    overdue: false,
  })
})

describe('BookingChat — délai de réponse de la Face', () => {
  it('Producteur : ligne affichée, chargée avec l’uuid du booking', async () => {
    const wrapper = mountChat(3)
    await flushPromises()

    expect(api.getBookingFaceResponse).toHaveBeenCalledWith('booking-uuid-1')
    expect(wrapper.find('[data-testid="face-response-notice"]').exists()).toBe(true)
  })

  it('Face : aucune ligne et aucun appel', async () => {
    const wrapper = mountChat(2)
    await flushPromises()

    expect(api.getBookingFaceResponse).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="face-response-notice"]').exists()).toBe(false)
  })

  it('Producteur : relit l’état quand le nombre de messages change', async () => {
    mountChat(3)
    await flushPromises()

    messagesRef.value = [{ id: 1, content: 'Bonjour', is_own_message: true }]
    await flushPromises()

    expect(api.getBookingFaceResponse).toHaveBeenCalledTimes(2)
  })
})

describe('BookingChat — consigne « adresse de livraison »', () => {
  function mountWithHint(extra: Record<string, unknown>) {
    const wrapper = mount(BookingChat, {
      props: { booking, currentUserId: 2, ...extra },
      global: { stubs: { ChatHeader: true, BookingChatMessage: true } },
    })
    mounted.push(wrapper)
    return wrapper
  }

  it('Face : encadré « Envoyez votre adresse de livraison à {nom} »', () => {
    const wrapper = mountWithHint({ showAddressHint: true, role: 'face', otherPartyName: 'Koffi Agbo' })
    const hint = wrapper.find('[data-testid="delivery-address-hint"]')

    expect(hint.exists()).toBe(true)
    expect(hint.text()).toContain('Première étape')
    expect(hint.text()).toContain('Envoyez votre adresse de livraison à Koffi Agbo')
  })

  it('Producteur : encadré « … va vous envoyer son adresse de livraison ici »', () => {
    const wrapper = mountWithHint({ showAddressHint: true, role: 'producer', otherPartyName: 'Aminata Diallo' })
    const hint = wrapper.find('[data-testid="delivery-address-hint"]')

    expect(hint.text()).toContain('Aminata Diallo va vous envoyer son adresse de livraison ici.')
  })

  it('absent quand la prop d\'affichage est fausse ou omise', () => {
    expect(
      mountWithHint({ showAddressHint: false, role: 'face', otherPartyName: 'Koffi Agbo' })
        .find('[data-testid="delivery-address-hint"]').exists(),
    ).toBe(false)
    expect(mountWithHint({}).find('[data-testid="delivery-address-hint"]').exists()).toBe(false)
  })
})
