import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref, type Component } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import FaceBookingsListPage from '@/pages/face/booking/FaceBookingsListPage.vue'
import ProducerBookingsListPage from '@/pages/producer/booking/ProducerBookingsListPage.vue'
import FaceCandidaturesPage from '@/pages/face/candidature/FaceCandidaturesPage.vue'

const candidatureStatusFilter = ref('')
const fetchCandidatures = vi.fn()

// Bookings lists keep their state in the URL (no cached status in the composable):
// the probe is the status argument of the last API call.
const getBookings = vi.fn()
vi.mock('@/features/booking/services/bookingApi', () => ({
  bookingApi: { getBookings: (...args: unknown[]) => getBookings(...args) },
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: { id: 1, userable_type: 'Face' } }),
}))

vi.mock('@/features/candidature/composables', () => ({
  useFaceCandidatures: () => ({
    candidatures: ref([]),
    isLoading: ref(false),
    error: ref(null),
    currentPage: ref(1),
    lastPage: ref(1),
    total: ref(0),
    hasNextPage: ref(false),
    hasPrevPage: ref(false),
    isEmpty: ref(true),
    statusFilter: candidatureStatusFilter,
    fetchCandidatures,
    nextPage: vi.fn(),
    prevPage: vi.fn(),
    goToPage: vi.fn(),
    setStatusFilter: vi.fn(),
    refresh: vi.fn(),
  }),
  useConfirmCandidature: () => ({
    error: ref(null),
    successMessage: ref(null),
    confirmCandidature: vi.fn(),
    reset: vi.fn(),
  }),
  useReconfirmCandidature: () => ({
    error: ref(null),
    successMessage: ref(null),
    reconfirmCandidature: vi.fn(),
    reset: vi.fn(),
  }),
  useCancelCandidature: () => ({
    error: ref(null),
    successMessage: ref(null),
    cancelCandidature: vi.fn(),
    reset: vi.fn(),
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}))

const OtherPage = { template: '<div>Other page</div>' }
const RootLayout = {
  template: `
    <router-view v-slot="{ Component }">
      <keep-alive>
        <component :is="Component" v-if="$route.meta.keepAlive" />
      </keep-alive>
      <component :is="Component" v-if="!$route.meta.keepAlive" />
    </router-view>
  `,
}

interface Scenario {
  name: string
  path: string
  routeName: string
  page: Component
  /** Status the list was last asked for. */
  currentStatus: () => string
  fetchList: ReturnType<typeof vi.fn>
}

const lastBookingsStatus = (): string => String(getBookings.mock.lastCall?.[1] ?? '')

const scenarios: Scenario[] = [
  {
    name: 'Face bookings',
    path: '/face/bookings',
    routeName: 'face-bookings',
    page: FaceBookingsListPage,
    currentStatus: lastBookingsStatus,
    fetchList: getBookings,
  },
  {
    name: 'Producer bookings',
    path: '/producer/bookings',
    routeName: 'producer-bookings',
    page: ProducerBookingsListPage,
    currentStatus: lastBookingsStatus,
    fetchList: getBookings,
  },
  {
    name: 'Face candidatures',
    path: '/face/candidatures',
    routeName: 'face-candidatures',
    page: FaceCandidaturesPage,
    currentStatus: () => candidatureStatusFilter.value,
    fetchList: fetchCandidatures,
  },
]

describe.each(scenarios)('$name — cached status filter vs clean URL', (scenario) => {
  beforeEach(() => {
    candidatureStatusFilter.value = ''
    vi.clearAllMocks()
    getBookings.mockResolvedValue({
      data: [],
      links: { first: null, last: null, prev: null, next: null },
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    })
  })

  it('clears the cached status when returning through a URL without status', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: scenario.path,
          name: scenario.routeName,
          component: scenario.page,
          meta: { keepAlive: true },
        },
        { path: '/other', name: 'other', component: OtherPage },
      ],
    })

    await router.push(`${scenario.path}?status=pending`)
    await router.isReady()
    const wrapper = mount(RootLayout, {
      global: {
        plugins: [router],
        stubs: {
          BookingCard: true,
          BookingStatusFilter: true,
          CandidatureCard: true,
          StatusFilter: true,
          ConfirmModal: true,
        },
      },
    })
    await flushPromises()
    expect(scenario.currentStatus()).toBe('pending')
    const fetchCountBeforeReturn = scenario.fetchList.mock.calls.length

    await router.push('/other')
    await flushPromises()
    await router.push(scenario.path)
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({})
    expect(scenario.currentStatus()).toBe('')
    expect(scenario.fetchList.mock.calls.length).toBeGreaterThan(fetchCountBeforeReturn)
    expect(scenario.fetchList.mock.lastCall?.[0]).toBe(1)
    wrapper.unmount()
  })
})
