import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import DeliveryAddressHint from '../components/DeliveryAddressHint.vue'
import MessageBubble from '../components/MessageBubble.vue'
import MessageInput from '../components/MessageInput.vue'
import MessageThread from '../components/MessageThread.vue'
import MessagesWorkspace from '../components/MessagesWorkspace.vue'
import BookingChatMessage from '@/features/booking/components/BookingChatMessage.vue'
import { messagingApi } from '../services/messagingApi'
import { makeContext, makeConversation, makeListItem, makeMessage } from './fixtures'
import type { BookingMessage } from '@/features/booking/types'

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ isEmailVerified: true }),
}))

vi.mock('../services/messagingApi', () => ({
  messagingApi: {
    getConversations: vi.fn(),
    getConversation: vi.fn(),
    sendMessage: vi.fn(),
    getProducerConversations: vi.fn(),
    getProducerConversation: vi.fn(),
    sendProducerMessage: vi.fn(),
    getConversationFaceResponse: vi.fn(),
    getBookingFaceResponse: vi.fn(),
  },
}))

const api = vi.mocked(messagingApi)

const LOCATION = {
  lat: 6.366667,
  lng: 2.433333,
  accuracy_m: 15,
  maps_url: 'https://www.google.com/maps/search/?api=1&query=6.366667,2.433333',
}

afterEach(() => {
  Object.defineProperty(navigator, 'geolocation', { configurable: true, value: undefined })
})

function stubGps() {
  Object.defineProperty(navigator, 'geolocation', {
    configurable: true,
    value: {
      getCurrentPosition: (success: (p: unknown) => void) =>
        success({ coords: { latitude: 6.366667, longitude: 2.433333, accuracy: 14.7 } }),
    },
  })
}

describe('MessageInput : bouton position', () => {
  it('absent par défaut', () => {
    const wrapper = mount(MessageInput, { props: { modelValue: '' } })
    expect(wrapper.find('[data-testid="share-location"]').exists()).toBe(false)
  })

  it('présent avec canShareLocation : libellé accessible, 44 px, actif même texte vide, émet share-location', async () => {
    const wrapper = mount(MessageInput, { props: { modelValue: '', canShareLocation: true } })
    const button = wrapper.find('[data-testid="share-location"]')

    expect(button.attributes('aria-label')).toBe('Envoyer ma position')
    expect(button.classes()).toContain('size-11')
    expect(button.attributes('disabled')).toBeUndefined()

    await button.trigger('click')
    expect(wrapper.emitted('share-location')).toHaveLength(1)
    expect(wrapper.emitted('submit')).toBeUndefined()
  })

  it('désactivé pendant un envoi', () => {
    const wrapper = mount(MessageInput, {
      props: { modelValue: '', canShareLocation: true, isLoading: true },
    })
    expect(wrapper.find('[data-testid="share-location"]').attributes('disabled')).toBeDefined()
  })
})

describe('DeliveryAddressHint : bouton position', () => {
  it('Face : bouton « Envoyer ma position » qui émet share-location', async () => {
    const wrapper = mount(DeliveryAddressHint, { props: { role: 'face', otherPartyName: 'Awa' } })
    const button = wrapper.find('[data-testid="hint-share-location"]')

    expect(button.text()).toBe('Envoyer ma position')
    await button.trigger('click')
    expect(wrapper.emitted('share-location')).toHaveLength(1)
  })

  it('Producteur : aucun bouton', () => {
    const wrapper = mount(DeliveryAddressHint, { props: { role: 'producer', otherPartyName: 'Koffi' } })
    expect(wrapper.find('[data-testid="hint-share-location"]').exists()).toBe(false)
  })

  it('Face avec canShareLocation faux : aucun bouton', () => {
    const wrapper = mount(DeliveryAddressHint, {
      props: { role: 'face', otherPartyName: 'Awa', canShareLocation: false },
    })
    expect(wrapper.find('[data-testid="hint-share-location"]').exists()).toBe(false)
  })
})

