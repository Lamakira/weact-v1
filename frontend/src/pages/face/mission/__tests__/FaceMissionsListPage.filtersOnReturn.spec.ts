import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h, KeepAlive, ref } from 'vue'
import FaceMissionsListPage from '../FaceMissionsListPage.vue'
import type { Mission } from '@/features/mission/types'

/**
 * F6 — reproduction spec (keep-alive + filters reconciliation on return).
 *
 * The page is cached under <keep-alive> and calls
 * `useRefreshOnReturn(() => refreshMissions(filters.value))`. `filters` is a
 * LIVE computed over the refs bound (v-model/defineModel) to the filter panel
 * inputs — updated on every keystroke, with no separate draft state. So on
 * reactivation the refresh refetches with whatever sits in the inputs, NOT
 * with what the current URL says.
 *
 * Contract asserted here (implementation-agnostic): on reactivation the page
 * must reconcile its filters with the CURRENT URL (source of truth for
 * applied filters) before refetching:
 *  - return with a clean URL  -> refresh called with {} (drafts discarded,
 *    applied filters abandoned — the pre-keep-alive remount semantics);
 *  - return with ?lieu=Cotonou -> refresh called with { lieu: 'Cotonou' }.
 */

// Mock vue-router with a STABLE route object whose query is MUTABLE from the
// tests (simulates the URL the page comes back to). Not a real router: the
// `watch(() => route.query)` inside useMissionFilters will NOT self-fire on
// mutation — intentional, the contract under test is the reactivation path.
const mockRoute: { query: Record<string, string>; name: string } = {
  query: {},
  name: 'face-missions',
}
const mockRouter = {
  push: vi.fn(() => Promise.resolve()),
  replace: vi.fn(() => Promise.resolve()), // syncToUrl chains .finally()
}
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  useRoute: () => mockRoute,
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

// Partial barrel mock: ONLY useFaceMissions is mocked. useMissionFilters must
// stay REAL — it owns the filter refs whose live-ness is the bug under test.
vi.mock('@/features/mission/composables', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/mission/composables')>()),
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
}))

// Stub of MissionFiltersPanel exposing the page's real v-model contract
// (defineModel props + apply/reset emits) so tests can type into the panel
// (update:lieu) and click "Appliquer" (apply) from the outside.
const MissionFiltersPanelStub = defineComponent({
  name: 'MissionFiltersPanel',
  props: ['lieu', 'budgetMin', 'budgetMax', 'dateTournage', 'typeMission', 'isLoading'],
  emits: [
    'update:lieu',
    'update:budgetMin',
    'update:budgetMax',
    'update:dateTournage',
    'update:typeMission',
    'apply',
    'reset',
  ],
  setup: () => () => h('div', 'filters-panel-stub'),
})

// A distinct sibling under the same <keep-alive>: KeepAlive only fires
// activated/deactivated when the child actually swaps to another component.
const Placeholder = defineComponent({
  name: 'Placeholder',
  setup: () => () => h('div', 'other'),
})

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
        MissionFiltersPanel: MissionFiltersPanelStub,
        UgcDiscoveryBanner: true,
      },
    },
  })
  return { wrapper, current }
}

/** The desktop sidebar panel (always rendered; the mobile one sits behind v-if). */
function findPanel(wrapper: VueWrapper) {
  const panel = wrapper.findComponent(MissionFiltersPanelStub)
  expect(panel.exists()).toBe(true)
  return panel
}

async function leaveAndReturn(current: { value: 'page' | 'other' }) {
  current.value = 'other'
  await flushPromises()
  current.value = 'page'
  await flushPromises()
}

describe('FaceMissionsListPage — filters reconciled with URL on keep-alive return (F6)', () => {
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
    mockRoute.query = {}
    vi.clearAllMocks()
  })

  it('T1 — discards a never-applied draft: return with clean URL refreshes with {}', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()

    // The Face types "Cotonou" in the Ville input (v-model fires on every
    // keystroke) but NEVER clicks "Appliquer" — the URL stays clean.
    findPanel(wrapper).vm.$emit('update:lieu', 'Cotonou')
    await flushPromises()

    // She opens a mission detail, then comes back (keep-alive reactivation).
    await leaveAndReturn(current)

    // Contract: the draft is thrown away — refresh with EMPTY filters, as the
    // clean URL dictates. Today: called with { lieu: 'Cotonou' } (live refs).
    expect(mockRefreshMissions).toHaveBeenCalledTimes(1)
    expect(mockRefreshMissions).toHaveBeenLastCalledWith({})
  })

  it('T2 — abandons applied filters on clean-URL return (sidebar "Missions" click)', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()

    // The Face types "Cotonou" AND clicks "Appliquer" (syncToUrl → router
    // mock; route.query deliberately stays {}: the scenario's real URL is the
    // clean /face/missions the sidebar link navigates to on return).
    const panel = findPanel(wrapper)
    panel.vm.$emit('update:lieu', 'Cotonou')
    await flushPromises()
    panel.vm.$emit('apply')
    await flushPromises()
    expect(mockFetchMissions).toHaveBeenLastCalledWith(1, { lieu: 'Cotonou' })

    // She visits a detail page, then clicks "Missions" in the sidebar —
    // /face/missions WITHOUT query — which reactivates the cached instance.
    await leaveAndReturn(current)

    // Contract: clean URL = no applied filters — refresh with {}.
    // Today: called with { lieu: 'Cotonou' } under a pristine URL.
    expect(mockRefreshMissions).toHaveBeenCalledTimes(1)
    expect(mockRefreshMissions).toHaveBeenLastCalledWith({})
  })

  it('T3 — keeps URL-borne filters: return with ?lieu=Cotonou refreshes with { lieu: "Cotonou" }', async () => {
    const { current } = mountKeepAliveHost()
    await flushPromises()

    // Leave for a detail page…
    current.value = 'other'
    await flushPromises()

    // …then browser-back restores /face/missions?lieu=Cotonou and reactivates.
    mockRoute.query = { lieu: 'Cotonou' }
    current.value = 'page'
    await flushPromises()

    // Contract: filters carried by the URL survive the return.
    expect(mockRefreshMissions).toHaveBeenCalledTimes(1)
    expect(mockRefreshMissions).toHaveBeenLastCalledWith({ lieu: 'Cotonou' })
  })
})
