import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent } from 'vue'
import ConversationListPane from '../components/ConversationListPane.vue'
import MessagesWorkspace from '../components/MessagesWorkspace.vue'
import BookingThreadPane from '../components/BookingThreadPane.vue'
import { messagingApi } from '../services/messagingApi'
import { makeListItem } from './fixtures'
import { bookingApi } from '@/features/booking/services/bookingApi'

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ isEmailVerified: true, user: { id: 7 } }),
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

vi.mock('@/features/booking/services/bookingApi', () => ({
  bookingApi: { fetchBooking: vi.fn() },
}))

// Le chat réel (Echo, polling) est couvert par ses propres tests : on vérifie ici le branchement
vi.mock('@/features/booking/components/BookingChat.vue', () => ({
  default: defineComponent({
    props: { booking: { type: Object, required: true }, currentUserId: { type: Number, required: true } },
    template: '<div data-testid="booking-chat-stub">{{ booking.id }}|{{ currentUserId }}</div>',
  }),
}))

const api = vi.mocked(messagingApi)
const bookingsApi = vi.mocked(bookingApi)

function bookingItem(overrides = {}) {
  return makeListItem({
    kind: 'booking',
    id: 'booking-uuid',
    candidature_id: null,
    mission_title: 'Booking · Clip musical · 05/12/2030',
    context: null,
    unread_count: 2,
    ...overrides,
  })
}

function makeRouter() {
  const stub = { template: '<div />' }
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/face/messages', name: 'face-messages', component: stub },
      { path: '/face/conversations/:conversationId', name: 'face-conversation', component: stub },
      { path: '/face/missions', name: 'face-missions', component: stub },
      { path: '/face/bookings/:id', name: 'face-booking-detail', component: stub },
      { path: '/producer/bookings/:id', name: 'producer-booking-detail', component: stub },
    ],
  })
}

function mockDesktop() {
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
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockDesktop()
  Element.prototype.scrollTo = vi.fn() as unknown as typeof Element.prototype.scrollTo
})

describe('Liste — fils de booking', () => {
  it('affiche le repère « Booking » et le titre sans préfixe redondant', () => {
    const wrapper = mount(ConversationListPane, {
      props: {
        conversations: [bookingItem(), makeListItem({ id: 'conv-2' })],
        selectedId: null,
        isLoading: false,
        isRefreshing: false,
        error: null,
        emptyMessage: '',
        emptyActionLabel: '',
      },
    })
    const items = wrapper.findAll('[data-testid="conversation-item"]')
    expect(items).toHaveLength(2)
    expect(items[0]!.find('[data-testid="booking-badge"]').exists()).toBe(true)
    expect(items[0]!.text()).toContain('Clip musical · 05/12/2030')
    expect(items[0]!.text()).not.toContain('Booking · Clip')
    expect(items[1]!.find('[data-testid="booking-badge"]').exists()).toBe(false)
  })

  it('la pilule « Bookings » ne garde que les fils de booking', async () => {
    const wrapper = mount(ConversationListPane, {
      props: {
        conversations: [bookingItem(), makeListItem({ id: 'conv-2' })],
        selectedId: null,
        isLoading: false,
        isRefreshing: false,
        error: null,
        emptyMessage: '',
        emptyActionLabel: '',
      },
    })
    const pill = wrapper.findAll('[data-testid="r-pill"]').find((p) => p.text().includes('Bookings'))
    expect(pill).toBeDefined()
    await pill!.trigger('click')
    expect(wrapper.findAll('[data-testid="conversation-item"]')).toHaveLength(1)
  })
})

