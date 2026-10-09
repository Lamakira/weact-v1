import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import MessagesWorkspace from '../components/MessagesWorkspace.vue'
import { messagingApi } from '../services/messagingApi'
import { makeConversation, makeListItem, makeMessage } from './fixtures'

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
      {
        path: '/producer/missions/:id/candidatures',
        name: 'producer-mission-candidatures',
        component: stub,
      },
    ],
  })
}

async function mountWorkspace(role: 'face' | 'producer', path: string) {
  const router = makeRouter()
  await router.push(path)
  await router.isReady()
  const wrapper = mount(MessagesWorkspace, {
    props: { role },
    global: { plugins: [router] },
  })
  await flushPromises()
  return { wrapper, router }
}

function setupFace() {
  const item = makeListItem({ id: 'conv-1', unread_count: 2 })
  api.getConversations.mockResolvedValue({
    data: [item, makeListItem({ id: 'conv-2' })],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
  })
  api.getConversation.mockResolvedValue({ data: makeConversation('face') })
}

beforeEach(() => {
  vi.clearAllMocks()
  Element.prototype.scrollTo = vi.fn() as unknown as typeof Element.prototype.scrollTo
})

describe('MessagesWorkspace — desktop', () => {
  beforeEach(() => mockMatchMedia(true))

  it('affiche liste, placeholder de fil, et pas de panneau sans sélection', async () => {
    setupFace()
    const { wrapper } = await mountWorkspace('face', '/face/messages')
    expect(wrapper.find('[data-testid="conversation-list"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="thread-placeholder"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="conversation-context-panel"]').exists()).toBe(false)
  })

  it('sélectionner une conversation charge le fil et le panneau Face (montants nets), sans changer de route', async () => {
    setupFace()
    const { wrapper, router } = await mountWorkspace('face', '/face/messages')
    await wrapper.findAll('[data-testid="conversation-item"]')[0]!.trigger('click')
    await flushPromises()

    expect(api.getConversation).toHaveBeenCalledWith('conv-1')
    expect(router.currentRoute.value.name).toBe('face-messages')
    expect(wrapper.find('[data-testid="message-thread"]').exists()).toBe(true)
    const panel = wrapper.find('[data-testid="conversation-context-panel"]')
    expect(panel.text()).toContain('Vous recevez')
    expect(panel.text()).not.toContain('Frais de service')
    expect(panel.find('[data-testid="context-open-link"]').attributes('href')).toBe(
      '/face/missions/mission-uuid',
    )
  })

  it('remet à zéro le badge non lu de la liste après ouverture', async () => {
    setupFace()
    const { wrapper } = await mountWorkspace('face', '/face/messages')
    expect(wrapper.find('[data-testid="unread-badge"]').exists()).toBe(true)
    await wrapper.findAll('[data-testid="conversation-item"]')[0]!.trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-testid="unread-badge"]').exists()).toBe(false)
  })

  it('Producteur : panneau avec cachet, frais de service et total', async () => {
    api.getProducerConversations.mockResolvedValue({
      data: [makeListItem({ id: 'conv-1' })],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })
    api.getProducerConversation.mockResolvedValue({ data: makeConversation('producer') })
    const { wrapper } = await mountWorkspace('producer', '/producer/messages')
    await wrapper.find('[data-testid="conversation-item"]').trigger('click')
    await flushPromises()

    const panel = wrapper.find('[data-testid="conversation-context-panel"]')
    expect(panel.text()).toContain('Cachet')
    expect(panel.text()).toContain('Frais de service')
    expect(panel.text()).toContain('Total payé')
    expect(panel.text()).not.toContain('Vous recevez')
  })

  it('sans entité liée : le panneau se limite à l’autre participant', async () => {
    setupFace()
    api.getConversation.mockResolvedValue({ data: makeConversation('face', { context: null }) })
    const { wrapper } = await mountWorkspace('face', '/face/messages')
    await wrapper.findAll('[data-testid="conversation-item"]')[0]!.trigger('click')
    await flushPromises()
    const panel = wrapper.find('[data-testid="conversation-context-panel"]')
    expect(panel.find('[data-testid="context-participant-only"]').text()).toContain('Afiavi Hounkpatin')
    expect(panel.find('[data-testid="context-step"]').exists()).toBe(false)
  })

  it('envoie un message : Entrée, ajout au fil et vidage du champ', async () => {
    setupFace()
    api.sendMessage.mockResolvedValue({
      data: makeMessage({ id: 99, content: 'Merci !', is_own_message: true }),
    })
    const { wrapper } = await mountWorkspace('face', '/face/messages')
    await wrapper.findAll('[data-testid="conversation-item"]')[0]!.trigger('click')
    await flushPromises()

    const textarea = wrapper.find('textarea')
    await textarea.setValue('Merci !')
    await textarea.trigger('keydown', { key: 'Enter' })
    await flushPromises()

    expect(api.sendMessage).toHaveBeenCalledWith('conv-1', { content: 'Merci !' })
    expect(wrapper.text()).toContain('Merci !')
    expect((wrapper.find('textarea').element as HTMLTextAreaElement).value).toBe('')
  })

  it('le bouton Actualiser recharge le fil ouvert', async () => {
    setupFace()
    const { wrapper } = await mountWorkspace('face', '/face/messages')
    await wrapper.findAll('[data-testid="conversation-item"]')[0]!.trigger('click')
    await flushPromises()

    api.getConversation.mockResolvedValue({
      data: makeConversation('face', {
        messages: [makeMessage(), makeMessage({ id: 2, content: 'Nouveau message reçu' })],
      }),
    })
    await wrapper.find('[data-testid="thread-refresh"]').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Nouveau message reçu')
  })

  it('« Lu » sur le dernier message envoyé lu uniquement, séparateur de jour présent', async () => {
    setupFace()
    api.getConversation.mockResolvedValue({
      data: makeConversation('face', {
        messages: [
          makeMessage({ id: 1, is_own_message: true, read_at: '2030-10-09T09:00:00+00:00' }),
          makeMessage({ id: 2, is_own_message: true, read_at: '2030-10-09T10:30:00+00:00' }),
          makeMessage({ id: 3, is_own_message: false }),
        ],
      }),
    })
    const { wrapper } = await mountWorkspace('face', '/face/messages')
    await wrapper.findAll('[data-testid="conversation-item"]')[0]!.trigger('click')
    await flushPromises()

    expect(wrapper.findAll('[data-testid="read-receipt"]')).toHaveLength(1)
    expect(wrapper.findAll('[data-testid="day-separator"]').length).toBeGreaterThanOrEqual(1)
  })

  it('deep link /conversations/:id : ouvre directement le fil', async () => {
    setupFace()
    const { wrapper } = await mountWorkspace('face', '/face/conversations/conv-1')
    expect(api.getConversation).toHaveBeenCalledWith('conv-1')
    expect(wrapper.find('[data-testid="message-thread"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="conversation-list"]').exists()).toBe(true)
  })
})

