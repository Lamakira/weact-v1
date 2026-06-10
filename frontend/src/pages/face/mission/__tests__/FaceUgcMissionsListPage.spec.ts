import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceUgcMissionsListPage from '../FaceUgcMissionsListPage.vue'
import type { Mission, UgcMissionTeaser, UgcPaywallMeta } from '@/features/mission/types'

// Mock vue-router
const mockRouter = { push: vi.fn() }
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

// Mock the composable with reactive refs
const mockItems = ref<(Mission | UgcMissionTeaser)[]>([])
const mockCanAccessUgc = ref(false)
const mockPaywall = ref<UgcPaywallMeta | null>(null)
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockIsEmpty = ref(false)
const mockCurrentPage = ref(1)
const mockLastPage = ref(1)
const mockTotalCount = ref(0)
const mockHasNextPage = ref(false)
const mockHasPrevPage = ref(false)
const mockFetchMissions = vi.fn()
const mockNextPage = vi.fn()
const mockPrevPage = vi.fn()
const mockRefreshMissions = vi.fn()

vi.mock('@/features/mission/composables', () => ({
  useUgcMissionDiscovery: () => ({
    items: mockItems,
    canAccessUgc: mockCanAccessUgc,
    paywall: mockPaywall,
    isLoading: mockIsLoading,
    error: mockError,
    isEmpty: mockIsEmpty,
    currentPage: mockCurrentPage,
    lastPage: mockLastPage,
    totalCount: mockTotalCount,
    hasNextPage: mockHasNextPage,
    hasPrevPage: mockHasPrevPage,
    fetchMissions: mockFetchMissions,
    nextPage: mockNextPage,
    prevPage: mockPrevPage,
    refreshMissions: mockRefreshMissions,
  }),
}))

const PAYWALL: UgcPaywallMeta = {
  code: 'UGC_SUBSCRIPTION_REQUIRED',
  message: "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus).",
  pricing_url: '/pricing',
}

function createEligibleMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'uuid-1',
    titre: 'Test sneakers running · 2 vidéos',
    description: 'Brief complet',
    date_tournage: '2026-07-01',
    profil_recherche: 'Créatrices',
    budget: 0,
    date_limite_candidature: '2026-06-24T00:00:00Z',
    nombre_faces_voulu: 3,
    type_mission: 'autre',
    type_mission_label: 'UGC',
    type_mission_autre: null,
    type_compensation: 'hybrid',
    type_compensation_label: 'Produit + Argent',
    nom_produit: 'Sneakers Shade Fit',
    valeur_produit: 35000,
    nombre_videos: 2,
    montant_remuneration: 10000,
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Cotonou',
    duree: 'Livrables vidéo',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    has_paid_payment: false,
    created_at: '2026-06-09T00:00:00Z',
    updated_at: '2026-06-09T00:00:00Z',
    ...overrides,
  } as Mission
}

function createTeaser(overrides: Partial<UgcMissionTeaser> = {}): UgcMissionTeaser {
  return {
    id: 'uuid-1',
    titre: 'Test sneakers running · 2 vidéos',
    type_compensation: 'product',
    type_compensation_label: 'Produit seul',
    nom_produit: 'Sneakers Shade Fit',
    valeur_produit: 35000,
    nombre_videos: 2,
    lieu: 'Cotonou',
    date_limite_candidature: '2026-06-24T00:00:00Z',
    created_at: '2026-06-09T00:00:00Z',
    ...overrides,
  }
}

function mountPage() {
  return mount(FaceUgcMissionsListPage)
}

