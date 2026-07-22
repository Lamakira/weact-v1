/**
 * Prove-it reproduction test for bug F8 (sticky error under keep-alive).
 *
 * The public listing composables (usePaginatedFaces / usePaginatedMissions /
 * usePaginatedArticles) record `loadedSignature = querySignature()` BEFORE
 * awaiting the fetch and never reset it in the catch. Their views are cached by
 * <keep-alive :include> in App.vue. So when a fetch FAILS (network error), the
 * query is still marked "loaded": on a keep-alive return to the same query, the
 * watcher guard (`querySignature() === loadedSignature` → return) SKIPS the
 * refetch and the stale error screen is restored as-is. Before keep-alive, the
 * remount re-triggered the load automatically.
 *
 * Contract of the fix (applied separately): after a FAILED fetch, a keep-alive
 * return on the same query RETRIES the fetch (the signature of an errored query
 * is forgotten). A SUCCESSFUL fetch keeps today's behavior: zero refetch on
 * return (Group A goal, locked by keepAlivePublicListing.spec.ts) — witnessed
 * here by the "after a SUCCESSFUL fetch" tests, which must stay green.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { defineComponent, h, KeepAlive, Transition } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, RouterView, useRoute } from 'vue-router'
import { usePaginatedFaces } from '@/features/public/composables/usePaginatedFaces'
import { usePaginatedMissions } from '@/features/public/composables/usePaginatedMissions'
import { fetchPublicFaces } from '@/features/public/services/publicFacesApi'
import { fetchPublicMissions } from '@/features/public/services/publicMissionsApi'

vi.mock('@/features/public/services/publicFacesApi', () => ({
  fetchPublicFaces: vi.fn(),
}))
vi.mock('@/features/public/services/publicMissionsApi', () => ({
  fetchPublicMissions: vi.fn(),
}))

const facesResponse = {
  data: [{ id: 1 }, { id: 2 }],
  meta: { current_page: 2, last_page: 3, per_page: 16, total: 40 },
  message: 'ok',
}
const missionsResponse = {
  data: [{ id: 1 }],
  meta: { current_page: 2, last_page: 3, per_page: 15, total: 40 },
  message: 'ok',
}

// Mirrors App.vue's public branch exactly: <Transition mode="out-in"> wrapping
// <keep-alive :include> wrapping the routed component keyed by route.path.
function makeApp(includeName: string) {
  return defineComponent({
    setup() {
      const route = useRoute()
      return () =>
        h(RouterView, null, {
          default: ({ Component }: { Component: unknown }) =>
            h(Transition, { name: 'page', mode: 'out-in' }, () =>
              Component
                ? h(KeepAlive, { include: [includeName] }, () =>
                    h(Component as never, { key: route.path }),
                  )
                : null,
            ),
        })
    },
  })
}

function makeFacesRouter() {
  const FacesPage = defineComponent({
    name: 'PublicFacesView',
    setup() {
      const { currentPage, error } = usePaginatedFaces(16)
      return () => h('div', { class: 'faces' }, error.value ?? `p${currentPage.value}`)
    },
  })
  const ProfilePage = defineComponent({
    name: 'PublicFaceProfileView',
    setup: () => () => h('div', 'profile'),
  })

  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/faces', name: 'public-faces-list', component: FacesPage },
      { path: '/faces/:username', name: 'public-face-profile', component: ProfilePage },
    ],
  })
}

function makeMissionsRouter() {
  const MissionsPage = defineComponent({
    name: 'PublicMissionsView',
    setup() {
      const { currentPage, error } = usePaginatedMissions(15)
      return () => h('div', { class: 'missions' }, error.value ?? `p${currentPage.value}`)
    },
  })
  const DetailPage = defineComponent({
    name: 'PublicMissionDetailView',
    setup: () => () => h('div', 'detail'),
  })

  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/missions', name: 'public-missions-list', component: MissionsPage },
      { path: '/missions/:slug', name: 'public-mission-detail', component: DetailPage },
    ],
  })
}

describe('F8 — keep-alive return after a FAILED fetch must retry (error is not sticky)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // The composables log the failure; keep the test output clean.
    vi.spyOn(console, 'error').mockImplementation(() => {})
  })

  it('usePaginatedFaces: retries the fetch on keep-alive return after a network failure', async () => {
    vi.mocked(fetchPublicFaces)
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValue(facesResponse as never)

    const router = makeFacesRouter()
    router.push('/faces?page=2')
    await router.isReady()
    const wrapper = mount(makeApp('PublicFacesView'), { global: { plugins: [router] } })
    await flushPromises()

    // Initial mount: one fetch, it failed, the view shows the error screen.
    expect(fetchPublicFaces).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Une erreur est survenue')

    // Browse away to a profile — expected: the route.name guard blocks any
    // refetch from the deactivated watcher (true before AND after the fix).
    await router.push('/faces/adjoua-dossou?returnTo=%2Ffaces%3Fpage%3D2')
    await flushPromises()
    expect(fetchPublicFaces).toHaveBeenCalledTimes(1)

    // Return to the SAME query. The previous fetch for it FAILED, so the
    // composable must forget its signature and RETRY here. Today the signature
    // set before the await survives the catch → the guard skips → still 1 call
    // and the stale error screen is restored (this expectation is RED).
    await router.push('/faces?page=2')
    await flushPromises()
    expect(fetchPublicFaces).toHaveBeenCalledTimes(2)
    expect(fetchPublicFaces).toHaveBeenLastCalledWith(
      2,
      16,
      {
        categorie: undefined,
        niche: undefined,
        ville: undefined,
        search: undefined,
      },
      // The first attempt failed, so no ranking generation could be pinned.
      null
    )
  })

  it('usePaginatedFaces (witness): NO refetch on keep-alive return after a SUCCESSFUL fetch', async () => {
    vi.mocked(fetchPublicFaces).mockResolvedValue(facesResponse as never)

    const router = makeFacesRouter()
    router.push('/faces?page=2')
    await router.isReady()
    mount(makeApp('PublicFacesView'), { global: { plugins: [router] } })
    await flushPromises()
    expect(fetchPublicFaces).toHaveBeenCalledTimes(1)

    await router.push('/faces/adjoua-dossou?returnTo=%2Ffaces%3Fpage%3D2')
    await flushPromises()
    await router.push('/faces?page=2')
    await flushPromises()

    // Group A guarantee: a successfully-loaded query is served from cache on
    // return. This must hold today AND after the F8 fix (proves the fix does
    // not reopen a systematic refetch).
    expect(fetchPublicFaces).toHaveBeenCalledTimes(1)
  })

  it('usePaginatedMissions: retries the fetch on keep-alive return after a network failure', async () => {
    vi.mocked(fetchPublicMissions)
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValue(missionsResponse as never)

    const router = makeMissionsRouter()
    router.push('/missions?page=2')
    await router.isReady()
    const wrapper = mount(makeApp('PublicMissionsView'), { global: { plugins: [router] } })
    await flushPromises()

    expect(fetchPublicMissions).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Une erreur est survenue')

    await router.push('/missions/some-mission')
    await flushPromises()
    expect(fetchPublicMissions).toHaveBeenCalledTimes(1) // away guard, expected

    // Return on the same query after a FAILED fetch → must retry (RED today).
    await router.push('/missions?page=2')
    await flushPromises()
    expect(fetchPublicMissions).toHaveBeenCalledTimes(2)
    expect(fetchPublicMissions).toHaveBeenLastCalledWith(2, 15, {})
  })

  it('usePaginatedMissions (witness): NO refetch on keep-alive return after a SUCCESSFUL fetch', async () => {
    vi.mocked(fetchPublicMissions).mockResolvedValue(missionsResponse as never)

    const router = makeMissionsRouter()
    router.push('/missions?page=2')
    await router.isReady()
    mount(makeApp('PublicMissionsView'), { global: { plugins: [router] } })
    await flushPromises()
    expect(fetchPublicMissions).toHaveBeenCalledTimes(1)

    await router.push('/missions/some-mission')
    await flushPromises()
    await router.push('/missions?page=2')
    await flushPromises()

    expect(fetchPublicMissions).toHaveBeenCalledTimes(1)
  })
})
