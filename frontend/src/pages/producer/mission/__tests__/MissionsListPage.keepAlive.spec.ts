import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, KeepAlive, ref } from 'vue'
import MissionsListPage from '../MissionsListPage.vue'
import type { Mission, MissionStatusType } from '@/features/mission/types'

// --- Mock vue-router (self-contained — not shared with the sibling spec) ---
// `mockRoute` is a STABLE object whose `query` is mutated between activations:
// the component holds this same reference, so mutating `query` here simulates
// the in-SPA redirect producer-missions?pay={id} landing on the CACHED page.
const mockRouter = { push: vi.fn(), replace: vi.fn() }
const mockRoute: { name: string; query: Record<string, unknown> } = {
  name: 'producer-missions',
  query: {},
}
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  useRoute: () => mockRoute,
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { email_verified: true, email_verified_at: '2026-04-01T00:00:00Z' },
    isEmailVerified: true,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn(), clear: vi.fn(), toast: {} }),
}))

// --- Fixtures (shape mirrors MissionsListPage.spec.ts) ---
const publishedMission: Mission = {
  id: 'mission-published-1',
  titre: 'Tournage publié',
  description: 'fixture',
  date_tournage: '2026-08-01',
  profil_recherche: 'Face',
  budget: 100000,
  date_limite_candidature: '2026-07-25',
  nombre_faces_voulu: 1,
  type_mission: 'publicite',
  type_mission_label: 'Publicité',
  type_mission_autre: null,
  genre_voulu: 'tous',
  genre_voulu_label: 'Homme et Femme',
  lieu: 'Cotonou',
  duree: '1 jour',
  status: 'published',
  status_label: 'Publiée',
  is_accepting_candidatures: true,
  has_paid_payment: true,
  candidatures_count: 0,
  created_at: '2026-07-01T00:00:00Z',
  updated_at: '2026-07-01T00:00:00Z',
}

// The freshly created UGC mission awaiting its commission payment. It is
// EXCLUDED from the filtered list (statusFilter = 'published') but present in
// allMissions — exactly the post-publish redirect scenario of bug F3.
const ugcPendingMission: Mission = {
  ...publishedMission,
  id: 'ugc-mission-1',
  titre: 'Appel UGC — Unboxing',
  status: 'pending_payment',
  status_label: 'En attente de paiement',
  is_accepting_candidatures: false,
  has_paid_payment: false,
  commission_ugc: 2500,
}

// --- useMissionsList mock state (reactive refs + vi.fn() spies) ---
// `missions` (the FILTERED list) deliberately omits the pending_payment
// mission; `allMissions` contains it — reproducing useMissionsList.ts where
// `missions: filteredMissions` filters client-side on statusFilter.
const mockFilteredMissions = ref<Mission[]>([])
const mockAllMissions = ref<Mission[]>([])
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockIsEmpty = ref(false)
const mockHasNoMissions = ref(false)
const mockStatusFilter = ref<MissionStatusType | ''>('')
const mockFetchMissions = vi.fn().mockResolvedValue(undefined)
const mockRefreshMissions = vi.fn().mockResolvedValue(undefined)
const mockSetStatusFilter = vi.fn()

vi.mock('@/features/mission/composables', () => ({
  useMissionsList: () => ({
    missions: mockFilteredMissions,
    allMissions: mockAllMissions,
    isLoading: mockIsLoading,
    error: mockError,
    isEmpty: mockIsEmpty,
    hasNoMissions: mockHasNoMissions,
    statusFilter: mockStatusFilter,
    fetchMissions: mockFetchMissions,
    refreshMissions: mockRefreshMissions,
    setStatusFilter: mockSetStatusFilter,
  }),
  useDeleteMission: () => ({ deleteMission: vi.fn(), isDeleting: ref(false) }),
  useCloseMission: () => ({ closeMission: vi.fn(), isClosing: ref(false) }),
  useReopenMission: () => ({ reopenMission: vi.fn(), isReopening: ref(false) }),
  useCompleteMission: () => ({ completeMission: vi.fn(), isCompleting: ref(false) }),
}))

vi.mock('@/features/mission/components', () => ({
  MissionCard: defineComponent({ name: 'MissionCardStub', setup: () => () => h('div') }),
  DeleteMissionDialog: defineComponent({ name: 'DeleteMissionDialogStub', setup: () => () => h('div') }),
  CloseMissionDialog: defineComponent({ name: 'CloseMissionDialogStub', setup: () => () => h('div') }),
  ReopenMissionDialog: defineComponent({ name: 'ReopenMissionDialogStub', setup: () => () => h('div') }),
  CompleteMissionDialog: defineComponent({ name: 'CompleteMissionDialogStub', setup: () => () => h('div') }),
  MissionStatusFilter: defineComponent({ name: 'MissionStatusFilterStub', setup: () => () => h('div') }),
}))

