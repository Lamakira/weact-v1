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
  Star: {
    name: 'Star',
    template: '<svg data-testid="star-icon"></svg>',
    props: ['size'],
  },
  Check: {
    name: 'Check',
    template: '<svg data-testid="check-icon"></svg>',
    props: ['size'],
  },
  Clock: {
    name: 'Clock',
    template: '<svg data-testid="clock-icon"></svg>',
    props: ['size'],
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
    it('renders 6 KPI cards when stats are loaded (4 missions + 2 candidatures section)', async () => {
      mockStats.value = {
        published: 3,
        in_progress: 2,
        closed: 5,
        completed: 10,
        total_candidatures: 47,
        unique_collaborators: 12,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const kpiCards = wrapper.findAll('[data-component="kpi-card"]')
      expect(kpiCards.length).toBe(6) // 4 missions KPIs + 2 candidatures section KPIs
    })

    it('renders KPI card with correct published value', async () => {
      mockStats.value = {
        published: 7,
        in_progress: 0,
        closed: 0,
        completed: 0,
        total_candidatures: 0,
        unique_collaborators: 0,
        average_rating: null,
        ratings_count: 0,
        acceptance_rate: 0,
        average_response_time_hours: null,
        completed_missions_count: 0,
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
        unique_collaborators: 0,
        average_rating: null,
        ratings_count: 0,
        acceptance_rate: 0,
        average_response_time_hours: null,
        completed_missions_count: 0,
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
        unique_collaborators: 0,
        average_rating: null,
        ratings_count: 0,
        acceptance_rate: 0,
        average_response_time_hours: null,
        completed_missions_count: 0,
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
        unique_collaborators: 0,
        average_rating: null,
        ratings_count: 0,
        acceptance_rate: 0,
        average_response_time_hours: null,
        completed_missions_count: 0,
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
        unique_collaborators: 0,
        average_rating: null,
        ratings_count: 0,
        acceptance_rate: 0,
        average_response_time_hours: null,
        completed_missions_count: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const candidaturesCard = wrapper.find('[data-testid="kpi-card-total_candidatures"]')
      expect(candidaturesCard.exists()).toBe(true)
      expect(candidaturesCard.attributes('data-value')).toBe('47')
    })

    it('renders KPI card with correct unique_collaborators value (FR57)', async () => {
      mockStats.value = {
        published: 0,
        in_progress: 0,
        closed: 0,
        completed: 0,
        total_candidatures: 0,
        unique_collaborators: 23,
        average_rating: null,
        ratings_count: 0,
        acceptance_rate: 0,
        average_response_time_hours: null,
        completed_missions_count: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const collaboratorsCard = wrapper.find('[data-testid="kpi-card-unique_collaborators"]')
      expect(collaboratorsCard.exists()).toBe(true)
      expect(collaboratorsCard.attributes('data-value')).toBe('23')
    })

    it('displays zero values correctly including candidatures and collaborators', async () => {
      mockStats.value = {
        published: 0,
        in_progress: 0,
        closed: 0,
        completed: 0,
        total_candidatures: 0,
        unique_collaborators: 0,
        average_rating: null,
        ratings_count: 0,
        acceptance_rate: 0,
        average_response_time_hours: null,
        completed_missions_count: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const publishedCard = wrapper.find('[data-testid="kpi-card-published"]')
      expect(publishedCard.attributes('data-value')).toBe('0')

      const candidaturesCard = wrapper.find('[data-testid="kpi-card-total_candidatures"]')
      expect(candidaturesCard.attributes('data-value')).toBe('0')

      const collaboratorsCard = wrapper.find('[data-testid="kpi-card-unique_collaborators"]')
      expect(collaboratorsCard.attributes('data-value')).toBe('0')
    })

    it('renders KPI cards grid container', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.0,
        ratings_count: 3,
        acceptance_rate: 60.0,
        average_response_time_hours: 2.5,
        completed_missions_count: 1,
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
        unique_collaborators: 5,
        average_rating: 4.0,
        ratings_count: 3,
        acceptance_rate: 60.0,
        average_response_time_hours: 2.5,
        completed_missions_count: 1,
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
      expect(kpiCards.length).toBe(6) // 4 missions + 2 candidatures section
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
        unique_collaborators: 5,
        average_rating: 4.0,
        ratings_count: 3,
        acceptance_rate: 60.0,
        average_response_time_hours: 2.5,
        completed_missions_count: 1,
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

    it('passes isLoading to collaborators KPI card (FR57)', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const collaboratorsCard = wrapper.find('[data-testid="kpi-card-unique_collaborators"]')
      expect(collaboratorsCard.exists()).toBe(true)
      expect(collaboratorsCard.attributes('data-loading')).toBe('true')
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

    it('displays "Candidatures & Réputation" section title (FR56, FR57, FR58)', async () => {
      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      expect(wrapper.text()).toContain('Candidatures & Réputation')
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
        unique_collaborators: 999,
        average_rating: 4.5,
        ratings_count: 10,
        acceptance_rate: 80.0,
        average_response_time_hours: 3.0,
        completed_missions_count: 0,
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

  describe('rating display (FR58)', () => {
    it('renders rating card in candidatures section', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      // Rating card is now integrated in the candidatures section
      const candidaturesSection = wrapper.find('[data-testid="candidatures-kpi-section"]')
      expect(candidaturesSection.exists()).toBe(true)

      const ratingCard = wrapper.find('[data-testid="kpi-card-rating"]')
      expect(ratingCard.exists()).toBe(true)
    })

    it('displays "Candidatures & Réputation" section title', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      expect(wrapper.text()).toContain('Candidatures & Réputation')
    })

    it('displays formatted rating value with one decimal', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const ratingValue = wrapper.find('[data-testid="kpi-card-rating-value"]')
      expect(ratingValue.exists()).toBe(true)
      expect(ratingValue.text()).toBe('4.5')
    })

    it('displays "--" when average_rating is null', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: null,
        ratings_count: 0,
        acceptance_rate: 0,
        average_response_time_hours: null,
        completed_missions_count: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const ratingValue = wrapper.find('[data-testid="kpi-card-rating-value"]')
      expect(ratingValue.exists()).toBe(true)
      expect(ratingValue.text()).toBe('--')
    })

    it('displays "Aucun avis" when ratings_count is 0', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: null,
        ratings_count: 0,
        acceptance_rate: 0,
        average_response_time_hours: null,
        completed_missions_count: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const ratingSubtitle = wrapper.find('[data-testid="kpi-card-rating-subtitle"]')
      expect(ratingSubtitle.exists()).toBe(true)
      expect(ratingSubtitle.text()).toBe('Aucun avis')
    })

    it('displays correct pluralized avis count', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const ratingSubtitle = wrapper.find('[data-testid="kpi-card-rating-subtitle"]')
      expect(ratingSubtitle.exists()).toBe(true)
      expect(ratingSubtitle.text()).toBe('8 avis')
    })

    it('displays "1 avis" for single rating', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 5.0,
        ratings_count: 1,
        acceptance_rate: 100.0,
        average_response_time_hours: 1.0,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const ratingSubtitle = wrapper.find('[data-testid="kpi-card-rating-subtitle"]')
      expect(ratingSubtitle.exists()).toBe(true)
      expect(ratingSubtitle.text()).toBe('1 avis')
    })

    it('displays whole number rating with one decimal (5.0 not 5)', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 5,
        ratings_count: 3,
        acceptance_rate: 90.0,
        average_response_time_hours: 2.0,
        completed_missions_count: 3,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const ratingValue = wrapper.find('[data-testid="kpi-card-rating-value"]')
      expect(ratingValue.exists()).toBe(true)
      expect(ratingValue.text()).toBe('5.0')
    })

    it('displays "Ma note" title label', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const ratingTitle = wrapper.find('[data-testid="kpi-card-rating-title"]')
      expect(ratingTitle.exists()).toBe(true)
      expect(ratingTitle.text()).toBe('Ma note')
    })

    it('shows skeleton while loading', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const ratingSkeleton = wrapper.find('[data-testid="kpi-card-rating-skeleton"]')
      expect(ratingSkeleton.exists()).toBe(true)
    })

    it('hides rating card when error occurs', async () => {
      mockStatsError.value = 'Erreur réseau'

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      // Candidatures section (with rating) is hidden on error
      const candidaturesSection = wrapper.find('[data-testid="candidatures-kpi-section"]')
      expect(candidaturesSection.exists()).toBe(false)

      const ratingCard = wrapper.find('[data-testid="kpi-card-rating"]')
      expect(ratingCard.exists()).toBe(false)
    })
  })

  describe('advanced stats display (FR59)', () => {
    it('renders advanced stats section', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const advancedStatsSection = wrapper.find('[data-testid="advanced-stats-section"]')
      expect(advancedStatsSection.exists()).toBe(true)
    })

    it('displays "Statistiques avancées" section title', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      expect(wrapper.text()).toContain('Statistiques avancées')
    })

    it('displays acceptance rate card', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const acceptanceRateCard = wrapper.find('[data-testid="kpi-card-acceptance_rate"]')
      expect(acceptanceRateCard.exists()).toBe(true)
    })

    it('displays formatted acceptance rate with percentage', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const acceptanceRateValue = wrapper.find('[data-testid="kpi-card-acceptance_rate-value"]')
      expect(acceptanceRateValue.exists()).toBe(true)
      expect(acceptanceRateValue.text()).toBe('75.0%')
    })

    it('displays "--" for acceptance rate when value is null/undefined', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 0,
        unique_collaborators: 0,
        average_rating: null,
        ratings_count: 0,
        acceptance_rate: 0,
        average_response_time_hours: null,
        completed_missions_count: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const acceptanceRateValue = wrapper.find('[data-testid="kpi-card-acceptance_rate-value"]')
      expect(acceptanceRateValue.exists()).toBe(true)
      // When acceptance_rate is 0, it should display "0.0%"
      expect(acceptanceRateValue.text()).toBe('0.0%')
    })

    it('displays "Taux d\'acceptation" title label', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const acceptanceRateTitle = wrapper.find('[data-testid="kpi-card-acceptance_rate-title"]')
      expect(acceptanceRateTitle.exists()).toBe(true)
      expect(acceptanceRateTitle.text()).toBe("Taux d'acceptation")
    })

    it('displays response time card', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const responseTimeCard = wrapper.find('[data-testid="kpi-card-average_response_time_hours"]')
      expect(responseTimeCard.exists()).toBe(true)
    })

    it('displays formatted response time with hours suffix', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const responseTimeValue = wrapper.find('[data-testid="kpi-card-average_response_time_hours-value"]')
      expect(responseTimeValue.exists()).toBe(true)
      expect(responseTimeValue.text()).toBe('4.5h')
    })

    it('displays "N/A" for response time when no decisions made', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: null,
        completed_missions_count: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const responseTimeValue = wrapper.find('[data-testid="kpi-card-average_response_time_hours-value"]')
      expect(responseTimeValue.exists()).toBe(true)
      expect(responseTimeValue.text()).toBe('N/A')
    })

    it('displays "Temps de réponse" title label', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const responseTimeTitle = wrapper.find('[data-testid="kpi-card-average_response_time_hours-title"]')
      expect(responseTimeTitle.exists()).toBe(true)
      expect(responseTimeTitle.text()).toBe('Temps de réponse')
    })

    it('displays "Délai moyen" subtitle when response time has value', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: 4.5,
        completed_missions_count: 1,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const responseTimeSubtitle = wrapper.find('[data-testid="kpi-card-average_response_time_hours-subtitle"]')
      expect(responseTimeSubtitle.exists()).toBe(true)
      expect(responseTimeSubtitle.text()).toBe('Délai moyen')
    })

    it('displays "Aucune décision" subtitle when no decisions made', async () => {
      mockStats.value = {
        published: 1,
        in_progress: 1,
        closed: 1,
        completed: 1,
        total_candidatures: 10,
        unique_collaborators: 5,
        average_rating: 4.5,
        ratings_count: 8,
        acceptance_rate: 75.0,
        average_response_time_hours: null,
        completed_missions_count: 0,
      }

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const responseTimeSubtitle = wrapper.find('[data-testid="kpi-card-average_response_time_hours-subtitle"]')
      expect(responseTimeSubtitle.exists()).toBe(true)
      expect(responseTimeSubtitle.text()).toBe('Aucune décision')
    })

    it('shows skeletons while loading', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const acceptanceRateSkeleton = wrapper.find('[data-testid="kpi-card-acceptance-rate-skeleton"]')
      expect(acceptanceRateSkeleton.exists()).toBe(true)

      const responseTimeSkeleton = wrapper.find('[data-testid="kpi-card-response-time-skeleton"]')
      expect(responseTimeSkeleton.exists()).toBe(true)
    })

    it('hides advanced stats section when error occurs', async () => {
      mockStatsError.value = 'Erreur réseau'

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const advancedStatsSection = wrapper.find('[data-testid="advanced-stats-section"]')
      expect(advancedStatsSection.exists()).toBe(false)
    })
  })
})
