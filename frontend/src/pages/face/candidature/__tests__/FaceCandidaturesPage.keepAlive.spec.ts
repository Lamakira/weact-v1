import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h, KeepAlive, ref } from 'vue'
import FaceCandidaturesPage from '../FaceCandidaturesPage.vue'
import type { FaceCandidature } from '@/features/candidature/types'

// Mock vue-router (self-contained — not imported from any sibling spec)
const mockRouter = { push: vi.fn(), replace: vi.fn() }
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  useRoute: () => ({ query: {}, name: 'face-candidatures' }),
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
    clear: vi.fn(),
  }),
}))

// --- useFaceCandidatures mock state (reactive refs + vi.fn() spies) ---
const mockCandidatures = ref<FaceCandidature[]>([])
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockCurrentPage = ref(1)
const mockLastPage = ref(1)
const mockTotal = ref(0)
const mockHasNextPage = ref(false)
const mockHasPrevPage = ref(false)
const mockIsEmpty = ref(false)
const mockStatusFilter = ref('')
const mockFetchCandidatures = vi.fn()
const mockNextPage = vi.fn()
const mockPrevPage = vi.fn()
const mockGoToPage = vi.fn()
const mockSetStatusFilter = vi.fn()
const mockRefresh = vi.fn()
const mockCancelCandidature = vi.fn()
const mockConfirmCandidature = vi.fn()
const mockReconfirmCandidature = vi.fn()

// All four composables live in the same barrel — one mock covers them.
vi.mock('@/features/candidature/composables', () => ({
  useFaceCandidatures: () => ({
    candidatures: mockCandidatures,
    isLoading: mockIsLoading,
    error: mockError,
    currentPage: mockCurrentPage,
    lastPage: mockLastPage,
    total: mockTotal,
    hasNextPage: mockHasNextPage,
    hasPrevPage: mockHasPrevPage,
    isEmpty: mockIsEmpty,
    statusFilter: mockStatusFilter,
    fetchCandidatures: mockFetchCandidatures,
    nextPage: mockNextPage,
    prevPage: mockPrevPage,
    goToPage: mockGoToPage,
    setStatusFilter: mockSetStatusFilter,
    refresh: mockRefresh,
  }),
  useConfirmCandidature: () => ({
    error: ref(null),
    successMessage: ref(null),
    confirmCandidature: mockConfirmCandidature,
    reset: vi.fn(),
  }),
  useReconfirmCandidature: () => ({
    error: ref(null),
    successMessage: ref(null),
    reconfirmCandidature: mockReconfirmCandidature,
    reset: vi.fn(),
  }),
  useCancelCandidature: () => ({
    error: ref(null),
    successMessage: ref(null),
    cancelCandidature: mockCancelCandidature,
    reset: vi.fn(),
  }),
}))

/**
 * One PENDING candidature: CandidatureCard shows its real
 * « Annuler ma candidature » button only when status === 'pending'.
 */
function makePendingCandidature(): FaceCandidature {
  return {
    id: 'cand-1',
    status: 'pending',
    status_label: 'En attente',
    message_motivation: null,
    created_at: '2026-06-20T10:00:00Z',
    mission: {
      id: 'm1',
      titre: 'Mission test',
      date_tournage: '2026-07-01',
      lieu: 'Cotonou',
      budget: 100000,
      type_compensation: null,
    },
    producer: {
      id: 'p1',
      display_name: 'Acme',
      type: 'agency',
      profile_photo_url: null,
      profile_photo_thumbnail_url: null,
    },
    conversation_id: null,
  }
}

// A distinct sibling under the same <keep-alive>. KeepAlive only fires
// activated/deactivated when the child actually swaps to another component,
// so a real placeholder (not an empty slot) is required to trigger deactivation.
const Placeholder = defineComponent({
  name: 'Placeholder',
  setup: () => () => h('div', 'other'),
})