// Inspectable stub for the commission tunnel: keeps the page's real props so
// the test can assert HOW the tunnel was opened (modelValue / ownerId / amount).
// The page renders it under `v-if="payingMission"`, so mere existence already
// proves handlePayCommission resolved the mission.
const overlayStub = defineComponent({
  name: 'UgcPaymentOverlay',
  props: {
    modelValue: { type: Boolean, required: true },
    kind: { type: String, default: 'mission' },
    ownerId: { type: String, default: '' },
    amount: { type: Number, default: 0 },
    reference: { type: String, default: '' },
  },
  setup: (props) => () => h('div', { 'data-testid': 'ugc-overlay-stub', 'data-open': String(props.modelValue) }),
})

// A distinct sibling under the same <keep-alive>. KeepAlive only fires
// activated/deactivated when the child actually swaps to another component,
// so a real placeholder (not an empty slot) is required to trigger deactivation.
const Placeholder = defineComponent({
  name: 'Placeholder',
  setup: () => () => h('div', 'other'),
})

/**
 * Mounts the page inside a <KeepAlive include="['MissionsListPage']">.
 * Toggling `current` between 'page' and 'other' simulates leaving the cached
 * missions tab (producer goes to PublishMissionPage, creates a UGC mission)
 * and returning to it via the ?pay redirect — an in-SPA navigation with NO
 * full reload, so onMounted does not re-run on return.
 */
function mountKeepAliveHost() {
  const current = ref<'page' | 'other'>('page')
  const Host = defineComponent({
    setup: () => () =>
      h(
        KeepAlive,
        { include: ['MissionsListPage'] },
        () => (current.value === 'page' ? h(MissionsListPage) : h(Placeholder)),
      ),
  })
  const wrapper = mount(Host, {
    global: {
      stubs: { UgcPaymentOverlay: overlayStub },
    },
  })
  return { wrapper, current }
}

describe('MissionsListPage — keep-alive return with ?pay under an active status filter (bug F3)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRoute.query = {}
    // Producer had filtered on « Publiées » BEFORE leaving the page; the
    // filter persists across keep-alive. The new UGC mission (pending_payment)
    // is therefore absent from the filtered list, present in allMissions.
    mockStatusFilter.value = 'published'
    mockFilteredMissions.value = [publishedMission]
    mockAllMissions.value = [publishedMission, ugcPendingMission]
    mockIsLoading.value = false
    mockError.value = null
    mockIsEmpty.value = false
    mockHasNoMissions.value = false
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('opens the commission tunnel on return with ?pay even when the active filter hides the mission', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()

    // 1. Initial mount + first activation: onMounted loads once, no ?pay in
    //    the URL yet → tunnel closed.
    expect(mockFetchMissions).toHaveBeenCalledTimes(1)
    expect(mockRefreshMissions).not.toHaveBeenCalled()
    expect(wrapper.findComponent(overlayStub).exists()).toBe(false)

    // 2. Deactivate: producer navigates to PublishMissionPage (the missions
    //    page stays cached by keep-alive, statusFilter persists).
    current.value = 'other'
    await flushPromises()

    // 3. UGC mission created → PublishMissionPage redirects to
    //    producer-missions?pay={id}: mutate the query, then reactivate the
    //    CACHED page (onMounted does NOT re-run).
    mockRoute.query = { pay: 'ugc-mission-1' }
    current.value = 'page'
    await flushPromises()

    // Sanity (wiring): useRefreshOnReturn ran on reactivation and refreshed.
    expect(mockRefreshMissions).toHaveBeenCalledTimes(1)

    // MAIN ASSERT (bug F3): the commission tunnel MUST open. Today
    // handlePayCommission does `missions.value.find(...)` on the FILTERED
    // list — the pending_payment mission is excluded by the 'published'
    // filter, find() misses, silent return, the overlay never renders and
    // the mission stays stuck in pending_payment. The fix (lookup in
    // allMissions.value) makes these assertions pass.
    const overlay = wrapper.findComponent(overlayStub)
    expect(overlay.exists()).toBe(true)
    expect(overlay.props('modelValue')).toBe(true)
    expect(overlay.props('ownerId')).toBe('ugc-mission-1')
    expect(overlay.props('amount')).toBe(2500)
  })
})

