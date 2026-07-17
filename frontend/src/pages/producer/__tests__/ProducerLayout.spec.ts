import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { ref } from 'vue'
import ProducerLayout from '../ProducerLayout.vue'
import { producerApi } from '@/features/producer/services/producerApi'
import type { SidebarItem } from '@/components/layout'

vi.mock('@/features/producer/services/producerApi', () => ({
  producerApi: { listDeliverablesToReview: vi.fn() },
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
  props: ['sidebarItems', 'title', 'userEmail', 'userName', 'avatarUrl', 'isLoggingOut', 'profileRoute'],
  template: '<div><slot /></div>',
}

describe('ProducerLayout', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    routeHolder.route.path = '/producer/dashboard'
    routeHolder.route.fullPath = '/producer/dashboard'
  })

  afterEach(() => {
    wrappers.splice(0).forEach((w) => w.unmount())
  })

  it('feeds the in_review count as a badge on the Validation livrables item', async () => {
    vi.mocked(producerApi.listDeliverablesToReview).mockResolvedValue({ data: [{}, {}, {}] as never })
    const wrapper = mount(ProducerLayout, {
      global: { stubs: { DashboardLayout: DashboardLayoutStub, EmailVerificationBanner: true, RouterView: true } },
    })
    wrappers.push(wrapper)
    await flushPromises()

    const items = wrapper.findComponent({ name: 'DashboardLayout' }).props('sidebarItems') as SidebarItem[]
    expect(items.find((i) => i.to === '/producer/ugc/validation')?.badge).toBe(3)
    expect(items.find((i) => i.to === '/producer/dashboard')?.badge).toBeUndefined()
  })

  // F13: the layout persists for the whole session (keep-alive rework removed the
  // per-route remount that used to re-run onMounted), so the badge count must be
  // re-fetched on every child-route navigation. The store's fetchCount delegates
  // 1:1 to producerApi.listDeliverablesToReview (no dedup), so the API mock's call
  // count is a faithful proxy for fetchCount invocations.
  it('re-fetches the validation count on each child-route navigation', async () => {
    vi.mocked(producerApi.listDeliverablesToReview).mockResolvedValue({ data: [{}] as never })
    wrappers.push(
      mount(ProducerLayout, {
        global: { stubs: { DashboardLayout: DashboardLayoutStub, EmailVerificationBanner: true, RouterView: true } },
      }),
    )
    await flushPromises()
    // Baseline: one fetch from onMounted
    expect(producerApi.listDeliverablesToReview).toHaveBeenCalledTimes(1)

    // Simulate a child-route navigation while the layout instance persists
    routeHolder.route.path = '/producer/missions'
    routeHolder.route.fullPath = '/producer/missions'
    await flushPromises()

    // Expected contract: a route.path watcher re-fetches the count (cf. FaceLayout)
    expect(producerApi.listDeliverablesToReview).toHaveBeenCalledTimes(2)
  })
})
