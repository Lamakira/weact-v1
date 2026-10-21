import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { ref } from 'vue'
import ProducerLayout from '../ProducerLayout.vue'
import { producerApi } from '@/features/producer/services/producerApi'
import apiClient from '@/services/apiClient'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'
import type { SidebarItem } from '@/components/layout'

vi.mock('@/features/producer/services/producerApi', () => ({
  producerApi: { listDeliverablesToReview: vi.fn() },
}))
vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn().mockResolvedValue({ data: { data: { count: 0 } } }) },
  getAuthToken: vi.fn(() => null),
}))
vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({ logout: vi.fn(), isLoading: ref(false) }),
}))
vi.mock('@/features/producer/composables/useProducerProfilePhoto', () => ({
  useProducerProfilePhoto: () => ({ profile: ref(null), fetchProfile: vi.fn().mockResolvedValue(undefined) }),
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: { email: 'p@x.bj' }, isEmailVerified: true }),
}))

// Le rappel WhatsApp n'est plus chargé par le layout (ligne de la to-do du tableau de bord) :
// ce mock vérifie qu'aucun chargement des infos de base n'est déclenché.
const basicInfoFetch = vi.hoisted(() => vi.fn().mockResolvedValue(undefined))
vi.mock('@/features/producer/composables/useProducerBasicInfo', () => ({
  useProducerBasicInfo: () => ({ basicInfo: { value: null }, fetchBasicInfo: basicInfoFetch }),
}))

// Harness: mutable REACTIVE route so tests can simulate child-route navigation
// (route.path change) and trigger any route watcher in the layout. Exposed via a
// hoisted holder because vi.mock factories cannot reference top-level variables.
const routeHolder = vi.hoisted(() => ({
  route: null as unknown as { path: string; fullPath: string; meta: Record<string, unknown> },
}))
vi.mock('vue-router', async () => {
  const { reactive, h } = await import('vue')
  routeHolder.route = reactive({
    path: '/producer/dashboard',
    fullPath: '/producer/dashboard',
    meta: {} as Record<string, unknown>,
    query: {},
    params: {},
  })
  return {
    useRoute: () => routeHolder.route,
    useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
    RouterLink: {
      name: 'RouterLink',
      props: ['to'],
      setup: (_props: unknown, { slots }: { slots: { default?: () => unknown } }) =>
        () => h('a', {}, slots.default?.() as never),
    },
    RouterView: { name: 'RouterView', render: () => null },
  }
})

// The mocked route is a module singleton: every mounted layout must be
// unmounted between tests, or a previous test's route watcher would fire
// again on the next test's route mutation and skew the call counts.
const wrappers: Array<ReturnType<typeof mount>> = []

const DashboardLayoutStub = {
  name: 'DashboardLayout',
  props: ['sidebarItems', 'mobileTabs', 'title', 'userEmail', 'userName', 'avatarUrl', 'isLoggingOut', 'profileRoute'],
  template: '<div><slot name="alerts" /><slot /></div>',
}

// Routage des GET : compteurs d'actions (lot D3) et compteur de conversations non lues.
function mockApi(actionCounts: Record<string, number> = {}, unread = 0) {
  vi.mocked(apiClient.get).mockImplementation(async (url: string) => {
    if (url === '/me/action-counts') return { data: { data: actionCounts } }
    return { data: { data: { count: unread } } }
  })
}

const actionCountCalls = () => vi.mocked(apiClient.get).mock.calls.filter(([url]) => url === '/me/action-counts').length

