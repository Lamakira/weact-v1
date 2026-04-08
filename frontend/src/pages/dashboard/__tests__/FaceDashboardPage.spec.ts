import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceDashboardPage from '../FaceDashboardPage.vue'
import type { DashboardStats } from '@/features/dashboard/types'

const mockRouter = {
  push: vi.fn(),
}

vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  RouterLink: {
    template: '<a v-bind="$attrs" :href="typeof to === \'string\' ? to : to.name"><slot /></a>',
    props: ['to'],
    inheritAttrs: false,
  },
}))

const mockFetchCompletion = vi.fn().mockResolvedValue(undefined)
vi.mock('@/features/face/composables/useProfileCompletion', () => ({
  useProfileCompletion: () => ({
    isLoading: ref(false),
    percentage: ref(75),
    fetchCompletion: mockFetchCompletion,
  }),
}))

const mockStats = ref<DashboardStats | null>(null)
const mockIsStatsLoading = ref(false)
const mockStatsError = ref<string | null>(null)
const mockFetchStats = vi.fn().mockResolvedValue(undefined)
const mockRetryStats = vi.fn().mockResolvedValue(undefined)

const mockCandidaturesByMonth = ref([{ month: 'Jan', count: 2 }])
const mockMissionsCompletedByMonth = ref([{ month: 'Jan', count: 1 }])
const mockIsChartsLoading = ref(false)
const mockChartsError = ref<string | null>(null)
const mockFetchChartStats = vi.fn().mockResolvedValue(undefined)
const mockRetryCharts = vi.fn().mockResolvedValue(undefined)

const mockMissionsCount = ref(4)
const mockFetchMissionsCount = vi.fn().mockResolvedValue(undefined)

const mockBookingStats = ref({
  pending: 1,
  accepted: 2,
  in_progress: 3,
  completed: 4,
})
const mockIsBookingStatsLoading = ref(false)
const mockFetchBookingStats = vi.fn().mockResolvedValue(undefined)

const mockBookingsByMonth = ref([{ month: 'Jan', count: 1 }])
const mockBookingsCompletedByMonth = ref([{ month: 'Jan', count: 1 }])
const mockIsBookingChartsLoading = ref(false)
const mockBookingChartsError = ref<string | null>(null)
const mockFetchBookingChartStats = vi.fn().mockResolvedValue(undefined)
const mockRetryBookingCharts = vi.fn().mockResolvedValue(undefined)

vi.mock('@/features/dashboard', () => ({
  useDashboardStats: () => ({
    stats: mockStats,
    isLoading: mockIsStatsLoading,
    error: mockStatsError,
    fetchStats: mockFetchStats,
    retry: mockRetryStats,
  }),
  useDashboardCharts: () => ({
    candidaturesByMonth: mockCandidaturesByMonth,
    missionsCompletedByMonth: mockMissionsCompletedByMonth,
    isLoading: mockIsChartsLoading,
    error: mockChartsError,
    fetchChartStats: mockFetchChartStats,
    retry: mockRetryCharts,
  }),
  useMissionsCount: () => ({
    count: mockMissionsCount,
    isLoading: ref(false),
    fetchMissionsCount: mockFetchMissionsCount,
  }),
  useBookingStats: () => ({
    bookingStats: mockBookingStats,
    isLoading: mockIsBookingStatsLoading,
    fetchBookingStats: mockFetchBookingStats,
  }),
  useDashboardBookingCharts: () => ({
    bookingsByMonth: mockBookingsByMonth,
    bookingsCompletedByMonth: mockBookingsCompletedByMonth,
    isLoading: mockIsBookingChartsLoading,
    error: mockBookingChartsError,
    fetchBookingChartStats: mockFetchBookingChartStats,
    retry: mockRetryBookingCharts,
  }),
  ActivityChart: {
    name: 'ActivityChart',
    template: '<div data-testid="activity-chart" :data-loading="isLoading" :data-error="error">Mon évolution</div>',
    props: ['candidaturesByMonth', 'missionsCompletedByMonth', 'isLoading', 'error'],
  },
  BookingActivityChart: {
    name: 'BookingActivityChart',
    template: '<div data-testid="booking-activity-chart" :data-loading="isLoading" :data-error="error">Mes bookings</div>',
    props: ['bookingsByMonth', 'bookingsCompletedByMonth', 'isLoading', 'error'],
  },
}))

const mockWalletBalance = ref(125000)
const mockFetchWallet = vi.fn().mockResolvedValue(undefined)
vi.mock('@/features/wallet', () => ({
  useWallet: () => ({
    balance: mockWalletBalance,
    fetchWallet: mockFetchWallet,
  }),
}))

