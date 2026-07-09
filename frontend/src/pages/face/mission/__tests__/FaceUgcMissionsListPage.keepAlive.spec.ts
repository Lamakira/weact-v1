import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, KeepAlive, ref } from 'vue'
import FaceUgcMissionsListPage from '../FaceUgcMissionsListPage.vue'
import type { Mission, UgcMissionTeaser, UgcPaywallMeta } from '@/features/mission/types'

// Mock vue-router (self-contained — not imported from the sibling spec)
const mockRouter = { push: vi.fn() }
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

// Mock the discovery composable with reactive refs + vi.fn() spies
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

// A distinct sibling under the same <keep-alive>. KeepAlive only fires
// activated/deactivated when the child actually swaps to another component,
// so a real placeholder (not an empty slot) is required to trigger deactivation.
const Placeholder = defineComponent({
  name: 'Placeholder',
  setup: () => () => h('div', 'other'),
})

/**
 * Mounts the page inside a <KeepAlive include="['FaceUgcMissionsListPage']">.
 * Toggling `current` between 'page' and 'other' simulates leaving the cached
 * tab (Face pays on /face/billing) and returning to it — an in-SPA navigation
 * with NO full reload, so onMounted does not re-run on return.
 */
function mountKeepAliveHost() {
  const current = ref<'page' | 'other'>('page')
  const Host = defineComponent({
    setup: () => () =>
      h(
        KeepAlive,
        { include: ['FaceUgcMissionsListPage'] },
        () => (current.value === 'page' ? h(FaceUgcMissionsListPage) : h(Placeholder)),
      ),
  })
  const wrapper = mount(Host)
  return { wrapper, current }
}

describe('FaceUgcMissionsListPage — keep-alive refresh on return (bug #6)', () => {
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

  it('refreshes on keep-alive reactivation but not on first activation', async () => {
    const { current } = mountKeepAliveHost()
    await flushPromises()

    // 1. Initial mount + first activation: fetches page 1, no refresh yet.
    expect(mockFetchMissions).toHaveBeenCalledWith(1)
    expect(mockRefreshMissions).not.toHaveBeenCalled()

    // 2. Deactivate: swap the cached page out for the placeholder.
    current.value = 'other'
    await flushPromises()
    expect(mockRefreshMissions).not.toHaveBeenCalled()

    // 3. Reactivate: swap back to the cached page. The user has just paid on
    //    /face/billing; on return the paywall must be re-fetched. Because the
    //    page is kept alive, onMounted does NOT re-run — the refresh must come
    //    from an onActivated hook (useRefreshOnReturn). This FAILS on current
    //    code and PASSES once the fix is applied.
    current.value = 'page'
    await flushPromises()
    expect(mockRefreshMissions).toHaveBeenCalledTimes(1)
  })
})
