import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceDashboardPage from '../FaceDashboardPage.vue'
import type {
  BookingMonthlyStats,
  BookingStats,
  DashboardStats,
  FaceTodoItem,
  MonthlyStats,
} from '@/features/dashboard/types'
import type { SubscriptionCurrent } from '@/features/face/types'

const mockRouter = {
  push: vi.fn(),
}

vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  RouterLink: {
    template:
      '<a v-bind="$attrs" :href="typeof to === \'string\' ? to : to.name"><slot /></a>',
    props: ['to'],
    inheritAttrs: false,
  },
}))

const mockFetchCompletion = vi.fn().mockResolvedValue(undefined)
const mockPercentage = ref(75)
const mockMissingItems = ref([
  { key: 'bio', label: 'Ajoutez une bio' },
  { key: 'ville', label: 'Ajoutez votre ville' },
])
vi.mock('@/features/face/composables/useProfileCompletion', () => ({
  useProfileCompletion: () => ({
    isLoading: ref(false),
    percentage: mockPercentage,
    missingItems: mockMissingItems,
    fetchCompletion: mockFetchCompletion,
  }),
}))

const mockStats = ref<DashboardStats | null>(null)
const mockIsStatsLoading = ref(false)
const mockStatsError = ref<string | null>(null)
const mockFetchStats = vi.fn().mockResolvedValue(undefined)
const mockRetryStats = vi.fn().mockResolvedValue(undefined)

const mockCandidaturesByMonth = ref<MonthlyStats[]>([])
const mockFetchChartStats = vi.fn().mockResolvedValue(undefined)

const mockBookingStats = ref<BookingStats | null>(null)
const mockIsBookingStatsLoading = ref(false)
const mockFetchBookingStats = vi.fn().mockResolvedValue(undefined)

const mockBookingsByMonth = ref<BookingMonthlyStats[]>([])
const mockFetchBookingChartStats = vi.fn().mockResolvedValue(undefined)

const mockTodoItems = ref<FaceTodoItem[]>([])
const mockTodoLoading = ref(false)
const mockTodoError = ref<string | null>(null)
const mockFetchTodo = vi.fn().mockResolvedValue(undefined)
const mockRetryTodo = vi.fn().mockResolvedValue(undefined)

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
    fetchChartStats: mockFetchChartStats,
  }),
  useBookingStats: () => ({
    bookingStats: mockBookingStats,
    isLoading: mockIsBookingStatsLoading,
    fetchBookingStats: mockFetchBookingStats,
  }),
  useDashboardBookingCharts: () => ({
    bookingsByMonth: mockBookingsByMonth,
    fetchBookingChartStats: mockFetchBookingChartStats,
  }),
  useFaceDashboardTodo: () => ({
    items: mockTodoItems,
    isLoading: mockTodoLoading,
    error: mockTodoError,
    fetchTodo: mockFetchTodo,
    retry: mockRetryTodo,
  }),
}))

const mockWalletBalance = ref(185000)
const mockPendingEscrow = ref(67500)
const mockWithdrawals = ref<Array<{ status: string; processed_at: string | null }>>([])
const mockFetchWallet = vi.fn().mockResolvedValue(undefined)
vi.mock('@/features/wallet', () => ({
  useWallet: () => ({
    balance: mockWalletBalance,
    pendingEscrow: mockPendingEscrow,
    withdrawalRequests: mockWithdrawals,
    isLoading: ref(false),
    fetchWallet: mockFetchWallet,
  }),
}))

const mockSubscriptionCurrent = ref<SubscriptionCurrent | null>(null)
const mockFetchStatus = vi.fn().mockResolvedValue(undefined)
vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ({
    current: mockSubscriptionCurrent,
    isLoading: ref(false),
    error: ref(null),
    fetchStatus: mockFetchStatus,
    refreshStatus: vi.fn().mockResolvedValue(undefined),
  }),
}))

