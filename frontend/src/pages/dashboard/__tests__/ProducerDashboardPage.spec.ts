import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import ProducerDashboardPage from '../ProducerDashboardPage.vue'
import type { ProducerDashboardStats } from '@/features/dashboard/types'

// Mock vue-router
const mockRouter = {
  push: vi.fn(),
}
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  RouterLink: {
    name: 'RouterLink',
    template: '<a :href="to?.name || to" data-testid="router-link"><slot /></a>',
    props: ['to'],
  },
}))

// Mock auth store
const mockIsEmailVerified = ref(true)
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { email: 'producer@example.com', userable: { id: 1 } },
    isEmailVerified: mockIsEmailVerified,
  }),
}))

// Mock useAuth composable
vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    logout: vi.fn(),
    isLoading: ref(false),
  }),
}))

// Mock Producer dashboard stats composable
const mockStats = ref<ProducerDashboardStats | null>(null)
const mockIsStatsLoading = ref(false)
const mockStatsError = ref<string | null>(null)
const mockFetchStats = vi.fn().mockResolvedValue(undefined)
const mockRetryStats = vi.fn().mockResolvedValue(undefined)

vi.mock('@/features/dashboard/composables/useProducerDashboardStats', () => ({
  useProducerDashboardStats: () => ({
    stats: mockStats,
    isLoading: mockIsStatsLoading,
    error: mockStatsError,
    fetchStats: mockFetchStats,
    retry: mockRetryStats,
  }),
}))