describe('MissionsListPage — stale ?pay in history / already-paid mission (bug F11)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRoute.query = {}
    // Same baseline as the F3 suite: 'published' filter active, the
    // pending_payment UGC mission only present in allMissions.
    mockStatusFilter.value = 'published'
    mockFilteredMissions.value = [publishedMission]
    mockAllMissions.value = [publishedMission, ugcPendingMission]
    mockIsLoading.value = false
    mockError.value = null
    mockIsEmpty.value = false
    mockHasNoMissions.value = false
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  // T-a — fix volet (a) CONSOMMATION : once ?pay has been processed, the page
  // must rewrite the history entry via router.replace with a query WITHOUT
  // `pay` (other keys preserved), so a later Back does not carry ?pay again.
  // RED today: maybeOpenPayTunnel never calls router.replace.
  it('consumes ?pay from the URL after opening the tunnel (router.replace without pay)', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()

    // Deactivate (producer leaves for PublishMissionPage)…
    current.value = 'other'
    await flushPromises()

    // …then return via the ?pay redirect, alongside another query key that
    // the consumption MUST preserve.
    mockRoute.query = { pay: 'ugc-mission-1', ref: 'checkout' }
    current.value = 'page'
    await flushPromises()

    // Sanity: the ?pay WAS processed (tunnel open) — consumption is asserted
    // on top of a processed ?pay, not instead of it.
    expect(wrapper.findComponent(overlayStub).exists()).toBe(true)

    // MAIN ASSERT (bug F11-a): the history entry must be rewritten.
    expect(mockRouter.replace).toHaveBeenCalledTimes(1)
    const replaceArg = mockRouter.replace.mock.calls[0]?.[0] as
      | { query?: Record<string, unknown> }
      | undefined
    expect(replaceArg?.query).toBeDefined()
    expect(replaceArg?.query).not.toHaveProperty('pay')
    expect(replaceArg?.query).toMatchObject({ ref: 'checkout' })
  })

  it('retains ?pay when the mission is missing, then consumes it after a later refresh finds the mission', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()

    current.value = 'other'
    await flushPromises()

    mockAllMissions.value = [publishedMission]
    mockRoute.query = { pay: 'ugc-mission-1', ref: 'checkout' }
    current.value = 'page'
    await flushPromises()

    expect(wrapper.findComponent(overlayStub).exists()).toBe(false)
    expect(mockRouter.replace).not.toHaveBeenCalled()

    current.value = 'other'
    await flushPromises()
    mockAllMissions.value = [publishedMission, ugcPendingMission]
    current.value = 'page'
    await flushPromises()

    expect(wrapper.findComponent(overlayStub).exists()).toBe(true)
    expect(mockRouter.replace).toHaveBeenCalledTimes(1)
    expect(mockRouter.replace).toHaveBeenCalledWith({ query: { ref: 'checkout' } })
  })

  it('retries the retained ?pay after the in-page refresh succeeds', async () => {
    mockRoute.query = { pay: 'ugc-mission-1', ref: 'retry' }
    mockAllMissions.value = [publishedMission]
    mockFilteredMissions.value = []
    mockError.value = 'Échec temporaire'
    mockRefreshMissions.mockImplementationOnce(async () => {
      mockAllMissions.value = [publishedMission, ugcPendingMission]
      mockFilteredMissions.value = [publishedMission]
      mockError.value = null
    })

    const { wrapper } = mountKeepAliveHost()
    await flushPromises()

    expect(mockRouter.replace).not.toHaveBeenCalled()
    const retryButton = wrapper.findAll('button').find((button) => button.text().includes('Réessayer'))
    expect(retryButton).toBeDefined()
    await retryButton!.trigger('click')
    await flushPromises()

    expect(wrapper.findComponent(overlayStub).exists()).toBe(true)
    expect(mockRouter.replace).toHaveBeenCalledWith({ query: { ref: 'retry' } })
  })

  // T-b — fix volet (b) GARDE STATUT : a stale ?pay (Back onto the old
  // producer-missions?pay={id} history entry) points to a mission that has
  // since been paid → status 'published'. The tunnel must NOT open.
  // RED today: handlePayCommission finds the mission in allMissions and opens
  // the overlay with no status check.
  it('does not open the tunnel for an already-paid (published) mission', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()
    expect(wrapper.findComponent(overlayStub).exists()).toBe(false)

    // Deactivate, then simulate Back onto the stale history entry
    // producer-missions?pay={id} for a mission that is ALREADY published
    // (present in BOTH the filtered list and allMissions).
    current.value = 'other'
    await flushPromises()

    mockRoute.query = { pay: 'mission-published-1' }
    current.value = 'page'
    await flushPromises()

    // MAIN ASSERT (bug F11-b): status !== 'pending_payment' → no tunnel.
    // The page renders the overlay under `v-if="payingMission"`, so mere
    // existence means handlePayCommission accepted the paid mission.
    expect(wrapper.findComponent(overlayStub).exists()).toBe(false)
    expect(mockRouter.replace).not.toHaveBeenCalled()
  })
})