describe('ProducerLayout', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    routeHolder.route.path = '/producer/dashboard'
    routeHolder.route.fullPath = '/producer/dashboard'
    resetAllSharedCachedResources()
    mockApi()
  })

  afterEach(() => {
    wrappers.splice(0).forEach((w) => w.unmount())
  })

  it('feeds the action counters as badges on Missions, Bookings and Validation des livrables', async () => {
    mockApi({ missions: 2, bookings: 4, livrables: 3 })
    const wrapper = mount(ProducerLayout, {
      global: { stubs: { DashboardLayout: DashboardLayoutStub, AccountAlertBar: true, RouterView: true } },
    })
    wrappers.push(wrapper)
    await flushPromises()

    const layout = wrapper.findComponent({ name: 'DashboardLayout' })
    const items = layout.props('sidebarItems') as SidebarItem[]
    expect(items.find((i) => i.to === '/producer/ugc/validation')?.badge).toBe(3)
    expect(items.find((i) => i.to === '/producer/missions')?.badge).toBe(2)
    expect(items.find((i) => i.to === '/producer/bookings')?.badge).toBe(4)
    expect(items.find((i) => i.to === '/producer/dashboard')?.badge).toBeUndefined()
    // Aucun chargement de la liste complète des livrables pour le badge.
    expect(producerApi.listDeliverablesToReview).not.toHaveBeenCalled()
  })

  it('bottom bar: Accueil, Missions, Bookings, Messages, Livrables with their badges', async () => {
    mockApi({ missions: 2, bookings: 4, livrables: 3 }, 5)
    const wrapper = mount(ProducerLayout, {
      global: { stubs: { DashboardLayout: DashboardLayoutStub, AccountAlertBar: true, RouterView: true } },
    })
    wrappers.push(wrapper)
    await flushPromises()

    const tabs = wrapper.findComponent({ name: 'DashboardLayout' }).props('mobileTabs') as SidebarItem[]
    expect(tabs.map((t) => t.label)).toEqual(['Accueil', 'Missions', 'Bookings', 'Messages', 'Livrables'])
    expect(tabs.map((t) => t.badge)).toEqual([undefined, 2, 4, 5, 3])
    expect(tabs.find((t) => t.label === 'Messages')?.badgeKind).toBe('messages')
    expect(tabs.find((t) => t.label === 'Livrables')?.to).toBe('/producer/ugc/validation')
  })

  describe('Barre d\'alertes', () => {
    it('monte une seule barre d\'alertes (audience producer) et aucun bandeau empilé', async () => {
      const wrapper = mount(ProducerLayout, {
        global: { stubs: { DashboardLayout: DashboardLayoutStub, AccountAlertBar: true, RouterView: true } },
      })
      wrappers.push(wrapper)
      await flushPromises()

      const bars = wrapper.findAllComponents({ name: 'AccountAlertBar' })
      expect(bars).toHaveLength(1)
      expect(bars[0]!.props('audience')).toBe('producer')
      expect(wrapper.find('[data-testid="email-verification-banner"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="whatsapp-missing-banner"]').exists()).toBe(false)
    })

    it('ne charge plus les infos de base : le rappel WhatsApp est une ligne de la to-do', async () => {
      wrappers.push(
        mount(ProducerLayout, {
          global: { stubs: { DashboardLayout: DashboardLayoutStub, AccountAlertBar: true, RouterView: true } },
        }),
      )
      await flushPromises()
      routeHolder.route.path = '/producer/missions'
      await flushPromises()

      expect(basicInfoFetch).not.toHaveBeenCalled()
    })
  })

  // F13: the layout persists for the whole session (keep-alive rework removed the
  // per-route remount that used to re-run onMounted), so the badges must be
  // re-fetched on every child-route navigation (the user's own actions emit no event to them).
  it('re-fetches the action counters on each child-route navigation', async () => {
    wrappers.push(
      mount(ProducerLayout, {
        global: { stubs: { DashboardLayout: DashboardLayoutStub, AccountAlertBar: true, RouterView: true } },
      }),
    )
    await flushPromises()
    // Baseline: one fetch from onMounted
    expect(actionCountCalls()).toBe(1)

    // Simulate a child-route navigation while the layout instance persists
    routeHolder.route.path = '/producer/missions'
    routeHolder.route.fullPath = '/producer/missions'
    await flushPromises()

    expect(actionCountCalls()).toBe(2)
  })

  describe('Messages unread badge', () => {
    async function mountLayoutWithCount(count: number) {
      mockApi({}, count)
      const wrapper = mount(ProducerLayout, {
        global: { stubs: { DashboardLayout: DashboardLayoutStub, AccountAlertBar: true, RouterView: true } },
      })
      wrappers.push(wrapper)
      await flushPromises()
      return wrapper
    }

    it('feeds the same count to the sidebar item and the mobile tab, capped at 9+', async () => {
      const wrapper = await mountLayoutWithCount(12)
      const layout = wrapper.findComponent({ name: 'DashboardLayout' })
      const item = (layout.props('sidebarItems') as SidebarItem[]).find((i) => i.to === '/producer/messages')
      const tab = (layout.props('mobileTabs') as SidebarItem[]).find((i) => i.to === '/producer/messages')

      expect(apiClient.get).toHaveBeenCalledWith('/producer/conversations/unread-count')
      expect(item?.badge).toBe(12)
      expect(tab?.badge).toBe(12)
      expect(item?.badgeMax).toBe(9)
      expect(tab?.badgeMax).toBe(9)
    })

    it('badge is 0 (hidden) when nothing is unread', async () => {
      const wrapper = await mountLayoutWithCount(0)
      const items = wrapper.findComponent({ name: 'DashboardLayout' }).props('sidebarItems') as SidebarItem[]
      expect(items.find((i) => i.to === '/producer/messages')?.badge).toBe(0)
    })

    it('fetches once at boot and never again on route changes', async () => {
      await mountLayoutWithCount(2)
      const unreadCalls = () => vi.mocked(apiClient.get).mock.calls.filter(([url]) => url === '/producer/conversations/unread-count').length
      expect(unreadCalls()).toBe(1)

      for (const path of ['/producer/missions', '/producer/wallet', '/producer/messages']) {
        routeHolder.route.path = path
        routeHolder.route.fullPath = path
        await flushPromises()
      }

      expect(unreadCalls()).toBe(1)
    })
  })

  describe('header title', () => {
    async function mountAt(path: string) {
      routeHolder.route.path = path
      routeHolder.route.fullPath = path
      vi.mocked(producerApi.listDeliverablesToReview).mockResolvedValue({ data: [] as never })
      const wrapper = mount(ProducerLayout, {
        global: { stubs: { DashboardLayout: DashboardLayoutStub, AccountAlertBar: true, RouterView: true } },
      })
      wrappers.push(wrapper)
      await flushPromises()
      return wrapper
    }

    it.each([
      ['/producer/dashboard', 'Tableau de bord'],
      ['/producer/missions', 'Mes missions'],
      ['/producer/missions/publish', 'Publier une mission'],
      ['/producer/missions/abc/edit', 'Mes missions'],
      ['/producer/faces', 'Liste des Faces'],
      ['/producer/bookings', 'Mes bookings'],
      ['/producer/messages', 'Messages'],
      ['/producer/profile', 'Mon profil'],
    ])('shows a specific French title on %s', async (path, expected) => {
      const wrapper = await mountAt(path)
      expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('title')).toBe(expected)
    })

    it('names the dashboard « Tableau de bord » in the sidebar', async () => {
      const wrapper = await mountAt('/producer/dashboard')
      const items = wrapper.findComponent({ name: 'DashboardLayout' }).props('sidebarItems') as SidebarItem[]
      expect(items.find((i) => i.to === '/producer/dashboard')?.label).toBe('Tableau de bord')
    })
  })
})