describe('FaceUgcMissionsListPage', () => {
  beforeEach(() => {
    mockItems.value = []
    mockCanAccessUgc.value = false
    mockPaywall.value = null
    mockIsLoading.value = false
    mockError.value = null
    mockIsEmpty.value = false
    mockCurrentPage.value = 1
    mockLastPage.value = 1
    mockTotalCount.value = 0
    mockHasNextPage.value = false
    mockHasPrevPage.value = false
    vi.clearAllMocks()
  })

  it('fetches page 1 on mount', async () => {
    mountPage()
    await flushPromises()

    expect(mockFetchMissions).toHaveBeenCalledWith(1)
  })

  it('eligible branch: no paywall banner, unlocked cards, click navigates to detail', async () => {
    mockItems.value = [createEligibleMission()]
    mockCanAccessUgc.value = true
    mockTotalCount.value = 1

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-paywall-banner"]').exists()).toBe(false)
    const card = wrapper.getComponent({ name: 'UgcMissionCard' })
    expect(card.props('locked')).toBe(false)

    await card.get('[data-testid="ugc-mission-card"]').trigger('click')
    expect(mockRouter.push).toHaveBeenCalledWith({
      name: 'face-mission-detail',
      params: { id: 'uuid-1' },
    })
  })

  it('free branch: paywall banner with backend message, locked cards, click goes to /pricing', async () => {
    mockItems.value = [createTeaser()]
    mockCanAccessUgc.value = false
    mockPaywall.value = PAYWALL
    mockTotalCount.value = 1

    const wrapper = mountPage()
    await flushPromises()

    const banner = wrapper.get('[data-testid="ugc-paywall-banner"]')
    expect(banner.text()).toContain(PAYWALL.message)

    const card = wrapper.getComponent({ name: 'UgcMissionCard' })
    expect(card.props('locked')).toBe(true)

    await card.get('[data-testid="ugc-mission-card"]').trigger('click')
    expect(mockRouter.push).toHaveBeenCalledWith('/pricing')
  })

  it('free branch: a custom pricing_url is respected on card click', async () => {
    mockItems.value = [createTeaser()]
    mockCanAccessUgc.value = false
    mockPaywall.value = { ...PAYWALL, pricing_url: '/abonnements' }

    const wrapper = mountPage()
    await flushPromises()

    await wrapper.get('[data-testid="ugc-mission-card"]').trigger('click')
    expect(mockRouter.push).toHaveBeenCalledWith('/abonnements')
  })

  it('renders loading skeletons while fetching', async () => {
    mockIsLoading.value = true

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.findAll('[data-testid="ugc-skeleton-card"]')).toHaveLength(6)
    expect(wrapper.find('[data-testid="ugc-paywall-banner"]').exists()).toBe(false)
  })

  it('renders error state and retry button refetches', async () => {
    mockError.value = 'Erreur réseau'

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.text()).toContain('Erreur réseau')
    const retryButton = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Réessayer'))
    expect(retryButton).toBeDefined()

    await retryButton!.trigger('click')
    expect(mockRefreshMissions).toHaveBeenCalled()
  })

  it('does not render the paywall banner on top of the error state', async () => {
    mockError.value = 'Erreur réseau'
    mockCanAccessUgc.value = false
    mockPaywall.value = PAYWALL

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.text()).toContain('Erreur réseau')
    expect(wrapper.find('[data-testid="ugc-paywall-banner"]').exists()).toBe(false)
  })

  it('keeps the paywall banner visible during a refetch (no layout jump)', async () => {
    mockItems.value = [createTeaser()]
    mockCanAccessUgc.value = false
    mockPaywall.value = PAYWALL
    mockIsLoading.value = true

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-paywall-banner"]').exists()).toBe(true)
  })

  it('renders the empty state', async () => {
    mockIsEmpty.value = true

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.text()).toContain('Aucune mission UGC disponible')
  })

  it('renders pagination when last_page > 1', async () => {
    mockItems.value = [createEligibleMission()]
    mockCanAccessUgc.value = true
    mockCurrentPage.value = 1
    mockLastPage.value = 3
    mockHasNextPage.value = true
    mockTotalCount.value = 30

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.text()).toContain('Page 1 sur 3')

    const nextButton = wrapper.findAll('button').find((b) => b.text().includes('Suivant'))
    await nextButton!.trigger('click')
    expect(mockNextPage).toHaveBeenCalled()
  })
})
