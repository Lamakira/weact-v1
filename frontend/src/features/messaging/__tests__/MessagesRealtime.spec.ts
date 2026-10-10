import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import MessagesWorkspace from '../components/MessagesWorkspace.vue'
import { messagingApi } from '../services/messagingApi'
import { channels, resetFakeEcho } from './fakeEcho'
import { makeConversation, makeListItem, makeMessage } from './fixtures'

vi.mock('@/plugins/echo', async () => {
  const { fakeEcho } = await import('./fakeEcho')
  return { echo: fakeEcho }
})

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ isEmailVerified: true, user: { id: 1 } }),
}))

vi.mock('../services/messagingApi', () => ({
  messagingApi: {
    getConversations: vi.fn(),
    getConversation: vi.fn(),
    sendMessage: vi.fn(),
    getProducerConversations: vi.fn(),
    getProducerConversation: vi.fn(),
    sendProducerMessage: vi.fn(),
    markConversationRead: vi.fn(),
  },
}))

const api = vi.mocked(messagingApi)

function mockMatchMedia(desktop: boolean) {
  Object.defineProperty(window, 'matchMedia', {
    configurable: true,
    writable: true,
    value: (query: string) => ({
      matches: desktop,
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    }),
  })
}

async function mountOpenThread(role: 'face' | 'producer' = 'face') {
  const stub = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/face/messages', name: 'face-messages', component: stub },
      { path: '/face/conversations/:conversationId', name: 'face-conversation', component: stub },
      { path: '/face/missions/:id', name: 'face-mission-detail', component: stub },
      { path: '/producer/missions/:id/candidatures', name: 'producer-mission-candidatures', component: stub },
      { path: '/producer/messages', name: 'producer-messages', component: stub },
      { path: '/producer/conversations/:conversationId', name: 'producer-conversation', component: stub },
    ],
  })
  await router.push(`/${role}/messages`)
  await router.isReady()
  const wrapper = mount(MessagesWorkspace, { props: { role }, global: { plugins: [router] } })
  await vi.waitFor(() => expect(channels.has('App.Models.User.1')).toBe(true))
  await flushPromises()
  await wrapper.findAll('[data-testid="conversation-item"]')[0]!.trigger('click')
  await vi.waitFor(() => expect(channels.has('conversation.conv-1')).toBe(true))
  await flushPromises()
  return wrapper
}

const incoming = (overrides = {}) => ({
  id: 50,
  conversation_id: 'conv-1',
  content: 'Nouveau !',
  sender_id: 2,
  sender_type: 'App\\Models\\User',
  sender_name: 'Afiavi Hounkpatin',
  read_at: null,
  created_at: '2030-10-09T11:00:00+00:00',
  ...overrides,
})

