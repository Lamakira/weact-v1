import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises, RouterLinkStub } from '@vue/test-utils'
import { ref } from 'vue'
import FaceMissionsListPage from '../FaceMissionsListPage.vue'
import { AvailableMissionCard, MissionFiltersPanel } from '@/features/mission/components'
import type { Mission } from '@/features/mission/types'

// Programmatic navigation in the page (mission card click, etc.)
const mockRouter = { push: vi.fn() }
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
}))

// useFaceMissions state
const mockMissions = ref<Mission[]>([])
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockIsEmpty = ref(false)
const mockCurrentPage = ref(1)
const mockLastPage = ref(1)
const mockTotalCount = ref(0)
const mockHasNextPage = ref(false)
const mockHasPrevPage = ref(false)
const mockHasFiltersApplied = ref(false)
const mockFetchMissions = vi.fn()
const mockNextPage = vi.fn()
const mockPrevPage = vi.fn()
const mockRefreshMissions = vi.fn()

// useMissionFilters state
const mockLieu = ref('')
const mockBudgetMin = ref<number | null>(null)
const mockBudgetMax = ref<number | null>(null)
const mockDateTournage = ref('')
const mockTypeMission = ref('')
const mockFilters = ref({})
const mockActiveFilterCount = ref(0)
const mockHasActiveFilters = ref(false)
const mockResetFilters = vi.fn()
const mockSyncToUrl = vi.fn()
const mockInitFromUrl = vi.fn()

// Both composables live in the same module — mock both.
vi.mock('@/features/mission/composables', () => ({
  useFaceMissions: () => ({
    missions: mockMissions,
    isLoading: mockIsLoading,
    error: mockError,
    isEmpty: mockIsEmpty,
    currentPage: mockCurrentPage,
    lastPage: mockLastPage,
    totalCount: mockTotalCount,
    hasNextPage: mockHasNextPage,
    hasPrevPage: mockHasPrevPage,
    hasFiltersApplied: mockHasFiltersApplied,
    fetchMissions: mockFetchMissions,
    nextPage: mockNextPage,
    prevPage: mockPrevPage,
    refreshMissions: mockRefreshMissions,
  }),
  useMissionFilters: () => ({
    lieu: mockLieu,
    budgetMin: mockBudgetMin,
    budgetMax: mockBudgetMax,
    dateTournage: mockDateTournage,
    typeMission: mockTypeMission,
    filters: mockFilters,
    activeFilterCount: mockActiveFilterCount,
    hasActiveFilters: mockHasActiveFilters,
    resetFilters: mockResetFilters,
    syncToUrl: mockSyncToUrl,
    initFromUrl: mockInitFromUrl,
  }),
}))

function createMission(overrides: Partial<Mission> = {}): Mission {
  // AvailableMissionCard is stubbed, so only a key is exercised by the page.
  return { id: 'mission-uuid-1', titre: 'Test mission', ...overrides } as Mission
}

function mountPage() {
  return mount(FaceMissionsListPage, {
    global: {
      stubs: {
        RouterLink: RouterLinkStub,
        AvailableMissionCard: true,
        MissionFiltersPanel: true,
      },
    },
  })
}

describe('FaceMissionsListPage — UGC discovery entry point (ugc-disc-1)', () => {
  beforeEach(() => {
    // Render the populated grid branch by default.
    mockMissions.value = [createMission()]
    mockIsLoading.value = false
    mockError.value = null
    mockIsEmpty.value = false
    mockTotalCount.value = 1
    mockActiveFilterCount.value = 0
    mockHasActiveFilters.value = false
    vi.clearAllMocks()
  })

  it('renders the UGC discovery CTA banner', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const cta = wrapper.find('[data-testid="ugc-discovery-cta"]')
    expect(cta.exists()).toBe(true)
    expect(cta.text()).toContain('Découvrez les missions UGC')
  })

  it('routes the CTA to the existing gated face-ugc-missions page', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const cta = wrapper.findComponent(RouterLinkStub)
    expect(cta.exists()).toBe(true)
    expect(cta.attributes('data-testid')).toBe('ugc-discovery-cta')
    expect(cta.props('to')).toEqual({ name: 'face-ugc-missions' })
  })

  it('keeps the existing mission list rendered — cards + filters unchanged (non-regression)', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.findComponent(AvailableMissionCard).exists()).toBe(true)
    expect(wrapper.findComponent(MissionFiltersPanel).exists()).toBe(true)
  })

  it('shows the CTA even when there are no cash missions (always-on discovery door)', async () => {
    mockMissions.value = []
    mockIsEmpty.value = true
    mockTotalCount.value = 0

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-discovery-cta"]').exists()).toBe(true)
  })
})