vi.mock('@/features/face/services/faceApi', () => ({
  faceApi: {
    getProfile: vi.fn().mockResolvedValue({
      data: {
        prenom: 'Ada',
        nom: 'Dossou',
        username: 'ada-dossou',
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

function month(m: string, v: Partial<MonthlyStats>): MonthlyStats {
  return { month: m, pending: 0, accepted: 0, confirmed: 0, in_progress: 0, completed: 0, rejected: 0, ...v }
}

function mountPage() {
  return mount(FaceDashboardPage)
}

describe('FaceDashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRouter.push.mockReset()
    mockStats.value = { pending: 3, accepted: 2, in_progress: 5, completed: 10 }
    mockBookingStats.value = { pending: 1, accepted: 2, in_progress: 3, completed: 4 }
    mockCandidaturesByMonth.value = [
      month('2026-09', { pending: 1, accepted: 1, completed: 2 }),
      month('2026-10', { pending: 3, accepted: 3, completed: 2 }),
    ]
    mockBookingsByMonth.value = []
    mockIsStatsLoading.value = false
    mockStatsError.value = null
    mockIsBookingStatsLoading.value = false
    mockTodoItems.value = []
    mockTodoLoading.value = false
    mockTodoError.value = null
    mockWalletBalance.value = 185000
    mockPendingEscrow.value = 67500
    mockWithdrawals.value = []
    mockPercentage.value = 75
    mockSubscriptionCurrent.value = null
  })

  describe('en-tête', () => {
    it('affiche nom, catégories, ville et le badge du plan', async () => {
      mockSubscriptionCurrent.value = {
        tier: 'pro',
        plan: 'pro',
        status: 'active',
        starts_at: null,
        expires_at: null,
        cancelled_at: null,
        capabilities: {} as SubscriptionCurrent['capabilities'],
      }
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="profile-name"]').text()).toBe('Ada Dossou')
      expect(wrapper.find('[data-testid="profile-category"]').text()).toBe('Actrice · Publicité')
      expect(wrapper.find('[data-testid="profile-city"]').text()).toBe('Cotonou')
      expect(wrapper.find('[data-testid="profile-plan-badge"]').text()).toBe('Pro')
    })

    it('le badge caméra et « Modifier le profil » ouvrent la fiche (flux photo existant)', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="profile-photo-edit"]').trigger('click')
      await wrapper.find('[data-testid="profile-edit-button"]').trigger('click')

      expect(mockRouter.push).toHaveBeenCalledTimes(2)
      expect(mockRouter.push).toHaveBeenNthCalledWith(1, { name: 'face-profile' })
      expect(mockRouter.push).toHaveBeenNthCalledWith(2, { name: 'face-profile' })
      expect(wrapper.find('[data-testid="profile-edit-button"]').text()).toBe('Modifier le profil')
    })

    it('« Fiche publique » est masquée sous md (une seule action visible sur mobile)', async () => {
      const wrapper = mountPage()
      await flushPromises()

      const link = wrapper.find('[data-testid="public-profile-link"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toContain('Fiche publique')
      expect(link.classes()).toContain('hidden')
      expect(link.classes()).toContain('md:inline-flex')
      expect(wrapper.find('[data-testid="profile-edit-button"]').classes()).not.toContain('hidden')
    })
  })

  describe('À faire', () => {
    it('affiche les items renvoyés avec la partie urgente et route par url', async () => {
      mockTodoItems.value = [
        {
          type: 'booking_proposal',
          title: 'Répondre au booking de Studio Awalé',
          meta: 'Publicité · 14 oct.',
          urgent_meta: 'expire dans 22 h',
          action_label: 'Répondre',
          url: '/face/bookings/abc',
        },
        {
          type: 'profile_completion',
          title: 'Compléter le profil',
          meta: 'Profil à 80 % · 2 éléments manquants',
          urgent_meta: null,
          action_label: 'Compléter',
          url: '/face/profile',
        },
      ]
      const wrapper = mountPage()
      await flushPromises()

      const items = wrapper.findAll('[data-testid="r-todo-item"]')
      expect(items).toHaveLength(2)
      expect(items[0]!.text()).toContain('Répondre au booking de Studio Awalé')
      expect(items[0]!.find('[data-testid="r-todo-urgent"]').text()).toBe('expire dans 22 h')
      expect(items[1]!.find('[data-testid="r-todo-urgent"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="r-todo-count"]').text()).toContain('2')

      await items[0]!.find('[data-testid="r-todo-action"]').trigger('click')
      expect(mockRouter.push).toHaveBeenCalledWith('/face/bookings/abc')
    })

    it('affiche l’état vide', async () => {
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="r-todo-empty"]').exists()).toBe(true)
    })

    it('affiche l’erreur et relance le chargement', async () => {
      mockTodoError.value = 'Impossible de charger vos tâches.'
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="face-todo-error"]').text()).toContain('Impossible de charger vos tâches.')
      await wrapper.find('[data-testid="face-todo-retry"]').trigger('click')
      expect(mockRetryTodo).toHaveBeenCalled()
    })
  })

  describe('matrice d’activité', () => {
    it('remplace les tuiles et les graphiques : chiffres candidatures et bookings', async () => {
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="kpi-card-pending"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="activity-chart"]').exists()).toBe(false)

      const table = wrapper.find('[data-testid="r-kpi-table"]')
      const cand = table.find('[data-row="candidatures"]')
      expect(cand.find('[data-cell="pending"]').text()).toContain('3')
      expect(cand.find('[data-cell="accepted"]').text()).toContain('2')
      expect(cand.find('[data-cell="in_progress"]').text()).toContain('5')
      expect(cand.find('[data-cell="completed"]').text()).toContain('10')
      const book = table.find('[data-row="bookings"]')
      expect(book.find('[data-cell="pending"]').text()).toContain('1')
      expect(book.find('[data-cell="completed"]').text()).toContain('4')
    })

    it('affiche le delta mois en cours vs mois dernier et la tendance', async () => {
      const wrapper = mountPage()
      await flushPromises()

      const cand = wrapper.find('[data-testid="r-kpi-table"] [data-row="candidatures"]')
      expect(cand.find('[data-cell="pending"]').text()).toContain('+2 vs mois dernier')
      expect(cand.find('[data-cell="completed"]').text()).toContain('stable vs mois dernier')
      expect(cand.find('[data-testid="r-mini-bars"]').exists()).toBe(true)
    })

    it('propose un contrôle segmenté Candidatures | Bookings sur mobile', async () => {
      const wrapper = mountPage()
      await flushPromises()

      const list = wrapper.find('[data-testid="r-kpi-list"]')
      const radios = list.findAll('[role="radio"]')
      expect(radios.map((r) => r.text())).toEqual(['Candidatures', 'Bookings'])
      expect(list.findAll('li')[0]!.text()).toContain('3')

      await radios[1]!.trigger('click')
      expect(list.findAll('li')[0]!.text()).toContain('1')
    })

    it('affiche l’erreur des stats et relance', async () => {
      mockStatsError.value = 'Une erreur est survenue'
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="stats-error"]').text()).toContain('Une erreur est survenue')
      await wrapper.find('[data-testid="retry-stats-button"]').trigger('click')
      expect(mockRetryStats).toHaveBeenCalled()
    })
  })

  describe('rangée du bas', () => {
    it('Portefeuille : solde, séquestre, dernier virement et lien Retirer', async () => {
      mockWithdrawals.value = [
        { status: 'approved', processed_at: '2026-09-28T10:00:00Z' },
        { status: 'pending', processed_at: null },
        { status: 'rejected', processed_at: '2026-10-05T10:00:00Z' },
      ]
      const wrapper = mountPage()
      await flushPromises()

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.find('[data-testid="wallet-balance"]').text()).toContain('185')
      expect(card.find('[data-testid="wallet-escrow"]').text()).toContain('67')
      expect(card.find('[data-testid="wallet-last-withdrawal"]').text()).toContain('28')
      expect(card.find('[data-testid="wallet-withdraw-link"]').attributes('href')).toBe('face-wallet')
    })

    it('Portefeuille : pas de ligne « Dernier virement » sans retrait approuvé', async () => {
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="wallet-last-withdrawal"]').exists()).toBe(false)
    })

    it('Profil : pourcentage, barre et éléments à compléter', async () => {
      const wrapper = mountPage()
      await flushPromises()

      const panel = wrapper.find('[data-testid="profile-completion-panel"]')
      expect(panel.find('[data-testid="profile-completion-percentage"]').text()).toBe('75%')
      expect(panel.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('75')
      expect(panel.findAll('[data-testid="profile-completion-item"]').map((i) => i.text())).toEqual([
        'Ajoutez une bio',
        'Ajoutez votre ville',
      ])
    })

    it('Abonnement : panneau du plan avec « Gérer mon plan » et « Comparer les plans »', async () => {
      const wrapper = mountPage()
      await flushPromises()

      const plan = wrapper.find('[data-testid="current-plan-card"]')
      expect(plan.exists()).toBe(true)
      expect(plan.find('[data-testid="plan-billing-cta"]').attributes('href')).toBe('face-billing')
      expect(plan.find('[data-testid="plan-compare-link"]').attributes('href')).toBe('pricing')
    })
  })

  it('charge toutes les données au montage', async () => {
    mountPage()
    await flushPromises()

    expect(mockFetchCompletion).toHaveBeenCalled()
    expect(mockFetchStats).toHaveBeenCalled()
    expect(mockFetchChartStats).toHaveBeenCalled()
    expect(mockFetchWallet).toHaveBeenCalled()
    expect(mockFetchBookingStats).toHaveBeenCalled()
    expect(mockFetchBookingChartStats).toHaveBeenCalled()
    expect(mockFetchTodo).toHaveBeenCalled()
  })
})