describe('MessageBubble : message position', () => {
  it('message propre : carte « Votre position », précision, lien Google Maps sécurisé, pas de texte', () => {
    const wrapper = mount(MessageBubble, {
      props: {
        message: makeMessage({
          is_own_message: true,
          content: 'Position partagée',
          location: LOCATION,
        }),
      },
    })

    expect(wrapper.find('[data-testid="location-card"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="location-title"]').text()).toBe('Votre position')
    expect(wrapper.find('[data-testid="location-accuracy"]').text()).toBe('Précision : 15 m')

    const link = wrapper.find('[data-testid="location-open"]')
    expect(link.text()).toBe('Ouvrir dans Google Maps')
    expect(link.attributes('href')).toBe(LOCATION.maps_url)
    expect(link.attributes('target')).toBe('_blank')
    expect(link.attributes('rel')).toBe('noopener noreferrer')

    // La carte remplace la bulle de texte
    expect(wrapper.text()).not.toContain('Position partagée')
  })

  it('message reçu : « Position de {prénom} », fond blanc', () => {
    const wrapper = mount(MessageBubble, {
      props: { message: makeMessage({ sender_name: 'Afiavi Hounkpatin', location: LOCATION }) },
    })

    expect(wrapper.find('[data-testid="location-title"]').text()).toBe('Position de Afiavi')
    expect(wrapper.find('[data-testid="location-card"]').classes()).toContain('bg-white')
  })

  it('nom manquant : « Position partagée »', () => {
    for (const sender_name of ['', 'Utilisateur']) {
      const wrapper = mount(MessageBubble, {
        props: { message: makeMessage({ sender_name, location: LOCATION }) },
      })
      expect(wrapper.find('[data-testid="location-title"]').text()).toBe('Position partagée')
    }
  })

  it('sans précision : ligne absente ; heure et accusé de lecture conservés', () => {
    const readAt = new Date(2030, 9, 9, 10, 30).toISOString()
    const wrapper = mount(MessageBubble, {
      props: {
        message: makeMessage({
          is_own_message: true,
          read_at: readAt,
          location: { ...LOCATION, accuracy_m: null },
        }),
        showReadReceipt: true,
      },
    })

    expect(wrapper.find('[data-testid="location-accuracy"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="read-receipt"]').text()).toBe('Lu 10:30')
  })

  it('message texte : pas de carte', () => {
    const wrapper = mount(MessageBubble, { props: { message: makeMessage({ location: null }) } })
    expect(wrapper.find('[data-testid="location-card"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Bonjour')
  })
})

describe('BookingChatMessage : message position', () => {
  it('affiche la carte avec le lien et remplace la bulle', () => {
    const message = {
      id: 1,
      booking_id: 1,
      sender_id: 2,
      sender_name: 'Koffi Agbo',
      content: 'Position partagée',
      location: LOCATION,
      is_own_message: false,
      created_at: new Date().toISOString(),
    } as BookingMessage
    const wrapper = mount(BookingChatMessage, { props: { message, isOwn: false } })

    expect(wrapper.find('[data-testid="location-title"]').text()).toBe('Position de Koffi')
    expect(wrapper.find('[data-testid="location-open"]').attributes('href')).toBe(LOCATION.maps_url)
    expect(wrapper.text()).not.toContain('Position partagée')
  })
})

function mountThread(overrides: Record<string, unknown> = {}) {
  return mount(MessageThread, {
    props: {
      participant: makeListItem().other_participant, // « Afiavi Hounkpatin »
      context: makeContext('face', { awaiting_delivery_address: true }),
      role: 'face',
      messages: [makeMessage({ id: 1 })],
      isLoading: false,
      isRefreshing: false,
      error: null,
      refreshError: null,
      isSending: false,
      sendError: null,
      modelValue: '',
      compact: false,
      sendLocation: vi.fn().mockResolvedValue(null),
      ...overrides,
    },
    global: { stubs: { teleport: true } },
  })
}

describe('MessageThread : envoi de position', () => {
  beforeEach(() => stubGps())

  it('bouton de saisie : dialogue au nom de l’autre partie, puis envoi de la position', async () => {
    const sendLocation = vi.fn().mockResolvedValue(null)
    const wrapper = mountThread({ sendLocation })

    expect(wrapper.find('[data-testid="share-location-dialog"]').exists()).toBe(false)
    await wrapper.find('[data-testid="share-location"]').trigger('click')
    expect(wrapper.find('[role="dialog"]').text()).toContain('Afiavi Hounkpatin verra votre position exacte')
    expect(sendLocation).not.toHaveBeenCalled()

    await wrapper.find('[data-testid="share-location-confirm"]').trigger('click')
    await flushPromises()

    expect(sendLocation).toHaveBeenCalledWith({ lat: 6.366667, lng: 2.433333, accuracy_m: 15 })
    expect(wrapper.find('[data-testid="share-location-dialog"]').exists()).toBe(false)
  })

  it('bouton de l’encadré d’adresse : ouvre le même dialogue', async () => {
    const wrapper = mountThread()

    await wrapper.find('[data-testid="hint-share-location"]').trigger('click')
    expect(wrapper.find('[data-testid="share-location-dialog"]').exists()).toBe(true)
  })

  it('échec d’envoi : le dialogue reste ouvert avec l’erreur', async () => {
    const wrapper = mountThread({ sendLocation: vi.fn().mockResolvedValue('Le chat n\'est pas encore débloqué') })

    await wrapper.find('[data-testid="share-location"]').trigger('click')
    await wrapper.find('[data-testid="share-location-confirm"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="share-location-error"]').text()).toBe('Le chat n\'est pas encore débloqué')
  })

  it('« Annuler » ferme sans rien envoyer', async () => {
    const sendLocation = vi.fn()
    const wrapper = mountThread({ sendLocation })

    await wrapper.find('[data-testid="share-location"]').trigger('click')
    await wrapper.find('[data-testid="share-location-cancel"]').trigger('click')

    expect(wrapper.find('[data-testid="share-location-dialog"]').exists()).toBe(false)
    expect(sendLocation).not.toHaveBeenCalled()
  })

  it('sans fonction d’envoi : aucun bouton', () => {
    const wrapper = mountThread({ sendLocation: undefined })

    expect(wrapper.find('[data-testid="share-location"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="hint-share-location"]').exists()).toBe(false)
  })

  it('saisie non rendue (chargement ou erreur) : aucun bouton, même sur l’encadré', () => {
    for (const overrides of [{ isLoading: true }, { error: 'Conversation introuvable' }]) {
      const wrapper = mountThread(overrides)
      expect(wrapper.find('[data-testid="share-location"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="hint-share-location"]').exists()).toBe(false)
    }
  })
})

