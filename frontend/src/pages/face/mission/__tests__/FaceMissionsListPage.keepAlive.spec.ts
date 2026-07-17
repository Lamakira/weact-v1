import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, KeepAlive, ref } from 'vue'
import FaceMissionsListPage from '../FaceMissionsListPage.vue'
import type { Mission, MissionFilters } from '@/features/mission/types'

// Mock vue-router (self-contained — not imported from any sibling spec)
const mockRouter = { push: vi.fn(), replace: vi.fn() }
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  useRoute: () => ({ query: {}, name: 'face-missions' }),
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

// --- useFaceMissions mock state (reactive refs + vi.fn() spies) ---
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

// --- useMissionFilters mock state ---
const mockLieu = ref('')
const mockBudgetMin = ref<number | undefined>(undefined)
const mockBudgetMax = ref<number | undefined>(undefined)
const mockDateTournage = ref('')
const mockTypeMission = ref<string>('')
const mockFilters = ref<MissionFilters>({})
const mockActiveFilterCount = ref(0)
const mockHasActiveFilters = ref(false)
const mockResetFilters = vi.fn()
const mockSyncToUrl = vi.fn()
const mockInitFromUrl = vi.fn()
const mockReinitFromUrl = vi.fn()

// Both composables live in the same barrel — one mock covers both.
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
    reinitFromUrl: mockReinitFromUrl,
  }),
}))

// A distinct sibling under the same <keep-alive>. KeepAlive only fires
// activated/deactivated when the child actually swaps to another component,
// so a real placeholder (not an empty slot) is required to trigger deactivation.
const Placeholder = defineComponent({
  name: 'Placeholder',
  setup: () => () => h('div', 'other'),
})

/**
 * Mounts the page inside a <KeepAlive include="['FaceMissionsListPage']">.
 * Toggling `current` between 'page' and 'other' simulates leaving the cached
 * missions tab (Face opens a mission detail, applies, a producer closes it…)
 * and returning to it — an in-SPA navigation with NO full reload, so
 * onMounted does not re-run on return.
 */
function mountKeepAliveHost() {
  const current = ref<'page' | 'other'>('page')
  const Host = defineComponent({
    setup: () => () =>
      h(
        KeepAlive,
        { include: ['FaceMissionsListPage'] },
        () => (current.value === 'page' ? h(FaceMissionsListPage) : h(Placeholder)),
      ),
  })
  const wrapper = mount(Host, {
    global: {
      stubs: {
        AvailableMissionCard: true,
        MissionFiltersPanel: true,
        UgcDiscoveryBanner: true,
      },
    },
  })
  return { wrapper, current }
}

describe('FaceMissionsListPage — keep-alive refresh on return (bug #7)', () => {
  beforeEach(() => {
    mockMissions.value = []
    mockIsLoading.value = false
    mockError.value = null
    mockIsEmpty.value = false
    mockCurrentPage.value = 1
    mockLastPage.value = 1
    mockTotalCount.value = 0
    mockHasNextPage.value = false
    mockHasPrevPage.value = false
    mockHasFiltersApplied.value = false
    mockLieu.value = ''
    mockBudgetMin.value = undefined
    mockBudgetMax.value = undefined
    mockDateTournage.value = ''
    mockTypeMission.value = ''
    mockFilters.value = {}
    mockActiveFilterCount.value = 0
    mockHasActiveFilters.value = false
    vi.clearAllMocks()
  })

  it('refreshes on keep-alive reactivation but not on first activation', async () => {
    const { current } = mountKeepAliveHost()
    await flushPromises()

    // 1. Initial mount + first activation: onMounted loads the list once,
    //    no refresh yet.
    expect(mockFetchMissions).toHaveBeenCalledTimes(1)
    expect(mockRefreshMissions).not.toHaveBeenCalled()

    // 2. Deactivate: swap the cached page out for the placeholder.
    current.value = 'other'
    await flushPromises()
    expect(mockRefreshMissions).not.toHaveBeenCalled()

    // 3. Reactivate: swap back to the cached page. The Face has just opened a
    //    mission detail (applied / a producer closed the mission) and returned;
    //    the list statuses must be re-fetched. Because the page is kept alive,
    //    onMounted does NOT re-run — the refresh must come from an onActivated
    //    hook (useRefreshOnReturn). This FAILS on current code and PASSES once
    //    the fix is applied.
    current.value = 'page'
    await flushPromises()
    expect(mockRefreshMissions).toHaveBeenCalledTimes(1)
  })
})
