/**
 * Repro F7 — flag `skipNextWatch` orphelin sous keep-alive après navigation dupliquée.
 *
 * Chaîne du bug (vue-router 4.6.4, sémantique réelle — PAS de mock du router) :
 *  1. `updateUrl()` arme `skipNextWatch = true` PUIS fait `router.replace({ query })`.
 *  2. Si la query construite est IDENTIQUE à l'actuelle (re-clic sur l'onglet de statut
 *     déjà actif — BookingStatusFilter émet inconditionnellement), vue-router court-circuite
 *     en NAVIGATION_DUPLICATED : `finalizeNavigation` n'est jamais appelé, `currentRoute`
 *     n'est pas réassigné, le watcher `watch(() => route.query)` ne tire JAMAIS
 *     → le flag reste armé.
 *  3. Sous keep-alive, l'instance (et son flag) survit à l'aller vers le détail : le tick
 *     d'aller retourne sur la garde `if (route.name !== 'face-bookings') return` AVANT la
 *     consommation du flag.
 *  4. Au RETOUR, le tick tire (nouvelle identité de query), la garde passe, le flag périmé
 *     est consommé → `syncFromUrl()` (donc `fetchBookings`) est SAUTÉ → le refresh-au-retour
 *     est avalé (liste avec statuts périmés).
 *
 * Ce fichier contient :
 *  - un test PRINCIPAL (ROUGE aujourd'hui) : re-clic redondant → détail → retour
 *    ⇒ fetchBookings ne rebouge pas ;
 *  - un test TÉMOIN (VERT aujourd'hui) : même trajet SANS re-clic redondant
 *    ⇒ fetchBookings rebouge (+1) au retour — prouve que le harnais mesure bien le
 *    refresh et que seul le flag orphelin l'avale.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import FaceBookingsListPage from '../FaceBookingsListPage.vue'
import BookingStatusFilter from '@/features/booking/components/BookingStatusFilter.vue'

// ---------------------------------------------------------------------------
// Mock du SEUL composable de données (surface exacte de useBookingsList).
// setStatusFilter pose statusFilter.value SANS appeler fetchBookings : le
// compteur fetchBookings isole ainsi les appels venant de syncFromUrl()
// (mount + watcher de retour) — c'est LA sonde du refresh-au-retour.
// ---------------------------------------------------------------------------
const mockBookings = ref<Record<string, unknown>[]>([])
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockCurrentPage = ref(1)
const mockLastPage = ref(1)
const mockTotal = ref(0)
const mockPerPage = ref(15)
const mockHasNextPage = ref(false)
const mockHasPrevPage = ref(false)
const mockIsEmpty = ref(true)
const mockStatusFilter = ref('')

const mockFetchBookings = vi.fn(async (_page?: number) => {})
const mockSetStatusFilter = vi.fn(async (status: string) => {
  mockStatusFilter.value = status
})
const mockNextPage = vi.fn(async () => {})
const mockPrevPage = vi.fn(async () => {})
const mockGoToPage = vi.fn(async (_page: number) => {})
const mockClearFilter = vi.fn(async () => {})
const mockRefresh = vi.fn(async () => {})

vi.mock('@/features/booking/composables', () => ({
  useBookingsList: () => ({
    bookings: mockBookings,
    isLoading: mockIsLoading,
    error: mockError,
    currentPage: mockCurrentPage,
    lastPage: mockLastPage,
    total: mockTotal,
    perPage: mockPerPage,
    hasNextPage: mockHasNextPage,
    hasPrevPage: mockHasPrevPage,
    isEmpty: mockIsEmpty,
    statusFilter: mockStatusFilter,
    fetchBookings: mockFetchBookings,
    nextPage: mockNextPage,
    prevPage: mockPrevPage,
    goToPage: mockGoToPage,
    setStatusFilter: mockSetStatusFilter,
    clearFilter: mockClearFilter,
    refresh: mockRefresh,
  }),
}))

// ---------------------------------------------------------------------------
// Harnais : VRAI router (memory history) + layout keep-alive reproduisant le
// pattern des layouts de l'app (caching piloté par meta.keepAlive).
// ---------------------------------------------------------------------------
const DetailPageStub = {
  name: 'FaceBookingDetailPageStub',
  template: '<div data-testid="detail-page" />',
}

const RootLayout = {
  name: 'RootLayout',
  template: `
    <router-view v-slot="{ Component }">
      <keep-alive>
        <component :is="Component" v-if="$route.meta.keepAlive" />
      </keep-alive>
      <component :is="Component" v-if="!$route.meta.keepAlive" :key="$route.path" />
    </router-view>
  `,
}

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/face/bookings',
        name: 'face-bookings',
        component: FaceBookingsListPage,
        meta: { keepAlive: true },
      },
      {
        path: '/face/bookings/:id',
        name: 'face-booking-detail',
        component: DetailPageStub,
      },
    ],
  })
}

async function mountApp() {
  const router = makeRouter()
  await router.push('/face/bookings')
  await router.isReady()

  const wrapper = mount(RootLayout, {
    global: {
      plugins: [router],
      stubs: {
        BookingCard: true,
      },
    },
  })
  await flushPromises()

  return { wrapper, router }
}

/**
 * Re-clic REDONDANT : clic réel sur l'onglet de statut DÉJÀ actif ('' = « Tous »).
 * BookingStatusFilter émet inconditionnellement → handleFilterChange('') →
 * setStatusFilter('') (no-op d'état) → updateUrl() → replace vers une query
 * IDENTIQUE ⇒ NAVIGATION_DUPLICATED ⇒ aucun tick du watcher ⇒ flag orphelin.
 */
