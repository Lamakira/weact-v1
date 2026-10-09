import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, KeepAlive, ref } from 'vue'
import MissionsListPage from '../MissionsListPage.vue'
import type { Mission } from '@/features/mission/types'

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

// The freshly created UGC mission awaiting its commission payment. It is NOT
// on the loaded page — exactly the post-publish redirect scenario of bug F3.
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
// The list is paginated / filtered / sorted SERVER-side: `mockMissions` is only
// the CURRENT page. A mission absent from it (other page, other filter) must
// still open the tunnel through the fetch-by-id fallback (bug F3, new shape).
const mockMissions = ref<Mission[]>([])
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockHasLoaded = ref(true)
const mockFetchMissions = vi.fn().mockResolvedValue(undefined)
const mockRefreshMissions = vi.fn().mockResolvedValue(undefined)
const mockGetMission = vi.hoisted(() => vi.fn())

vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: { getMission: mockGetMission },
}))

vi.mock('@/features/mission/composables', () => ({
  useMissionsList: () => ({
    missions: mockMissions,
    isLoading: mockIsLoading,
    error: mockError,
    currentPage: ref(1),
    lastPage: ref(1),
    total: ref(1),
    hasLoaded: mockHasLoaded,
    fetchMissions: mockFetchMissions,
    refreshMissions: mockRefreshMissions,
  }),
  useDeleteMission: () => ({ deleteMission: vi.fn(), isDeleting: ref(false) }),
  useCloseMission: () => ({ closeMission: vi.fn(), isClosing: ref(false) }),
  useReopenMission: () => ({ reopenMission: vi.fn(), isReopening: ref(false) }),
  useCompleteMission: () => ({ completeMission: vi.fn(), isCompleting: ref(false) }),
}))