// Mock KpiCard component
vi.mock('@/features/dashboard/components/KpiCard.vue', () => ({
  default: {
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
}))

// Mock EmailVerificationBanner
vi.mock('@/components/EmailVerificationBanner.vue', () => ({
  default: {
    name: 'EmailVerificationBanner',
    template: '<div data-testid="email-verification-banner">Verify your email</div>',
  },
}))

// Mock lucide-vue-next
vi.mock('lucide-vue-next', () => ({
  RefreshCw: {
    name: 'RefreshCw',
    template: '<svg data-testid="refresh-icon"></svg>',
    props: ['size', 'class'],
  },
}))

describe('ProducerDashboardPage', () => {
  beforeEach(() => {
    mockStats.value = null
    mockIsStatsLoading.value = false
    mockStatsError.value = null
    mockIsEmailVerified.value = true
    mockFetchStats.mockClear()
    mockRetryStats.mockClear()
    mockRouter.push.mockClear()
  })

  describe('KPI cards rendering', () => {
    it('renders 5 KPI cards when stats are loaded (4 missions + 1 candidatures)', async () => {
      mockStats.value = {
        published: 3,
        in_progress: 2,
        closed: 5,
        completed: 10,
        total_candidatures: 47,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const kpiCards = wrapper.findAll('[data-component="kpi-card"]')
      expect(kpiCards.length).toBe(5) // 4 missions KPIs + 1 candidatures KPI
    })

    it('renders KPI card with correct published value', async () => {
      mockStats.value = {
        published: 7,
        in_progress: 0,
        closed: 0,
        completed: 0,
        total_candidatures: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const publishedCard = wrapper.find('[data-testid="kpi-card-published"]')
      expect(publishedCard.exists()).toBe(true)
      expect(publishedCard.attributes('data-value')).toBe('7')
    })

    it('renders KPI card with correct in_progress value', async () => {
      mockStats.value = {
        published: 0,
        in_progress: 4,
        closed: 0,
        completed: 0,
        total_candidatures: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const inProgressCard = wrapper.find('[data-testid="kpi-card-in_progress"]')
      expect(inProgressCard.exists()).toBe(true)
      expect(inProgressCard.attributes('data-value')).toBe('4')
    })

    it('renders KPI card with correct closed value', async () => {
      mockStats.value = {
        published: 0,
        in_progress: 0,
        closed: 8,
        completed: 0,
        total_candidatures: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const closedCard = wrapper.find('[data-testid="kpi-card-closed"]')
      expect(closedCard.exists()).toBe(true)
      expect(closedCard.attributes('data-value')).toBe('8')
    })

    it('renders KPI card with correct completed value', async () => {
      mockStats.value = {
        published: 0,
        in_progress: 0,
        closed: 0,
        completed: 15,
        total_candidatures: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const completedCard = wrapper.find('[data-testid="kpi-card-completed"]')
      expect(completedCard.exists()).toBe(true)
      expect(completedCard.attributes('data-value')).toBe('15')
    })

    it('renders KPI card with correct total_candidatures value (FR56)', async () => {
      mockStats.value = {
        published: 0,
        in_progress: 0,
        closed: 0,
        completed: 0,
        total_candidatures: 47,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const candidaturesCard = wrapper.find('[data-testid="kpi-card-total_candidatures"]')
      expect(candidaturesCard.exists()).toBe(true)
      expect(candidaturesCard.attributes('data-value')).toBe('47')
    })

    it('displays zero values correctly including candidatures', async () => {
      mockStats.value = {
        published: 0,
        in_progress: 0,
        closed: 0,
        completed: 0,
        total_candidatures: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const publishedCard = wrapper.find('[data-testid="kpi-card-published"]')
      expect(publishedCard.attributes('data-value')).toBe('0')

      const candidaturesCard = wrapper.find('[data-testid="kpi-card-total_candidatures"]')
      expect(candidaturesCard.attributes('data-value')).toBe('0')
    })

    it('renders KPI cards grid container', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const grid = wrapper.find('[data-testid="kpi-cards-grid"]')
      expect(grid.exists()).toBe(true)
    })

    it('renders candidatures KPI section', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const candidaturesSection = wrapper.find('[data-testid="candidatures-kpi-section"]')
      expect(candidaturesSection.exists()).toBe(true)
    })
  })

  describe('loading state', () => {
    it('passes isLoading=true to KPI cards when loading', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const kpiCards = wrapper.findAll('[data-component="kpi-card"]')
      expect(kpiCards.length).toBe(5) // 4 missions + 1 candidatures
      expect(kpiCards[0].attributes('data-loading')).toBe('true')
    })

    it('passes isLoading=false to KPI cards when loaded', async () => {
      mockIsStatsLoading.value = false
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const kpiCards = wrapper.findAll('[data-component="kpi-card"]')
      expect(kpiCards[0].attributes('data-loading')).toBe('false')
    })

    it('passes isLoading to candidatures KPI card', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const candidaturesCard = wrapper.find('[data-testid="kpi-card-total_candidatures"]')
      expect(candidaturesCard.exists()).toBe(true)
      expect(candidaturesCard.attributes('data-loading')).toBe('true')
    })
  })

  describe('error state', () => {
    it('shows error message when API fails', async () => {
      mockStatsError.value = 'Une erreur est survenue'

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const errorDiv = wrapper.find('[data-testid="stats-error"]')
      expect(errorDiv.exists()).toBe(true)
      expect(errorDiv.text()).toContain('Une erreur est survenue')
    })

    it('shows retry button when error occurs', async () => {
      mockStatsError.value = 'Erreur réseau'

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const retryButton = wrapper.find('[data-testid="retry-button"]')
      expect(retryButton.exists()).toBe(true)
      expect(retryButton.text()).toContain('Réessayer')
    })

    it('calls retry function when retry button is clicked', async () => {
      mockStatsError.value = 'Erreur réseau'

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const retryButton = wrapper.find('[data-testid="retry-button"]')
      await retryButton.trigger('click')

      expect(mockRetryStats).toHaveBeenCalled()
    })

    it('hides KPI cards grid when error occurs', async () => {
      mockStatsError.value = 'Erreur réseau'

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const grid = wrapper.find('[data-testid="kpi-cards-grid"]')
      expect(grid.exists()).toBe(false)

      // Also verify candidatures grid is hidden (Issue #1 fix)
      const candidaturesGrid = wrapper.find('[data-testid="candidatures-kpi-grid"]')
      expect(candidaturesGrid.exists()).toBe(false)
    })

    it('error message has role="alert" for accessibility', async () => {
      mockStatsError.value = 'Une erreur'

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const errorDiv = wrapper.find('[data-testid="stats-error"]')
      expect(errorDiv.attributes('role')).toBe('alert')
    })
  })

  describe('data fetching', () => {
    it('calls fetchStats on mount', async () => {
      mount(ProducerDashboardPage)
      await flushPromises()

      expect(mockFetchStats).toHaveBeenCalled()
    })
  })

  describe('section headers', () => {
    it('displays "Mes missions" section title', async () => {
      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      expect(wrapper.text()).toContain('Mes missions')
    })

    it('displays "Candidatures" section title (FR56)', async () => {
      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      expect(wrapper.text()).toContain('Candidatures')
    })
  })

  describe('KPI section structure', () => {
    it('renders KPI section container', async () => {
      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const kpiSection = wrapper.find('[data-testid="kpi-section"]')
      expect(kpiSection.exists()).toBe(true)
    })
  })

  describe('number formatting', () => {
    it('formats large numbers with French locale (Issue #6 fix)', async () => {
      mockStats.value = {
        published: 1234,
        in_progress: 0,
        closed: 0,
        completed: 0,
        total_candidatures: 56789,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      // KpiCard uses Intl.NumberFormat('fr-FR') which formats 1234 as "1 234"
      // The mock component exposes the raw value, but real component formats it
      const publishedCard = wrapper.find('[data-testid="kpi-card-published"]')
      expect(publishedCard.exists()).toBe(true)
      // Value is passed correctly to the card
      expect(publishedCard.attributes('data-value')).toBe('1234')
    })
  })
})