async function clickAlreadyActiveTab(wrapper: ReturnType<typeof mount>): Promise<void> {
  const filter = wrapper.findComponent(BookingStatusFilter)
  expect(filter.exists()).toBe(true)
  // buttons[0] = option '' (« Tous ») — active par défaut (statusFilter === '').
  await filter.findAll('button')[0]!.trigger('click')
  await flushPromises()
}

describe('FaceBookingsListPage — keep-alive + navigation dupliquée (bug F7)', () => {
  beforeEach(() => {
    mockBookings.value = []
    mockIsLoading.value = false
    mockError.value = null
    mockCurrentPage.value = 1
    mockLastPage.value = 1
    mockTotal.value = 0
    mockIsEmpty.value = true
    mockStatusFilter.value = ''
    mockFetchBookings.mockClear()
    mockSetStatusFilter.mockClear()
  })

  it('resynchronise la liste au retour du détail même après un re-clic redondant sur le filtre actif (ROUGE aujourd\'hui : flag skipNextWatch orphelin)', async () => {
    const { wrapper, router } = await mountApp()

    // Fetch initial (onMounted → syncFromUrl → fetchBookings(1)).
    expect(mockFetchBookings).toHaveBeenCalledTimes(1)

    // 1) RE-CLIC REDONDANT sur l'onglet déjà actif → updateUrl → replace dupliqué.
    await clickAlreadyActiveTab(wrapper)
    expect(mockSetStatusFilter).toHaveBeenCalledTimes(1)
    // Aucune navigation effective : ni tick du watcher, ni fetch supplémentaire.
    expect(mockFetchBookings).toHaveBeenCalledTimes(1)
    expect(router.currentRoute.value.name).toBe('face-bookings')

    // 2) Aller vers un détail : la page liste est cachée par keep-alive (instance
    //    et flag survivent) ; le tick d'aller sort sur la garde route.name.
    await router.push('/face/bookings/123')
    await flushPromises()
    expect(mockFetchBookings).toHaveBeenCalledTimes(1)

    // 3) RETOUR sur la liste : le watcher tire, la garde passe — le refresh
    //    promis DOIT tourner (contrat du fix : plus aucun flag orphelin).
    await router.push('/face/bookings')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('face-bookings')

    // ASSERT PRINCIPAL — ROUGE aujourd'hui : le flag périmé (armé par le replace
    // dupliqué, jamais consommé faute de tick) est consommé au retour à la place
    // de syncFromUrl → fetchBookings reste à 1 au lieu de passer à 2.
    expect(mockFetchBookings).toHaveBeenCalledTimes(2)
    expect(mockFetchBookings).toHaveBeenLastCalledWith(1)
  })

  it('TÉMOIN (vert aujourd\'hui) : sans re-clic redondant, le retour du détail resynchronise bien la liste', async () => {
    const { router } = await mountApp()

    // Fetch initial.
    expect(mockFetchBookings).toHaveBeenCalledTimes(1)

    // Aller vers un détail (pas de re-clic redondant avant) — garde route.name.
    await router.push('/face/bookings/123')
    await flushPromises()
    expect(mockFetchBookings).toHaveBeenCalledTimes(1)

    // Retour : le watcher tire, aucun flag armé → syncFromUrl → refetch.
    await router.push('/face/bookings')
    await flushPromises()

    expect(mockFetchBookings).toHaveBeenCalledTimes(2)
    expect(mockFetchBookings).toHaveBeenLastCalledWith(1)
  })
})
