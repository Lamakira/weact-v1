/**
 * Prove-it regression test for Group A (public browse-and-return listings).
 *
 * The listings (/faces, /missions, /ressources) are cached in App.vue via
 * <keep-alive :include>. Their pagination composables sync state FROM route.query
 * with a watcher. Under keep-alive that watcher keeps running while the page is
 * off-screen (Vue 3.5 does not pause a deactivated child's watchers), so it must
 * NOT react to the two query churns that happen around a detail visit:
 *
 *   1. navigating AWAY to a detail drops the listing's query params — a naive
 *      reload would refetch page 1 and clobber the cached grid;
 *   2. returning restores the exact same query — a refetch there is pure waste
 *      (skeleton flash + a reshuffle of the rotation-ordered public Faces grid).
 *
 * The fix combines a route.name guard (handles #1) with a "skip if the query
 * signature is unchanged" guard (handles #2). This test drives the REAL
 * composables inside a <keep-alive> with a real router and asserts the fetch is
 * called on the initial load and on a genuine page change, but NOT on the away
 * churn nor on the return. It also asserts the listing instance is cached
 * (mounted exactly once).
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { defineComponent, h, onMounted, KeepAlive, Transition } from 'vue'
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
  // `generation` = the rotating public ranking the page was served from. It is
  // pinned in the composable's local state and replayed on the NEXT page — it
  // must never appear in the URL, whose signature drives this very guard.
  meta: { current_page: 2, last_page: 3, per_page: 16, total: 40, generation: 7 },
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

describe('Group A public listing kept alive across a browse-and-return', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('usePaginatedFaces: loads once, no refetch on away/return, refetches on page change', async () => {
    vi.mocked(fetchPublicFaces).mockResolvedValue(facesResponse as never)
    const listMounted = vi.fn()

    const FacesPage = defineComponent({
      name: 'PublicFacesView',
      setup() {
        onMounted(listMounted)
        const { currentPage } = usePaginatedFaces(16)
        return () => h('div', { class: 'faces' }, `p${currentPage.value}`)
      },
    })
    const ProfilePage = defineComponent({
      name: 'PublicFaceProfileView',
      setup: () => () => h('div', 'profile'),
    })

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/faces', name: 'public-faces-list', component: FacesPage },
        { path: '/faces/:username', name: 'public-face-profile', component: ProfilePage },
      ],
    })

    router.push('/faces?page=2')
    await router.isReady()
    mount(makeApp('PublicFacesView'), { global: { plugins: [router] } })
    await flushPromises()

    // Initial mount loads page 2 exactly once.
    expect(fetchPublicFaces).toHaveBeenCalledTimes(1)
    expect(fetchPublicFaces).toHaveBeenLastCalledWith(
      2,
      16,
      {
        categorie: undefined,
        niche: undefined,
        ville: undefined,
        search: undefined,
      },
      // Nothing pinned yet on the first request of the session.
      null
    )

    // Browse to a profile — the FaceCard link drops the listing's query params.
    await router.push('/faces/adjoua-dossou?returnTo=%2Ffaces%3Fpage%3D2')
    await flushPromises()
    // Away guard (route.name): the deactivated watcher must NOT refetch page 1.
    expect(fetchPublicFaces).toHaveBeenCalledTimes(1)

    // Back to the same listing state.
    await router.push('/faces?page=2')
    await flushPromises()
    // Signature guard: same query as displayed → served from the cache, no refetch.
    expect(fetchPublicFaces).toHaveBeenCalledTimes(1)

    // A genuine page change while ON the listing DOES refetch.
    await router.push('/faces?page=3')
    await flushPromises()
    expect(fetchPublicFaces).toHaveBeenCalledTimes(2)
    expect(fetchPublicFaces).toHaveBeenLastCalledWith(
      3,
      16,
      {
        categorie: undefined,
        niche: undefined,
        ville: undefined,
        search: undefined,
      },
      // The pinned generation rides on the HTTP request only…
      7
    )
    // …and NOT on the URL: the router query the guard signs stays page-only.
    expect(router.currentRoute.value.query).toEqual({ page: '3' })

    // The listing was cached, never remounted, across the whole trip.
    expect(listMounted).toHaveBeenCalledTimes(1)
  })

  it('usePaginatedMissions (immediate watcher): same guarantees', async () => {
    vi.mocked(fetchPublicMissions).mockResolvedValue(missionsResponse as never)
    const listMounted = vi.fn()

    const MissionsPage = defineComponent({
      name: 'PublicMissionsView',
      setup() {
        onMounted(listMounted)
        const { currentPage } = usePaginatedMissions(15)
        return () => h('div', { class: 'missions' }, `p${currentPage.value}`)
      },
    })
    const DetailPage = defineComponent({
      name: 'PublicMissionDetailView',
      setup: () => () => h('div', 'detail'),
    })

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/missions', name: 'public-missions-list', component: MissionsPage },
        { path: '/missions/:slug', name: 'public-mission-detail', component: DetailPage },
      ],
    })

    router.push('/missions?page=2')
    await router.isReady()
    mount(makeApp('PublicMissionsView'), { global: { plugins: [router] } })
    await flushPromises()

    // The immediate watcher loads page 2 once on mount.
    expect(fetchPublicMissions).toHaveBeenCalledTimes(1)
    expect(fetchPublicMissions).toHaveBeenLastCalledWith(2, 15, {})

    await router.push('/missions/some-mission')
    await flushPromises()
    expect(fetchPublicMissions).toHaveBeenCalledTimes(1) // away guard

    await router.push('/missions?page=2')
    await flushPromises()
    expect(fetchPublicMissions).toHaveBeenCalledTimes(1) // signature guard

    await router.push('/missions?page=3')
    await flushPromises()
    expect(fetchPublicMissions).toHaveBeenCalledTimes(2) // genuine change
    expect(fetchPublicMissions).toHaveBeenLastCalledWith(3, 15, {})

    expect(listMounted).toHaveBeenCalledTimes(1)
  })
})