describe('MessagesWorkspace : envoi de position', () => {
  function makeRouter(): Router {
    const stub = { template: '<div />' }
    return createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/face/messages', name: 'face-messages', component: stub },
        { path: '/face/conversations/:conversationId', name: 'face-conversation', component: stub },
        { path: '/face/missions', name: 'face-missions', component: stub },
        { path: '/face/missions/:id', name: 'face-mission-detail', component: stub },
        { path: '/producer/messages', name: 'producer-messages', component: stub },
        { path: '/producer/conversations/:conversationId', name: 'producer-conversation', component: stub },
        { path: '/producer/missions', name: 'producer-missions', component: stub },
        { path: '/producer/missions/:id', name: 'producer-mission-detail', component: stub },
      ],
    })
  }

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    stubGps()
    Object.defineProperty(window, 'matchMedia', {
      configurable: true,
      writable: true,
      value: (query: string) => ({
        matches: true,
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      }),
    })
  })

  async function openFirstConversation(role: 'face' | 'producer') {
    const item = makeListItem({ id: 'conv-1', unread_count: 0 })
    const list = { data: [item], meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 } }
    api.getConversations.mockResolvedValue(list)
    api.getProducerConversations.mockResolvedValue(list)
    api.getConversation.mockResolvedValue({ data: makeConversation('face') })
    api.getProducerConversation.mockResolvedValue({ data: makeConversation('producer') })

    const router = makeRouter()
    await router.push(`/${role}/messages`)
    await router.isReady()
    const wrapper = mount(MessagesWorkspace, {
      props: { role },
      global: { plugins: [router], stubs: { teleport: true } },
      attachTo: document.body,
    })
    await flushPromises()
    await wrapper.findAll('[data-testid="conversation-item"]')[0]!.trigger('click')
    await flushPromises()
    return wrapper
  }

  async function shareLocation(wrapper: Awaited<ReturnType<typeof openFirstConversation>>) {
    await wrapper.find('[data-testid="share-location"]').trigger('click')
    await wrapper.find('[data-testid="share-location-confirm"]').trigger('click')
    await flushPromises()
  }

  it('Face : le corps envoyé est { location } et la carte apparaît dans le fil', async () => {
    api.sendMessage.mockResolvedValue({
      data: makeMessage({ id: 99, content: 'Position partagée', is_own_message: true, location: LOCATION }),
    })
    const wrapper = await openFirstConversation('face')

    await shareLocation(wrapper)

    expect(api.sendMessage).toHaveBeenCalledWith('conv-1', {
      location: { lat: 6.366667, lng: 2.433333, accuracy_m: 15 },
    })
    expect(wrapper.find('[data-testid="location-open"]').attributes('href')).toBe(LOCATION.maps_url)
    expect(wrapper.find('[data-testid="share-location-dialog"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('Producteur : passe par l’API Producteur', async () => {
    api.sendProducerMessage.mockResolvedValue({
      data: makeMessage({ id: 100, content: 'Position partagée', is_own_message: true, location: LOCATION }),
    })
    const wrapper = await openFirstConversation('producer')

    await shareLocation(wrapper)

    expect(api.sendProducerMessage).toHaveBeenCalledWith('conv-1', {
      location: { lat: 6.366667, lng: 2.433333, accuracy_m: 15 },
    })
    expect(api.sendMessage).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('refus serveur : erreur dans le dialogue, rien ajouté au fil', async () => {
    api.sendMessage.mockRejectedValue({
      isAxiosError: true,
      response: { status: 403, data: {} },
    })
    const wrapper = await openFirstConversation('face')

    await shareLocation(wrapper)

    expect(wrapper.find('[data-testid="share-location-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="location-card"]').exists()).toBe(false)
    wrapper.unmount()
  })
})