vi.mock('@/features/face/services/faceApi', () => ({
  faceApi: {
    getProfile: vi.fn().mockResolvedValue({
      data: {
        prenom: 'Ada',
        nom: 'Dossou',
        profile_photo_url: null,
      },
    }),
    getCategoryNiche: vi.fn().mockResolvedValue({
      data: {
        categories: [{ label: 'Actrice' }],
        niches: [{ label: 'Publicité' }],
      },
    }),
    getBioLocation: vi.fn().mockResolvedValue({
      data: {
        ville: 'Cotonou',
      },
    }),
  },
}))

vi.mock('@/components/ui/skeleton', () => ({
  Skeleton: {
    template: '<div data-testid="skeleton"></div>',
    props: ['class'],
  },
}))

function mountPage() {
  return mount(FaceDashboardPage)
}

describe('FaceDashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRouter.push.mockReset()
    mockStats.value = {
      pending: 3,
      accepted: 2,
      in_progress: 5,
      completed: 10,
    }
    mockIsStatsLoading.value = false
    mockStatsError.value = null
    mockIsChartsLoading.value = false
    mockChartsError.value = null
    mockIsBookingStatsLoading.value = false
    mockIsBookingChartsLoading.value = false
    mockBookingChartsError.value = null
    mockWalletBalance.value = 125000
  })

  it('renders candidature and booking KPI cards with the provided values', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="kpi-card-pending"]').text()).toContain('3')
    expect(wrapper.find('[data-testid="kpi-card-accepted"]').text()).toContain('2')
    expect(wrapper.find('[data-testid="kpi-card-in_progress"]').text()).toContain('5')
    expect(wrapper.find('[data-testid="kpi-card-completed"]').text()).toContain('10')
    expect(wrapper.find('[data-testid="booking-kpi-pending"]').text()).toContain('1')
    expect(wrapper.find('[data-testid="booking-kpi-completed"]').text()).toContain('4')
  })

  it('shows the stats error state and retries when requested', async () => {
    mockStatsError.value = 'Une erreur est survenue'

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="stats-error"]').text()).toContain('Une erreur est survenue')

    await wrapper.find('[data-testid="retry-stats-button"]').trigger('click')

    expect(mockRetryStats).toHaveBeenCalled()
  })

  it('fetches dashboard, wallet, and booking data on mount', async () => {
    mountPage()
    await flushPromises()

    expect(mockFetchCompletion).toHaveBeenCalled()
    expect(mockFetchStats).toHaveBeenCalled()
    expect(mockFetchChartStats).toHaveBeenCalled()
    expect(mockFetchMissionsCount).toHaveBeenCalled()
    expect(mockFetchWallet).toHaveBeenCalled()
    expect(mockFetchBookingStats).toHaveBeenCalled()
    expect(mockFetchBookingChartStats).toHaveBeenCalled()
  })

  it('renders the quick access area and navigates from the action cards', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="quick-access-cards-grid"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="browse-missions-card"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="messages-card"]').exists()).toBe(true)

    await wrapper.find('[data-testid="browse-missions-card"]').trigger('click')
    await wrapper.find('[data-testid="messages-card"]').trigger('click')

    expect(mockRouter.push).toHaveBeenCalledWith({ name: 'face-missions' })
    expect(mockRouter.push).toHaveBeenCalledWith({ name: 'face-messages' })
  })

  it('renders the wallet quick-access card with the formatted balance', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const walletCard = wrapper.find('[data-testid="wallet-card"]')

    expect(walletCard.exists()).toBe(true)
    expect(walletCard.text()).toContain('Mon portefeuille')
    expect(walletCard.text()).toContain('125')
    expect(walletCard.attributes('href')).toBe('face-wallet')
  })

  it('renders candidature and booking charts with current loading and error props', async () => {
    mockIsChartsLoading.value = true
    mockChartsError.value = 'Erreur candidatures'
    mockIsBookingChartsLoading.value = true
    mockBookingChartsError.value = 'Erreur bookings'

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="activity-chart"]').attributes('data-loading')).toBe('true')
    expect(wrapper.find('[data-testid="activity-chart"]').attributes('data-error')).toBe('Erreur candidatures')
    expect(wrapper.find('[data-testid="booking-activity-chart"]').attributes('data-loading')).toBe('true')
    expect(wrapper.find('[data-testid="booking-activity-chart"]').attributes('data-error')).toBe('Erreur bookings')
  })
})
