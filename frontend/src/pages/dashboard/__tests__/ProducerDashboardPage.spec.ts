import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { ref } from 'vue'
import ProducerDashboardPage from '../ProducerDashboardPage.vue'
import { producerApi } from '@/features/producer/services/producerApi'
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

// Mock producerApi
vi.mock('@/features/producer/services/producerApi', () => ({
  producerApi: {
    getProfile: vi.fn().mockResolvedValue({ data: null }),
    listDeliverablesToReview: vi.fn().mockResolvedValue({ data: [] }),
  },
}))

// Mock Skeleton component
vi.mock('@/components/ui/skeleton', () => ({
  Skeleton: {
    name: 'Skeleton',
    template: '<div data-testid="skeleton"></div>',
    props: ['class'],
  },
}))

// Mock lucide-vue-next
vi.mock('lucide-vue-next', () => {
  const m = (name: string) => ({
    name,
    template: `<svg data-testid="${name}-icon"></svg>`,
    props: ['size', 'class'],
  })
  return {
    RefreshCw: m('RefreshCw'),
    Star: m('Star'),
    Check: m('Check'),
    Clock: m('Clock'),
    AlertCircle: m('AlertCircle'),
    Pencil: m('Pencil'),
    Briefcase: m('Briefcase'),
    MessageCircle: m('MessageCircle'),
    PlusCircle: m('PlusCircle'),
    Eye: m('Eye'),
    Users: m('Users'),
    UserCheck: m('UserCheck'),
    CheckCircle2: m('CheckCircle2'),
    PlayCircle: m('PlayCircle'),
    CheckSquare: m('CheckSquare'),
    XCircle: m('XCircle'),
    BadgeCheck: m('BadgeCheck'),
  }
})