describe('Page Messages — ouverture d’un fil de booking', () => {
  async function mountWorkspace(path: string) {
    api.getConversations.mockResolvedValue({
      data: [bookingItem(), makeListItem({ id: 'conv-2' })],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
    })
    bookingsApi.fetchBooking.mockResolvedValue({ data: { id: 'booking-uuid', status: 'paid' } } as never)
    const router = makeRouter()
    await router.push(path)
    await router.isReady()
    const wrapper = mount(MessagesWorkspace, { props: { role: 'face' }, global: { plugins: [router] } })
    await flushPromises()
    return wrapper
  }

  it('ouvre le chat du booking dans le panneau (sans charger une conversation) et marque le fil lu', async () => {
    const wrapper = await mountWorkspace('/face/messages')
    expect(wrapper.find('[data-testid="unread-badge"]').exists()).toBe(true)

    await wrapper.findAll('[data-testid="conversation-item"]')[0]!.trigger('click')
    await flushPromises()

    expect(api.getConversation).not.toHaveBeenCalled()
    expect(bookingsApi.fetchBooking).toHaveBeenCalledWith('booking-uuid')
    expect(wrapper.find('[data-testid="booking-chat-stub"]').text()).toBe('booking-uuid|7')
    expect(wrapper.find('[data-testid="message-thread"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="booking-thread-link"]').attributes('href')).toBe('/face/bookings/booking-uuid')
    expect(wrapper.find('[data-testid="unread-badge"]').exists()).toBe(false)
  })

  it('lien profond ?kind=booking hors de la liste chargée : ouvre directement le chat du booking', async () => {
    api.getConversations.mockResolvedValue({
      data: [makeListItem({ id: 'conv-2' })],
      meta: { current_page: 1, last_page: 2, per_page: 15, total: 20 },
    })
    bookingsApi.fetchBooking.mockResolvedValue({ data: { id: 'far-booking', status: 'paid' } } as never)
    const router = makeRouter()
    await router.push('/face/conversations/far-booking?kind=booking')
    await router.isReady()
    const wrapper = mount(MessagesWorkspace, { props: { role: 'face' }, global: { plugins: [router] } })
    await flushPromises()

    expect(api.getConversation).not.toHaveBeenCalled()
    expect(bookingsApi.fetchBooking).toHaveBeenCalledWith('far-booking')
    expect(wrapper.find('[data-testid="booking-chat-stub"]').text()).toBe('far-booking|7')
  })

  it('lien profond sans kind hors liste : essaie la mission, puis le booking sur 404', async () => {
    api.getConversations.mockResolvedValue({
      data: [makeListItem({ id: 'conv-2' })],
      meta: { current_page: 1, last_page: 2, per_page: 15, total: 20 },
    })
    api.getConversation.mockRejectedValue({ response: { status: 404 } })
    bookingsApi.fetchBooking.mockResolvedValue({ data: { id: 'far-booking', status: 'paid' } } as never)
    const router = makeRouter()
    await router.push('/face/conversations/far-booking')
    await router.isReady()
    const wrapper = mount(MessagesWorkspace, { props: { role: 'face' }, global: { plugins: [router] } })
    await flushPromises()

    expect(api.getConversation).toHaveBeenCalledWith('far-booking')
    expect(bookingsApi.fetchBooking).toHaveBeenCalledWith('far-booking')
    expect(wrapper.find('[data-testid="booking-chat-stub"]').exists()).toBe(true)
  })

  it('deep link vers un fil de booking : ouvre le chat après le chargement de la liste', async () => {
    const wrapper = await mountWorkspace('/face/conversations/booking-uuid')
    expect(api.getConversation).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="booking-chat-stub"]').exists()).toBe(true)
  })
})

describe('BookingThreadPane', () => {
  it('lien « Voir le booking » côté Producteur', async () => {
    bookingsApi.fetchBooking.mockResolvedValue({ data: { id: 'b-1', status: 'paid' } } as never)
    const router = makeRouter()
    await router.push('/face/messages')
    const wrapper = mount(BookingThreadPane, {
      props: { bookingId: 'b-1', title: 'Booking · Clip · 01/01/2031', role: 'producer' },
      global: { plugins: [router] },
    })
    await flushPromises()
    expect(wrapper.find('[data-testid="booking-thread-link"]').attributes('href')).toBe('/producer/bookings/b-1')
  })

  it('affiche une erreur si le booking est inaccessible', async () => {
    bookingsApi.fetchBooking.mockRejectedValue({ response: { status: 403 } })
    const router = makeRouter()
    await router.push('/face/messages')
    const wrapper = mount(BookingThreadPane, {
      props: { bookingId: 'b-1', title: 'Booking', role: 'face' },
      global: { plugins: [router] },
    })
    await flushPromises()
    expect(wrapper.find('[data-testid="booking-thread-error"]').text()).toContain('pas accès')
    expect(wrapper.find('[data-testid="booking-chat-stub"]').exists()).toBe(false)
  })
})
