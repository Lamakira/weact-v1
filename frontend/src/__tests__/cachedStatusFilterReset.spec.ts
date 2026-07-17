import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref, type Component } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import FaceBookingsListPage from '@/pages/face/booking/FaceBookingsListPage.vue'
import ProducerBookingsListPage from '@/pages/producer/booking/ProducerBookingsListPage.vue'
import FaceCandidaturesPage from '@/pages/face/candidature/FaceCandidaturesPage.vue'

const bookingStatusFilter = ref('')
const candidatureStatusFilter = ref('')
const fetchBookings = vi.fn()
const fetchCandidatures = vi.fn()

vi.mock('@/features/booking/composables', () => ({
  useBookingsList: () => ({
    bookings: ref([]),
    isLoading: ref(false),
    error: ref(null),
    currentPage: ref(1),
    lastPage: ref(1),
    total: ref(0),
    hasNextPage: ref(false),
    hasPrevPage: ref(false),
    isEmpty: ref(true),
    statusFilter: bookingStatusFilter,
    fetchBookings,
    nextPage: vi.fn(),
    prevPage: vi.fn(),
    goToPage: vi.fn(),
    setStatusFilter: vi.fn(),
  }),
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
  statusFilter: typeof bookingStatusFilter
  fetchList: typeof fetchBookings
}

const scenarios: Scenario[] = [
  {
    name: 'Face bookings',
    path: '/face/bookings',
    routeName: 'face-bookings',
    page: FaceBookingsListPage,
    statusFilter: bookingStatusFilter,
    fetchList: fetchBookings,
  },
  {
    name: 'Producer bookings',
    path: '/producer/bookings',
    routeName: 'producer-bookings',
    page: ProducerBookingsListPage,
    statusFilter: bookingStatusFilter,
    fetchList: fetchBookings,
  },
  {
    name: 'Face candidatures',
    path: '/face/candidatures',
    routeName: 'face-candidatures',
    page: FaceCandidaturesPage,
    statusFilter: candidatureStatusFilter,
    fetchList: fetchCandidatures,
  },
]

describe.each(scenarios)('$name — cached status filter vs clean URL', (scenario) => {
  beforeEach(() => {
    bookingStatusFilter.value = ''
    candidatureStatusFilter.value = ''
    vi.clearAllMocks()
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
    expect(scenario.statusFilter.value).toBe('pending')
    const fetchCountBeforeReturn = scenario.fetchList.mock.calls.length

    await router.push('/other')
    await flushPromises()
    await router.push(scenario.path)
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({})
    expect(scenario.statusFilter.value).toBe('')
    expect(scenario.fetchList.mock.calls.length).toBeGreaterThan(fetchCountBeforeReturn)
    expect(scenario.fetchList).toHaveBeenLastCalledWith(1)
    wrapper.unmount()
  })
})