vi.mock('@/features/mission/components', () => ({
  // Minimal table stub: exposes the error-state retry the real DataTable renders.
  MissionsTable: defineComponent({
    name: 'MissionsTableStub',
    props: { error: { type: String, default: null } },
    emits: ['retry'],
    setup: (props, { emit }) => () =>
      props.error ? h('button', { onClick: () => emit('retry') }, 'Réessayer') : h('div'),
  }),
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

function resetState(): void {
  vi.clearAllMocks()
  mockRoute.query = {}
  // Current page only holds the published mission; the pending UGC mission is on
  // another page / hidden by the active filter.
  mockMissions.value = [publishedMission]
  mockIsLoading.value = false
  mockError.value = null
  mockHasLoaded.value = true
  mockGetMission.mockReset()
  mockGetMission.mockResolvedValue({ data: ugcPendingMission })
}

describe('MissionsListPage — keep-alive return with ?pay when the mission is not on the loaded page (bug F3)', () => {
  beforeEach(resetState)

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('opens the commission tunnel on return with ?pay by fetching the mission by id', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()

    // Initial mount: the list loads once, no ?pay yet -> tunnel closed.
    expect(mockFetchMissions).toHaveBeenCalledTimes(1)
    expect(wrapper.findComponent(overlayStub).exists()).toBe(false)

    // Producer leaves for PublishMissionPage (page stays cached)...
    current.value = 'other'
    await flushPromises()

    // ...creates a UGC mission and comes back through ?pay={id}: onMounted does NOT re-run.
    mockRoute.query = { pay: 'ugc-mission-1' }
    current.value = 'page'
    await flushPromises()

    // Wiring: useRefreshOnReturn reloaded the list (URL state) on reactivation.
    expect(mockFetchMissions).toHaveBeenCalledTimes(2)

    // MAIN ASSERT: the mission is NOT in the loaded page, so the tunnel can only
    // open through the fetch-by-id fallback (the paginated list is not the whole truth).
    expect(mockGetMission).toHaveBeenCalledWith('ugc-mission-1')
    const overlay = wrapper.findComponent(overlayStub)
    expect(overlay.exists()).toBe(true)
    expect(overlay.props('modelValue')).toBe(true)
    expect(overlay.props('ownerId')).toBe('ugc-mission-1')
    expect(overlay.props('amount')).toBe(2500)
  })

  it('uses the loaded row without any extra request when the mission is on the current page', async () => {
    mockMissions.value = [publishedMission, ugcPendingMission]
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()

    current.value = 'other'
    await flushPromises()
    mockRoute.query = { pay: 'ugc-mission-1' }
    current.value = 'page'
    await flushPromises()

    expect(mockGetMission).not.toHaveBeenCalled()
    expect(wrapper.findComponent(overlayStub).exists()).toBe(true)
  })
})

describe('MissionsListPage — stale ?pay in history / already-paid mission (bug F11)', () => {
  beforeEach(resetState)

  afterEach(() => {
    document.body.innerHTML = ''
  })

  // Fix volet (a) CONSOMMATION : once ?pay has been processed, the page rewrites the
  // history entry via router.replace with a query WITHOUT `pay` (other keys preserved).
  it('consumes ?pay from the URL after opening the tunnel (router.replace without pay)', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()

    current.value = 'other'
    await flushPromises()

    mockRoute.query = { pay: 'ugc-mission-1', ref: 'checkout' }
    current.value = 'page'
    await flushPromises()

    expect(wrapper.findComponent(overlayStub).exists()).toBe(true)

    expect(mockRouter.replace).toHaveBeenCalledTimes(1)
    const replaceArg = mockRouter.replace.mock.calls[0]?.[0] as
      | { query?: Record<string, unknown> }
      | undefined
    expect(replaceArg?.query).toBeDefined()
    expect(replaceArg?.query).not.toHaveProperty('pay')
    expect(replaceArg?.query).toMatchObject({ ref: 'checkout' })
  })

  it('retains ?pay when the mission cannot be found, then consumes it after a later return finds it', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()

    current.value = 'other'
    await flushPromises()

    mockGetMission.mockRejectedValue(new Error('404'))
    mockRoute.query = { pay: 'ugc-mission-1', ref: 'checkout' }
    current.value = 'page'
    await flushPromises()

    expect(wrapper.findComponent(overlayStub).exists()).toBe(false)
    expect(mockRouter.replace).not.toHaveBeenCalled()

    current.value = 'other'
    await flushPromises()
    mockGetMission.mockResolvedValue({ data: ugcPendingMission })
    current.value = 'page'
    await flushPromises()

    expect(wrapper.findComponent(overlayStub).exists()).toBe(true)
    expect(mockRouter.replace).toHaveBeenCalledTimes(1)
    expect(mockRouter.replace).toHaveBeenCalledWith({ query: { ref: 'checkout' } })
  })

  it('retries the retained ?pay after the in-page retry succeeds', async () => {
    mockRoute.query = { pay: 'ugc-mission-1', ref: 'retry' }
    mockError.value = 'Échec temporaire'
    mockGetMission.mockRejectedValueOnce(new Error('network'))

    const { wrapper } = mountKeepAliveHost()
    await flushPromises()

    expect(mockRouter.replace).not.toHaveBeenCalled()
    const retryButton = wrapper.findAll('button').find((button) => button.text().includes('Réessayer'))
    expect(retryButton).toBeDefined()

    mockError.value = null
    await retryButton!.trigger('click')
    await flushPromises()

    expect(wrapper.findComponent(overlayStub).exists()).toBe(true)
    expect(mockRouter.replace).toHaveBeenCalledWith({ query: { ref: 'retry' } })
  })

  // Fix volet (b) GARDE STATUT : a stale ?pay (Back onto the old history entry) points to
  // a mission that has since been paid -> status 'published'. The tunnel must NOT open.
  it('does not open the tunnel for an already-paid (published) mission', async () => {
    const { wrapper, current } = mountKeepAliveHost()
    await flushPromises()
    expect(wrapper.findComponent(overlayStub).exists()).toBe(false)

    current.value = 'other'
    await flushPromises()

    mockRoute.query = { pay: 'mission-published-1' }
    current.value = 'page'
    await flushPromises()

    expect(wrapper.findComponent(overlayStub).exists()).toBe(false)
    // The stale ?pay is consumed (replace without it) so later keep-alive returns stop re-checking it.
    expect(mockRouter.replace).toHaveBeenCalledTimes(1)
    expect(mockRouter.replace).toHaveBeenCalledWith({ query: {} })
  })
})
