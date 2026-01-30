import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceDashboardPage from '../FaceDashboardPage.vue'
import type { DashboardStats } from '@/features/dashboard/types'

// Mock vue-router
const mockRouter = {
  push: vi.fn(),
}
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
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

vi.mock('@/features/dashboard', () => ({
  useDashboardStats: () => ({
    stats: mockStats,
    isLoading: mockIsStatsLoading,
    error: mockStatsError,
    fetchStats: mockFetchStats,
    retry: mockRetryStats,
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

  describe('section header', () => {
    it('displays "Mes candidatures" section title', async () => {
      const wrapper = mount(FaceDashboardPage)
      await flushPromises()

      expect(wrapper.text()).toContain('Mes candidatures')
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
})