beforeEach(() => {
  vi.clearAllMocks()
  resetFakeEcho()
  mockMatchMedia(true)
  Element.prototype.scrollTo = vi.fn() as unknown as typeof Element.prototype.scrollTo
  api.getConversations.mockResolvedValue({
    data: [makeListItem({ id: 'conv-1' }), makeListItem({ id: 'conv-2', unread_count: 0 })],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
  })
  api.getConversation.mockResolvedValue({
    data: makeConversation('face', {
      messages: [makeMessage({ id: 1 }), makeMessage({ id: 2, is_own_message: true, sender_id: 1 })],
    }),
  })
  api.markConversationRead.mockResolvedValue({ data: { marked: 1 } })
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('Messagerie temps réel — fil ouvert', () => {
  it('ajoute le message entrant en bas du fil, une seule fois (dédoublonné par id)', async () => {
    const wrapper = await mountOpenThread()
    const channel = channels.get('conversation.conv-1')!

    channel.emit('.message.sent', incoming())
    channel.emit('.message.sent', incoming())
    await flushPromises()

    const bubbles = wrapper.findAll('[data-testid="message-bubble"]')
    expect(bubbles).toHaveLength(3)
    expect(bubbles[2]!.text()).toContain('Nouveau !')
  })

  it('un message de soi-même reçu d\'un autre onglet est marqué comme le sien et sans doublon', async () => {
    const wrapper = await mountOpenThread()
    const channel = channels.get('conversation.conv-1')!

    // id 2 existe déjà (réponse HTTP) : pas de doublon
    channel.emit('.message.sent', incoming({ id: 2, sender_id: 1, content: 'Bonjour' }))
    await flushPromises()

    expect(wrapper.findAll('[data-testid="message-bubble"]')).toHaveLength(2)
    expect(api.markConversationRead).not.toHaveBeenCalled()
  })

  it('marque le message entrant comme lu côté serveur quand le fil est ouvert et l\'onglet visible', async () => {
    await mountOpenThread()
    channels.get('conversation.conv-1')!.emit('.message.sent', incoming())
    await flushPromises()

    expect(api.markConversationRead).toHaveBeenCalledWith('face', 'conv-1')
  })

  it('ne marque pas comme lu quand l\'onglet est masqué, puis le fait au retour sur l\'onglet', async () => {
    await mountOpenThread()
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden')

    channels.get('conversation.conv-1')!.emit('.message.sent', incoming())
    await flushPromises()
    expect(api.markConversationRead).not.toHaveBeenCalled()

    visibility.mockReturnValue('visible')
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()
    expect(api.markConversationRead).toHaveBeenCalledWith('face', 'conv-1')
  })

  it('met à jour « Lu » en direct sur mon dernier message quand l\'autre participant lit', async () => {
    const wrapper = await mountOpenThread()
    expect(wrapper.find('[data-testid="read-receipt"]').exists()).toBe(false)

    channels.get('conversation.conv-1')!.emit('.messages.read', {
      conversation_id: 'conv-1',
      reader_role: 'producer',
      reader_id: 2,
      read_at: '2030-10-09T11:05:00+00:00',
      last_read_message_id: 2,
    })
    await flushPromises()

    expect(wrapper.find('[data-testid="read-receipt"]').exists()).toBe(true)
  })

  it('repli : resynchronise le fil toutes les 15 s si le canal tombe en erreur, puis s\'arrête à la reconnexion', async () => {
    const wrapper = await mountOpenThread()
    vi.useFakeTimers()
    const channel = channels.get('conversation.conv-1')!
    api.getConversation.mockClear()

    channel.fail()
    await vi.advanceTimersByTimeAsync(15_000)
    expect(api.getConversation).toHaveBeenCalledTimes(1)

    channel.connect()
    await vi.advanceTimersByTimeAsync(60_000)
    expect(api.getConversation).toHaveBeenCalledTimes(1)

    vi.useRealTimers()
    wrapper.unmount()
  })
})

describe('Messagerie temps réel — liste', () => {
  it('met à jour aperçu, non-lus et ordre sans recharger', async () => {
    const wrapper = await mountOpenThread()
    api.getConversations.mockClear()

    channels.get('App.Models.User.1')!.emit('.conversation.updated', {
      conversation_id: 'conv-2',
      latest_message: {
        id: 60,
        content: 'Un autre message',
        sender_id: 3,
        sender_name: 'Kossi',
        created_at: '2030-10-09T12:00:00+00:00',
      },
      unread_count: 4,
      updated_at: '2030-10-09T12:00:00+00:00',
    })
    await flushPromises()

    const items = wrapper.findAll('[data-testid="conversation-item"]')
    expect(items[0]!.text()).toContain('Un autre message')
    expect(items[0]!.find('[data-testid="unread-badge"]').text()).toContain('4')
    expect(api.getConversations).not.toHaveBeenCalled()
  })

  it('le fil ouvert et visible garde un compteur à zéro', async () => {
    const wrapper = await mountOpenThread()

    channels.get('App.Models.User.1')!.emit('.conversation.updated', {
      conversation_id: 'conv-1',
      latest_message: {
        id: 61,
        content: 'Message dans le fil ouvert',
        sender_id: 2,
        sender_name: 'Afiavi',
        created_at: '2030-10-09T12:01:00+00:00',
      },
      unread_count: 1,
      updated_at: '2030-10-09T12:01:00+00:00',
    })
    await flushPromises()

    expect(wrapper.find('[data-testid="unread-badge"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-testid="conversation-item"]')[0]!.text()).toContain(
      'Message dans le fil ouvert',
    )
  })

  it('conversation inconnue : recharge la liste', async () => {
    await mountOpenThread()
    api.getConversations.mockClear()

    channels.get('App.Models.User.1')!.emit('.conversation.updated', {
      conversation_id: 'conv-new',
      latest_message: {
        id: 70,
        content: 'Première !',
        sender_id: 9,
        sender_name: 'Nouveau',
        created_at: '2030-10-09T12:05:00+00:00',
      },
      unread_count: 1,
      updated_at: '2030-10-09T12:05:00+00:00',
    })
    await flushPromises()

    expect(api.getConversations).toHaveBeenCalledTimes(1)
  })

  it('Producteur : mêmes comportements via les endpoints Producteur', async () => {
    api.getProducerConversations.mockResolvedValue({
      data: [makeListItem({ id: 'conv-1' })],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })
    api.getProducerConversation.mockResolvedValue({
      data: makeConversation('producer', { messages: [makeMessage({ id: 1 })] }),
    })
    const wrapper = await mountOpenThread('producer')

    channels.get('conversation.conv-1')!.emit('.message.sent', incoming())
    await flushPromises()

    expect(wrapper.findAll('[data-testid="message-bubble"]')).toHaveLength(2)
    expect(api.markConversationRead).toHaveBeenCalledWith('producer', 'conv-1')
  })
})