/**
 * Mounts the page inside a <KeepAlive include="['FaceCandidaturesPage']">.
 * Toggling `current` between 'page' and 'other' simulates leaving the cached
 * candidatures tab (in-SPA navigation, NO full reload) and coming back.
 *
 * IMPORTANT: ConfirmModal and CandidatureCard are REAL (no stubs, no Teleport
 * stub) — the modal content must actually land in document.body for the bug
 * to be observable.
 */
function mountKeepAliveHost() {
  const current = ref<'page' | 'other'>('page')
  const Host = defineComponent({
    setup: () => () =>
      h(
        KeepAlive,
        { include: ['FaceCandidaturesPage'] },
        () => (current.value === 'page' ? h(FaceCandidaturesPage) : h(Placeholder)),
      ),
  })
  const wrapper = mount(Host, {
    global: {
      stubs: {
        StatusFilter: true,
      },
    },
  })
  return { wrapper, current }
}

/** The ConfirmModal backdrop teleported into document.body (ConfirmModal.vue l.55). */
function teleportedOverlay(): Element | null {
  return document.body.querySelector('.fixed.inset-0.z-50')
}

/**
 * Flush pending promises AND let <Transition> leave hooks complete (they
 * schedule DOM removal on requestAnimationFrame frames, not on microtasks).
 */
async function settle(): Promise<void> {
  await flushPromises()
  await new Promise<void>((resolve) => {
    if (typeof requestAnimationFrame === 'function') {
      requestAnimationFrame(() => requestAnimationFrame(() => resolve()))
    } else {
      setTimeout(resolve, 0)
    }
  })
  await flushPromises()
}

let activeWrapper: VueWrapper | null = null

describe('FaceCandidaturesPage — teleported ConfirmModal vs keep-alive deactivation (bug F2)', () => {
  beforeEach(() => {
    mockCandidatures.value = [makePendingCandidature()]
    mockIsLoading.value = false
    mockError.value = null
    mockCurrentPage.value = 1
    mockLastPage.value = 1
    mockTotal.value = 1
    mockHasNextPage.value = false
    mockHasPrevPage.value = false
    mockIsEmpty.value = false
    mockStatusFilter.value = ''
    vi.clearAllMocks()
  })

  afterEach(() => {
    // happy-dom keeps document.body across tests; teleported residues would
    // leak into the next test without an explicit unmount + body reset.
    activeWrapper?.unmount()
    activeWrapper = null
    document.body.innerHTML = ''
  })

  it('removes the teleported cancel-modal overlay from document.body on deactivation, and it does not reappear on reactivation', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    activeWrapper = wrapper
    await settle()

    // 1. Open the modal through the real UI: the « Annuler ma candidature »
    //    button on the pending candidature card.
    const cancelButton = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Annuler ma candidature'))
    expect(cancelButton, 'cancel button on the pending card').toBeDefined()
    await cancelButton!.trigger('click')
    await settle()

    // 2. SANITY: while the page is active, the ConfirmModal overlay
    //    (fixed inset-0 z-50 backdrop) is teleported into document.body.
    expect(teleportedOverlay(), 'overlay in body while page active').not.toBeNull()

    // 3. Deactivate the page: KeepAlive moves the page subtree to its storage
    //    container, but never moves teleported children — buggy code leaves
    //    the full-screen overlay in document.body on top of the next page.
    current.value = 'other'
    await settle()

    // MAIN ASSERT (red today): after deactivation, the teleported overlay
    // must be GONE from document.body. Behaviour-only — we don't care HOW
    // the page closes it (onDeactivated, emit, watcher…).
    expect(
      teleportedOverlay(),
      'teleported overlay must leave document.body when the page is deactivated',
    ).toBeNull()

    // 4. Reactivate the cached page: the modal must not silently reappear.
    current.value = 'page'
    await settle()
    expect(
      teleportedOverlay(),
      'modal must not reappear when the cached page is reactivated',
    ).toBeNull()
  })
})
