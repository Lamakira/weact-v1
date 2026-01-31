import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceDashboardPage from '../FaceDashboardPage.vue'
import type { DashboardStats } from '@/features/dashboard/types'

// Mock vue-router
const mockRouter = {
  push: vi.fn(),
}
const mockRoute = {
  path: '/face/dashboard',
  name: 'face-dashboard',
}
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  useRoute: () => mockRoute,
}))

// Mock auth store
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { email: 'test@example.com' },
  }),
}))

// Mock useAuth composable
vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    logout: vi.fn(),
    isLoading: ref(false),
  }),
}))

// Mock profile completion composable
vi.mock('@/features/face/composables/useProfileCompletion', () => ({
  useProfileCompletion: () => ({
    isLoading: ref(false),
    percentage: ref(75),
    missingItems: ref(['bio', 'photo']),
    fetchCompletion: vi.fn().mockResolvedValue(undefined),
  }),
}))

// Mock dashboard stats composable
const mockStats = ref<DashboardStats | null>(null)
const mockIsStatsLoading = ref(false)
const mockStatsError = ref<string | null>(null)
const mockFetchStats = vi.fn().mockResolvedValue(undefined)
const mockRetryStats = vi.fn().mockResolvedValue(undefined)

// Mock dashboard charts composable
const mockCandidaturesByMonth = ref([])
const mockMissionsCompletedByMonth = ref([])
const mockIsChartsLoading = ref(false)
const mockChartsError = ref<string | null>(null)
const mockFetchChartStats = vi.fn().mockResolvedValue(undefined)
const mockRetryCharts = vi.fn().mockResolvedValue(undefined)

// Mock missions count composable
const mockMissionsCount = ref(0)
const mockIsMissionsCountLoading = ref(false)
const mockFetchMissionsCount = vi.fn().mockResolvedValue(undefined)

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
    isLoading: mockIsMissionsCountLoading,
    fetchMissionsCount: mockFetchMissionsCount,
  }),
  KpiCard: {
    name: 'KpiCard',
    template: `
      <div
        :data-testid="$attrs['data-testid']"
        data-component="kpi-card"
        :data-title="title"
        :data-value="value"
        :data-loading="isLoading"
      >
        <span class="title">{{ title }}</span>
        <span class="value">{{ value }}</span>
      </div>
    `,
    props: ['title', 'value', 'icon', 'color', 'isLoading'],
  },
  WalletCard: {
    name: 'WalletCard',
    template: `
      <div
        data-testid="wallet-card"
        data-component="wallet-card"
      >
        <span>0 XOF</span>
        <span>Bientôt disponible</span>
      </div>
    `,
  },
  ActivityChart: {
    name: 'ActivityChart',
    template: `
      <div
        data-testid="activity-chart"
        data-component="activity-chart"
        :data-loading="isLoading"
        :data-error="error"
      >
        <span>Mon évolution</span>
      </div>
    `,
    props: ['candidaturesByMonth', 'missionsCompletedByMonth', 'isLoading', 'error'],
    emits: ['retry'],
  },
  MissionsQuickAccessCard: {
    name: 'MissionsQuickAccessCard',
    template: `
      <div
        data-testid="browse-missions-card"
        data-component="missions-quick-access-card"
        :data-count="count"
        :data-loading="isLoading"
        @click="$emit('click')"
      >
        <span>Voir les missions</span>
        <span v-if="count > 0" data-testid="missions-count-badge">{{ count }}</span>
      </div>
    `,
    props: ['count', 'isLoading'],
    emits: ['click'],
  },
  MessagesCard: {
    name: 'MessagesCard',
    template: `
      <div
        data-testid="messages-card"
        data-component="messages-card"
        @click="$emit('click')"
      >
        <span>Messages</span>
        <span>Discussions avec les producteurs</span>
      </div>
    `,
    emits: ['click'],
  },
  FACE_KPI_CONFIGS: [
    { key: 'pending', title: 'En attente', color: 'amber-500', bgColor: 'amber-50', icon: 'clock' },
    { key: 'accepted', title: 'Acceptées', color: 'green-500', bgColor: 'green-50', icon: 'check' },
    { key: 'in_progress', title: 'En cours', color: 'blue-500', bgColor: 'blue-50', icon: 'play' },
    { key: 'completed', title: 'Terminées', color: 'primary', bgColor: 'primary/10', icon: 'checkCircle' },
  ],
}))

