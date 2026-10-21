import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { reactive, ref } from 'vue'
import FaceLayout from '../FaceLayout.vue'
import apiClient from '@/services/apiClient'
import { useMessagesUnreadStore } from '@/stores/messagesUnread'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'
import type { SidebarItem } from '@/components/layout'

const routeHolder = vi.hoisted(() => ({ route: null as unknown as { path: string; meta: Record<string, unknown> } }))
vi.mock('vue-router', async () => {
  const { reactive } = await import('vue')
  routeHolder.route = reactive({ path: '/face/dashboard', meta: {} })
  return { useRoute: () => routeHolder.route }
})
vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn() },
  getAuthToken: vi.fn(() => null),
}))
vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({ logout: vi.fn(), isLoading: ref(false) }),
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => reactive({ user: { id: 1, email: 'face@test.com' }, isEmailVerified: true }),
}))
vi.mock('@/features/face/composables/useProfilePhoto', () => ({
  useProfilePhoto: () => ({ profile: ref(null), fetchProfile: vi.fn().mockResolvedValue(undefined) }),
}))
vi.mock('@/features/face/composables/usePersonalInfo', () => ({
  usePersonalInfo: () => ({ personalInfo: ref(null), fetchPersonalInfo: vi.fn().mockResolvedValue(undefined) }),
}))
vi.mock('@/components/layout', async () => {
  const { defineComponent } = await import('vue')
  return {
    DashboardLayout: defineComponent({
      name: 'DashboardLayout',
      props: ['title', 'sidebarItems', 'mobileTabs'],
      template: '<div><slot /></div>',
    }),
    KeepAliveRouterView: { template: '<div />' },
  }
})

const stub = { template: '<div />' }
const stubs = {
  EmailVerificationBanner: stub,
  TarifsMissingBanner: stub,
  WhatsappMissingBanner: stub,
  PendingSubscriptionPaymentBanner: stub,
  UgcSuspensionBanner: stub,
}

function messagesItems(wrapper: ReturnType<typeof mount>) {
  const layout = wrapper.findComponent({ name: 'DashboardLayout' })
  const find = (items: SidebarItem[]) => items.find((i) => i.to === '/face/messages')
  return {
    sidebar: find(layout.props('sidebarItems') as SidebarItem[]),
    tab: find(layout.props('mobileTabs') as SidebarItem[]),
  }
}

describe('FaceLayout - badge non lus sur Messages', () => {
  let wrapper: ReturnType<typeof mount>

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    resetAllSharedCachedResources()
    routeHolder.route.path = '/face/dashboard'
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data: { count: 3 } } })
  })

  afterEach(() => {
    wrapper.unmount()
  })

  it('donne le même compteur à l\'item de sidebar et à l\'onglet mobile, plafonné à 9+', async () => {
    wrapper = mount(FaceLayout, { global: { stubs } })
    await flushPromises()

    expect(apiClient.get).toHaveBeenCalledWith('/face/conversations/unread-count')
    const { sidebar, tab } = messagesItems(wrapper)
    expect(sidebar?.badge).toBe(3)
    expect(tab?.badge).toBe(3)
    expect(sidebar?.badgeMax).toBe(9)
    expect(tab?.badgeMax).toBe(9)
  })

  it('suit le store en direct (événement temps réel) sans nouvelle requête', async () => {
    wrapper = mount(FaceLayout, { global: { stubs } })
    await flushPromises()
    vi.mocked(apiClient.get).mockClear()

    useMessagesUnreadStore().setCount(12)
    await flushPromises()

    const { sidebar, tab } = messagesItems(wrapper)
    expect(sidebar?.badge).toBe(12)
    expect(tab?.badge).toBe(12)
    expect(apiClient.get).not.toHaveBeenCalled()
  })

  it('un seul fetch au montage, aucun fetch aux navigations', async () => {
    wrapper = mount(FaceLayout, { global: { stubs } })
    await flushPromises()
    const unreadCalls = () => vi.mocked(apiClient.get).mock.calls.filter(([url]) => url === '/face/conversations/unread-count').length
    expect(unreadCalls()).toBe(1)

    for (const path of ['/face/missions', '/face/messages', '/face/conversations/abc', '/face/wallet']) {
      routeHolder.route.path = path
      await flushPromises()
    }

    expect(unreadCalls()).toBe(1)
  })
})

describe('FaceLayout - badges d\'actions et barre du bas (lot D3)', () => {
  let wrapper: ReturnType<typeof mount>

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    resetAllSharedCachedResources()
    routeHolder.route.path = '/face/dashboard'
    vi.mocked(apiClient.get).mockImplementation(async (url: string) => {
      if (url === '/me/action-counts') {
        return { data: { data: { bookings: 2, candidatures: 1, livrables: 4, messages: 3 } } }
      }
      return { data: { data: { count: 3 } } }
    })
  })

  afterEach(() => {
    wrapper.unmount()
  })

  it('barre du bas : Accueil, Missions, Bookings, Messages, Profil avec leurs badges', async () => {
    wrapper = mount(FaceLayout, { global: { stubs } })
    await flushPromises()

    const tabs = wrapper.findComponent({ name: 'DashboardLayout' }).props('mobileTabs') as SidebarItem[]
    expect(tabs.map((t) => t.label)).toEqual(['Accueil', 'Missions', 'Bookings', 'Messages', 'Profil'])
    expect(tabs.map((t) => t.to)).toEqual([
      '/face/dashboard', '/face/missions', '/face/bookings', '/face/messages', '/face/profile',
    ])
    // Missions = candidatures à traiter, Bookings = bookings à traiter, Messages = non lus.
    expect(tabs.map((t) => t.badge)).toEqual([undefined, 1, 2, 3, undefined])
    expect(tabs.find((t) => t.label === 'Messages')?.badgeKind).toBe('messages')
  })

  it('barre latérale : badges sur Mes candidatures et Mes bookings', async () => {
    wrapper = mount(FaceLayout, { global: { stubs } })
    await flushPromises()

    const items = wrapper.findComponent({ name: 'DashboardLayout' }).props('sidebarItems') as SidebarItem[]
    expect(items.find((i) => i.to === '/face/candidatures')?.badge).toBe(1)
    expect(items.find((i) => i.to === '/face/bookings')?.badge).toBe(2)
  })

  it('recharge les compteurs à chaque navigation', async () => {
    wrapper = mount(FaceLayout, { global: { stubs } })
    await flushPromises()
    const calls = () => vi.mocked(apiClient.get).mock.calls.filter(([url]) => url === '/me/action-counts').length
    expect(calls()).toBe(1)

    routeHolder.route.path = '/face/bookings'
    await flushPromises()
    expect(calls()).toBe(2)
  })
})