describe('MessagesWorkspace — mobile', () => {
  beforeEach(() => mockMatchMedia(false))

  it('liste plein écran : ni fil ni panneau de contexte', async () => {
    setupFace()
    const { wrapper } = await mountWorkspace('face', '/face/messages')
    expect(wrapper.find('[data-testid="conversation-list"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="message-thread"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="thread-placeholder"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="conversation-context-panel"]').exists()).toBe(false)
  })

  it('sélectionner pousse la route du fil (pas de chargement inline)', async () => {
    setupFace()
    const { wrapper, router } = await mountWorkspace('face', '/face/messages')
    await wrapper.findAll('[data-testid="conversation-item"]')[0]!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('face-conversation')
    expect(router.currentRoute.value.params.conversationId).toBe('conv-1')
    expect(api.getConversation).not.toHaveBeenCalled()
  })

  it('fil plein écran avec bouton retour et barre de contexte repliable', async () => {
    setupFace()
    const { wrapper, router } = await mountWorkspace('face', '/face/conversations/conv-1')
    expect(wrapper.find('[data-testid="conversation-list"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="message-thread"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="conversation-context-panel"]').exists()).toBe(false)

    const bar = wrapper.find('[data-testid="conversation-context-bar"]')
    expect(bar.text()).toContain('Payé')
    expect(bar.text()).toContain('Lookbook Wax')
    expect(wrapper.find('[data-testid="conversation-context-bar-details"]').exists()).toBe(false)
    await bar.find('button').trigger('click')
    expect(wrapper.find('[data-testid="conversation-context-bar-details"]').text()).toContain(
      'Vous recevez',
    )

    await wrapper.find('[data-testid="thread-back"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('face-messages')
  })
})