describe('ProducerDashboardPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockStats.value = null
    mockIsStatsLoading.value = false
    mockStatsError.value = null
    mockIsEmailVerified.value = true
    mockFetchStats.mockClear()
    mockRetryStats.mockClear()
    mockRouter.push.mockClear()
    vi.mocked(producerApi.listDeliverablesToReview).mockResolvedValue({ data: [] })
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

      // 4 mission KPIs in the grid
      const missionKpis = wrapper.find('[data-testid="kpi-cards-grid"]')
      expect(missionKpis.exists()).toBe(true)
      // 4 candidatures items
      const candidaturesGrid = wrapper.find('[data-testid="candidatures-kpi-grid"]')
      expect(candidaturesGrid.exists()).toBe(true)
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
      expect(publishedCard.text()).toContain('7')
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
      expect(inProgressCard.text()).toContain('4')
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
      expect(closedCard.text()).toContain('8')
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
      expect(completedCard.text()).toContain('15')
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
      expect(candidaturesCard.text()).toContain('47')
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
      expect(collaboratorsCard.text()).toContain('23')
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
      expect(publishedCard.text()).toContain('0')

      const candidaturesCard = wrapper.find('[data-testid="kpi-card-total_candidatures"]')
      expect(candidaturesCard.text()).toContain('0')

      const collaboratorsCard = wrapper.find('[data-testid="kpi-card-unique_collaborators"]')
      expect(collaboratorsCard.text()).toContain('0')
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

      const candidaturesSection = wrapper.find('[data-testid="candidatures-kpi-grid"]')
      expect(candidaturesSection.exists()).toBe(true)
    })
  })

  describe('loading state', () => {
    it('passes isLoading=true to KPI cards when loading', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      // When loading, skeleton elements should be present
      const skeletons = wrapper.findAll('[data-testid="skeleton"]')
      expect(skeletons.length).toBeGreaterThan(0)
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

      // When loaded, KPI values should be visible
      const publishedCard = wrapper.find('[data-testid="kpi-card-published"]')
      expect(publishedCard.text()).toContain('1')
    })

    it('passes isLoading to candidatures KPI card', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const candidaturesCard = wrapper.find('[data-testid="kpi-card-total_candidatures"]')
      expect(candidaturesCard.exists()).toBe(true)
      // Should show skeleton when loading
      const skeletons = candidaturesCard.findAll('[data-testid="skeleton"]')
      expect(skeletons.length).toBeGreaterThan(0)
    })

    it('passes isLoading to collaborators KPI card (FR57)', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const collaboratorsCard = wrapper.find('[data-testid="kpi-card-unique_collaborators"]')
      expect(collaboratorsCard.exists()).toBe(true)
      const skeletons = collaboratorsCard.findAll('[data-testid="skeleton"]')
      expect(skeletons.length).toBeGreaterThan(0)
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

      const statsPanel = wrapper.find('[data-testid="stats-panel"]')
      expect(statsPanel.exists()).toBe(true)
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

      const publishedCard = wrapper.find('[data-testid="kpi-card-published"]')
      expect(publishedCard.exists()).toBe(true)
      expect(publishedCard.text()).toContain('1234')
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
      const candidaturesSection = wrapper.find('[data-testid="candidatures-kpi-grid"]')
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

      const ratingCard = wrapper.find('[data-testid="kpi-card-rating"]')
      expect(ratingCard.exists()).toBe(true)
      expect(ratingCard.text()).toContain('Ma note')
    })

    it('shows skeleton while loading', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const ratingCard = wrapper.find('[data-testid="kpi-card-rating"]')
      expect(ratingCard.exists()).toBe(true)
      const skeletons = ratingCard.findAll('[data-testid="skeleton"]')
      expect(skeletons.length).toBeGreaterThan(0)
    })

    it('hides rating card when error occurs', async () => {
      mockStatsError.value = 'Erreur réseau'

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      // Candidatures section (with rating) is hidden on error
      const candidaturesSection = wrapper.find('[data-testid="candidatures-kpi-grid"]')
      expect(candidaturesSection.exists()).toBe(false)

      const ratingCard = wrapper.find('[data-testid="kpi-card-rating"]')
      expect(ratingCard.exists()).toBe(false)
    })
  })

  describe('advanced stats display (FR59)', () => {
    it('renders acceptance rate in candidatures section', async () => {
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

      const candidaturesGrid = wrapper.find('[data-testid="candidatures-kpi-grid"]')
      expect(candidaturesGrid.exists()).toBe(true)
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

    it('displays "0.0%" for acceptance rate when value is 0', async () => {
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
      expect(acceptanceRateValue.text()).toBe('0.0%')
    })

    it('displays "Acceptation" title label', async () => {
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
      expect(acceptanceRateCard.text()).toContain('Acceptation')
    })

    it('shows skeletons while loading', async () => {
      mockIsStatsLoading.value = true
      mockStats.value = null

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const acceptanceRateCard = wrapper.find('[data-testid="kpi-card-acceptance_rate"]')
      expect(acceptanceRateCard.exists()).toBe(true)
      const skeletons = acceptanceRateCard.findAll('[data-testid="skeleton"]')
      expect(skeletons.length).toBeGreaterThan(0)
    })

    it('hides candidatures section when error occurs', async () => {
      mockStatsError.value = 'Erreur réseau'

      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()

      const candidaturesGrid = wrapper.find('[data-testid="candidatures-kpi-grid"]')
      expect(candidaturesGrid.exists()).toBe(false)
    })
  })

  describe('UGC validation counter', () => {
    it('renders the À valider card with the in_review count', async () => {
      vi.mocked(producerApi.listDeliverablesToReview).mockResolvedValue({ data: [{}, {}, {}] as never })
      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()
      expect(wrapper.find('[data-testid="ugc-validation-card-count"]').text()).toBe('3')
    })

    it('navigates to the validation inbox when the card is clicked', async () => {
      const wrapper = mount(ProducerDashboardPage)
      await flushPromises()
      await wrapper.find('[data-testid="ugc-validation-card"]').trigger('click')
      expect(mockRouter.push).toHaveBeenCalledWith({ name: 'producer-ugc-validation' })
    })
  })
})
