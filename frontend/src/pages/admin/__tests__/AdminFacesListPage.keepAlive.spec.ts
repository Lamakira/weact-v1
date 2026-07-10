import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, KeepAlive, nextTick, ref } from 'vue'
import AdminFacesListPage from '../AdminFacesListPage.vue'

// Mock vue-router (self-contained — the page only uses useRouter().push)
const mockRouter = { push: vi.fn(), replace: vi.fn() }
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  useRoute: () => ({ query: {}, name: 'admin-faces-list' }),
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

// --- useAdminFaces mock state (reactive refs + vi.fn() spy) ---
const mockFetchFaces = vi.fn()
const facesRef = ref<unknown[]>([])
const paginationRef = ref<{
  current_page: number
  last_page: number
  per_page: number
  total: number
} | null>(null)
const isLoadingRef = ref(false)
const errorRef = ref<string | null>(null)

vi.mock('@/features/admin/composables/useAdminFaces', () => ({
  useAdminFaces: () => ({
    faces: facesRef,
    pagination: paginationRef,
    isLoading: isLoadingRef,
    error: errorRef,
    fetchFaces: mockFetchFaces,
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
 * Mounts the page inside a <KeepAlive include="['AdminFacesListPage']">.
 * Toggling `current` between 'page' and 'other' simulates the admin leaving
 * the cached list (clicking a Face row → detail page) — an in-SPA navigation
 * with NO unmount: onUnmounted does not run, only onDeactivated would.
 */
function mountKeepAliveHost() {
  const current = ref<'page' | 'other'>('page')
  const Host = defineComponent({
    setup: () => () =>
      h(
        KeepAlive,
        { include: ['AdminFacesListPage'] },
        () => (current.value === 'page' ? h(AdminFacesListPage) : h(Placeholder)),
      ),
  })
  const wrapper = mount(Host)
  return { wrapper, current }
}

describe('AdminFacesListPage — search debounce vs keep-alive deactivation (bug F9)', () => {
  beforeEach(() => {
    facesRef.value = []
    paginationRef.value = null
    isLoadingRef.value = false
    errorRef.value = null
    vi.clearAllMocks()
    // Fake timers to control the 300 ms search debounce. flushPromises is NOT
    // used in these tests (it relies on faked scheduling primitives and would
    // hang); microtasks are flushed via awaited nextTick, timers via
    // advanceTimersByTimeAsync.
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('does NOT fetch off-screen when the debounce timer fires after deactivation (repro — RED today)', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await nextTick()

    // 1. Initial mount: onMounted loads the list once.
    expect(mockFetchFaces).toHaveBeenCalledTimes(1)

    // 2. The admin types in the search field…
    await wrapper.find('[data-testid="search-input"]').setValue('foo')

    // 3. …then IMMEDIATELY clicks a row (deactivation) within the 300 ms
    //    debounce window. The page is cached by keep-alive: onUnmounted does
    //    NOT run, and no onDeactivated cleanup exists → the timer stays armed.
    current.value = 'other'
    await nextTick()

    // 4. The debounce window elapses while the page is off-screen.
    await vi.advanceTimersByTimeAsync(400)

    // Contract of the fix: the debounce is cancelled on deactivation — no
    // timer-driven fetch may happen after the page is deactivated. Today the
    // off-screen timer fires loadFaces(1) → 2 calls → this test FAILS.
    expect(mockFetchFaces).toHaveBeenCalledTimes(1)
  })

  it('still fetches through the debounce while the page stays active (control — GREEN today and after fix)', async () => {
    const { wrapper } = mountKeepAliveHost()
    await nextTick()
    expect(mockFetchFaces).toHaveBeenCalledTimes(1)

    // Type without deactivating: the debounce must run normally.
    await wrapper.find('[data-testid="search-input"]').setValue('foo')
    await vi.advanceTimersByTimeAsync(400)

    expect(mockFetchFaces).toHaveBeenCalledTimes(2)
    expect(mockFetchFaces).toHaveBeenLastCalledWith({ page: 1, search: 'foo' })
  })
})
