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

// Shared basic-info resource: tests drive it through these hoisted refs.
const basicInfoHolder = vi.hoisted(() => ({
  info: null as unknown as { value: { type: string; whatsapp_number?: string | null } | null },
  fetch: null as unknown as ReturnType<typeof vi.fn>,
}))
vi.mock('@/features/producer/composables/useProducerBasicInfo', async () => {
  const { ref } = await import('vue')
  basicInfoHolder.info = ref(null)
  basicInfoHolder.fetch = vi.fn().mockResolvedValue(undefined)
  return {
    useProducerBasicInfo: () => ({
      basicInfo: basicInfoHolder.info,
      fetchBasicInfo: basicInfoHolder.fetch,
    }),
  }
})

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
    basicInfoHolder.info.value = null
    basicInfoHolder.fetch.mockResolvedValue(undefined)
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

  describe('WhatsApp reminder banner', () => {
    const layoutStubs = {
      DashboardLayout: DashboardLayoutStub,
      EmailVerificationBanner: true,
      RouterView: true,
    }

    async function mountLayout() {
      vi.mocked(producerApi.listDeliverablesToReview).mockResolvedValue({ data: [] as never })
      const wrapper = mount(ProducerLayout, { global: { stubs: layoutStubs } })
      wrappers.push(wrapper)
      await flushPromises()
      return wrapper
    }

    it('shows the banner, pointing to the profile field, while the number is missing', async () => {
      basicInfoHolder.fetch.mockImplementation(async () => {
        basicInfoHolder.info.value = { type: 'particulier', whatsapp_number: null }
      })

      const wrapper = await mountLayout()
      const banner = wrapper.findComponent({ name: 'WhatsappMissingBanner' })

      expect(banner.exists()).toBe(true)
      expect(banner.props('to')).toBe('/producer/profile?focus=whatsapp')
      expect(banner.text()).toContain(
        "Ajoutez votre numéro WhatsApp pour que l'équipe WeAct puisse vous joindre rapidement.",
      )
    })

    it('hides the banner once the Producer has a number', async () => {
      basicInfoHolder.fetch.mockImplementation(async () => {
        basicInfoHolder.info.value = { type: 'agency', whatsapp_number: '+22997000000' }
      })

      const wrapper = await mountLayout()

      expect(wrapper.findComponent({ name: 'WhatsappMissingBanner' }).exists()).toBe(false)
    })

    it('hides the banner again when the number gets saved elsewhere', async () => {
      basicInfoHolder.fetch.mockImplementation(async () => {
        basicInfoHolder.info.value = { type: 'particulier', whatsapp_number: null }
      })
      const wrapper = await mountLayout()
      expect(wrapper.findComponent({ name: 'WhatsappMissingBanner' }).exists()).toBe(true)

      basicInfoHolder.info.value = { type: 'particulier', whatsapp_number: '+22997000000' }
      await flushPromises()

      expect(wrapper.findComponent({ name: 'WhatsappMissingBanner' }).exists()).toBe(false)
    })

    it('does not flash the banner when the basic info could not be loaded', async () => {
      basicInfoHolder.fetch.mockRejectedValue(new Error('network'))

      const wrapper = await mountLayout()

      expect(wrapper.findComponent({ name: 'WhatsappMissingBanner' }).exists()).toBe(false)
    })
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