// Mock ProfileCompletionCard
vi.mock('@/features/face/components/ProfileCompletionCard.vue', () => ({
  default: {
    name: 'ProfileCompletionCard',
    template: '<div data-testid="profile-completion-card">Profile Completion</div>',
    props: ['percentage', 'missingCount', 'isLoading'],
  },
}))

describe('FaceDashboardPage', () => {
  beforeEach(() => {
    mockStats.value = null
    mockIsStatsLoading.value = false
    mockStatsError.value = null
    mockFetchStats.mockClear()
    mockRetryStats.mockClear()
    mockRouter.push.mockClear()
    // Reset chart mocks
    mockCandidaturesByMonth.value = []
    mockMissionsCompletedByMonth.value = []
    mockIsChartsLoading.value = false
    mockChartsError.value = null
    mockFetchChartStats.mockClear()
    mockRetryCharts.mockClear()
    // Reset missions count mocks
    mockMissionsCount.value = 0
    mockIsMissionsCountLoading.value = false
    mockFetchMissionsCount.mockClear()
  })

  describe('KPI cards rendering', () => {
    it('renders 4 KPI cards when stats are loaded', async () => {
      mockStats.value = {
        pending: 3,
        accepted: 2,
        in_progress: 5,
        completed: 10,
      }

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const kpiCards = wrapper.findAll('[data-component="kpi-card"]')
      expect(kpiCards.length).toBe(4)
    })

    it('renders KPI card with correct pending value', async () => {
      mockStats.value = {
        pending: 7,
        accepted: 0,
        in_progress: 0,
        completed: 0,
      }

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const pendingCard = wrapper.find('[data-testid="kpi-card-pending"]')
      expect(pendingCard.exists()).toBe(true)
      expect(pendingCard.attributes('data-value')).toBe('7')
    })

    it('renders KPI card with correct accepted value', async () => {
      mockStats.value = {
        pending: 0,
        accepted: 4,
        in_progress: 0,
        completed: 0,
      }

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const acceptedCard = wrapper.find('[data-testid="kpi-card-accepted"]')
      expect(acceptedCard.exists()).toBe(true)
      expect(acceptedCard.attributes('data-value')).toBe('4')
    })

    it('renders KPI card with correct in_progress value', async () => {
      mockStats.value = {
        pending: 0,
        accepted: 0,
        in_progress: 8,
        completed: 0,
      }

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const inProgressCard = wrapper.find('[data-testid="kpi-card-in_progress"]')
      expect(inProgressCard.exists()).toBe(true)
      expect(inProgressCard.attributes('data-value')).toBe('8')
    })

    it('renders KPI card with correct completed value', async () => {
      mockStats.value = {
        pending: 0,
        accepted: 0,
        in_progress: 0,
        completed: 15,
      }

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const completedCard = wrapper.find('[data-testid="kpi-card-completed"]')
      expect(completedCard.exists()).toBe(true)
      expect(completedCard.attributes('data-value')).toBe('15')
    })

    it('displays zero values correctly', async () => {
      mockStats.value = {
        pending: 0,
        accepted: 0,
        in_progress: 0,
        completed: 0,
      }

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const pendingCard = wrapper.find('[data-testid="kpi-card-pending"]')
      expect(pendingCard.attributes('data-value')).toBe('0')
    })

    it('renders KPI cards grid container', async () => {
      mockStats.value = {
        pending: 1,
        accepted: 1,
        in_progress: 1,
        completed: 1,
      }

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const grid = wrapper.find('[data-testid="kpi-cards-grid"]')
      expect(grid.exists()).toBe(true)
    })
  })

  describe('loading state', () => {
    it('passes isLoading=true to KPI cards when loading', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const kpiCards = wrapper.findAll('[data-component="kpi-card"]')
      expect(kpiCards.length).toBe(4)
      expect(kpiCards[0].attributes('data-loading')).toBe('true')
    })

    it('passes isLoading=false to KPI cards when loaded', async () => {
      mockIsStatsLoading.value = false
      mockStats.value = {
        pending: 1,
        accepted: 1,
        in_progress: 1,
        completed: 1,
      }

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const kpiCards = wrapper.findAll('[data-component="kpi-card"]')
      expect(kpiCards[0].attributes('data-loading')).toBe('false')
    })
  })

  describe('error state', () => {
    it('shows error message when API fails', async () => {
      mockStatsError.value = 'Une erreur est survenue'

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const errorDiv = wrapper.find('[data-testid="stats-error"]')
      expect(errorDiv.exists()).toBe(true)
      expect(errorDiv.text()).toContain('Une erreur est survenue')
    })

    it('shows retry button when error occurs', async () => {
      mockStatsError.value = 'Erreur réseau'

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const retryButton = wrapper.find('[data-testid="retry-stats-button"]')
      expect(retryButton.exists()).toBe(true)
      expect(retryButton.text()).toBe('Réessayer')
    })

    it('calls retry function when retry button is clicked', async () => {
      mockStatsError.value = 'Erreur réseau'

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const retryButton = wrapper.find('[data-testid="retry-stats-button"]')
      await retryButton.trigger('click')

      expect(mockRetryStats).toHaveBeenCalled()
    })

    it('hides KPI cards grid when error occurs', async () => {
      mockStatsError.value = 'Erreur réseau'

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const grid = wrapper.find('[data-testid="kpi-cards-grid"]')
      expect(grid.exists()).toBe(false)
    })
  })

  describe('data fetching', () => {
    it('calls fetchStats on mount', async () => {
      mount(FaceDashboardPage)
      await flushPromises()

      expect(mockFetchStats).toHaveBeenCalled()
    })
  })

  describe('section headers', () => {
    it('displays "Mes candidatures" section title', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      expect(wrapper.text()).toContain('Mes candidatures')
    })

    it('displays "Accès rapides" section title', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      expect(wrapper.text()).toContain('Accès rapides')
    })

    it('displays "Mon évolution" section title', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      expect(wrapper.text()).toContain('Mon évolution')
    })

    it('has proper section accessibility attributes', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      // Check for aria-labelledby attributes on sections
      const sections = wrapper.findAll('section')
      expect(sections.length).toBeGreaterThanOrEqual(3)
    })
  })

  describe('WalletCard integration', () => {
    it('renders WalletCard component', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const walletCard = wrapper.find('[data-testid="wallet-card"]')
      expect(walletCard.exists()).toBe(true)
    })

    it('displays WalletCard in the dashboard cards grid', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const walletCard = wrapper.find('[data-component="wallet-card"]')
      expect(walletCard.exists()).toBe(true)
    })

    it('displays WalletCard as first item in dashboard cards grid', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      // Find the dashboard cards grid (not KPI grid)
      const dashboardCardsGrid = wrapper.findAll('.grid')[1] // Second grid is dashboard cards
      const firstChild = dashboardCardsGrid.element.children[0]
      expect(firstChild.getAttribute('data-component')).toBe('wallet-card')
    })

    it('displays static wallet content without API calls', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const walletCard = wrapper.find('[data-component="wallet-card"]')
      expect(walletCard.text()).toContain('0 XOF')
      expect(walletCard.text()).toContain('Bientôt disponible')
    })
  })

  describe('ActivityChart integration', () => {
    it('renders ActivityChart component', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const activityChart = wrapper.find('[data-testid="activity-chart"]')
      expect(activityChart.exists()).toBe(true)
    })

    it('displays ActivityChart in the dashboard layout', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const activityChart = wrapper.find('[data-component="activity-chart"]')
      expect(activityChart.exists()).toBe(true)
    })

    it('calls fetchChartStats on mount', async () => {
      mount(FaceDashboardPage)
      await flushPromises()

      expect(mockFetchChartStats).toHaveBeenCalled()
    })

    it('passes loading state to ActivityChart', async () => {
      mockIsChartsLoading.value = true

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const activityChart = wrapper.find('[data-component="activity-chart"]')
      expect(activityChart.attributes('data-loading')).toBe('true')
    })

    it('passes error state to ActivityChart', async () => {
      mockChartsError.value = 'Erreur réseau'

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const activityChart = wrapper.find('[data-component="activity-chart"]')
      expect(activityChart.attributes('data-error')).toBe('Erreur réseau')
    })
  })

  describe('MissionsQuickAccessCard integration', () => {
    it('renders MissionsQuickAccessCard component', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const missionsCard = wrapper.find('[data-testid="browse-missions-card"]')
      expect(missionsCard.exists()).toBe(true)
    })

    it('displays MissionsQuickAccessCard in the dashboard cards grid', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const missionsCard = wrapper.find('[data-component="missions-quick-access-card"]')
      expect(missionsCard.exists()).toBe(true)
    })

    it('calls fetchMissionsCount on mount', async () => {
      mount(FaceDashboardPage)
      await flushPromises()

      expect(mockFetchMissionsCount).toHaveBeenCalled()
    })

    it('passes count to MissionsQuickAccessCard', async () => {
      mockMissionsCount.value = 12

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const missionsCard = wrapper.find('[data-component="missions-quick-access-card"]')
      expect(missionsCard.attributes('data-count')).toBe('12')
    })

    it('displays count badge when missions are available', async () => {
      mockMissionsCount.value = 5

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const badge = wrapper.find('[data-testid="missions-count-badge"]')
      expect(badge.exists()).toBe(true)
      expect(badge.text()).toBe('5')
    })

    it('does not display count badge when no missions available', async () => {
      mockMissionsCount.value = 0

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const badge = wrapper.find('[data-testid="missions-count-badge"]')
      expect(badge.exists()).toBe(false)
    })

    it('passes loading state to MissionsQuickAccessCard', async () => {
      mockIsMissionsCountLoading.value = true

      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const missionsCard = wrapper.find('[data-component="missions-quick-access-card"]')
      expect(missionsCard.attributes('data-loading')).toBe('true')
    })

    it('navigates to missions page when card is clicked', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const missionsCard = wrapper.find('[data-testid="browse-missions-card"]')
      await missionsCard.trigger('click')

      expect(mockRouter.push).toHaveBeenCalledWith({ name: 'face-missions' })
    })
  })

  describe('MessagesCard integration', () => {
    it('renders MessagesCard component', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const messagesCard = wrapper.find('[data-testid="messages-card"]')
      expect(messagesCard.exists()).toBe(true)
    })

    it('displays MessagesCard in the quick access cards grid', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const messagesCard = wrapper.find('[data-component="messages-card"]')
      expect(messagesCard.exists()).toBe(true)
    })

    it('navigates to messages page when card is clicked', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const messagesCard = wrapper.find('[data-testid="messages-card"]')
      await messagesCard.trigger('click')

      expect(mockRouter.push).toHaveBeenCalledWith({ name: 'face-messages' })
    })
  })

  // Note: Header tests moved to FaceLayout.spec.ts
  // FaceDashboardPage is now content-only (rendered inside FaceLayout)

  describe('layout structure', () => {
    it('renders quick access cards grid container', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const grid = wrapper.find('[data-testid="quick-access-cards-grid"]')
      expect(grid.exists()).toBe(true)
    })

    it('renders all 4 quick access cards', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      const grid = wrapper.find('[data-testid="quick-access-cards-grid"]')
      expect(grid.element.children.length).toBe(4)
    })

    it('does not display welcome message (removed)', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      expect(wrapper.text()).not.toContain('Bienvenue sur votre Dashboard Face')
    })
  })
})
