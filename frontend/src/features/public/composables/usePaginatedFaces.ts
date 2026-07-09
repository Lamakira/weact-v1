import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  fetchPublicFaces,
  type PublicFace,
  type PaginationMeta,
  type FacesFilterParams,
} from '../services/publicFacesApi'
import { BENIN_CITY_VALUES } from '@/shared/constants/beninCities'

/**
 * Composable for managing paginated faces list with URL sync and filters
 *
 * Features:
 * - Reactive loading, error, and data states
 * - URL query param sync for pagination and filters
 * - Automatic fetch on page/filter change
 */
const TRACKED_QUERY_KEYS = ['page', 'categorie', 'niche', 'ville', 'search'] as const

export function usePaginatedFaces(perPage: number = 15) {
  const route = useRoute()
  const router = useRouter()

  // Captured at setup — when this page first mounts it IS the active route, so
  // this snapshots the listing's own route name. The query watcher below uses it
  // to tell "I'm the active listing" from "I'm a <keep-alive>-cached page whose
  // query churned because we navigated to a Face profile". Snapshotting avoids
  // hard-coding the route string.
  const listRouteName = route.name

  // Signature of the page+filters the currently-displayed data was fetched for.
  // Lets the watcher skip a redundant refetch when a keep-alive return restores
  // the same query (a refetch would flash the skeleton AND reshuffle the rotated
  // public grid).
  const loadedSignature = ref<string | null>(null)

  function querySignature(): string {
    const normalized: Record<string, string> = {}
    for (const key of TRACKED_QUERY_KEYS) {
      const value = route.query[key]
      if (typeof value === 'string' && value !== '') normalized[key] = value
    }
    return JSON.stringify(normalized)
  }

  function getValidCityFilter(rawCity: unknown): string | undefined {
    if (typeof rawCity !== 'string' || rawCity === '') {
      return undefined
    }

    return BENIN_CITY_VALUES.has(rawCity) ? rawCity : undefined
  }

  // State
  const faces = ref<PublicFace[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  let requestId = 0 // Counter to discard stale responses

  // Computed: read current page from URL
  const currentPage = computed(() => {
    const page = Number(route.query.page) || 1
    return Math.max(1, page)
  })

  // Computed: read current filters from URL
  const filters = computed<FacesFilterParams>(() => ({
    categorie: (route.query.categorie as string) || undefined,
    niche: (route.query.niche as string) || undefined,
    ville: getValidCityFilter(route.query.ville),
    search: (route.query.search as string) || undefined,
  }))

  const hasActiveFilters = computed(() =>
    Boolean(filters.value.categorie || filters.value.niche || filters.value.ville || filters.value.search)
  )

  const totalPages = computed(() => meta.value?.last_page ?? 1)
  const totalItems = computed(() => meta.value?.total ?? 0)
  const hasNextPage = computed(() => currentPage.value < totalPages.value)
  const hasPreviousPage = computed(() => currentPage.value > 1)
  const isEmpty = computed(() => !isLoading.value && faces.value.length === 0)

  /**
   * Load faces for a specific page with current filters
   */
  async function loadPage(page: number): Promise<void> {
    const validPage = Math.max(1, page)
    const currentCity = route.query.ville
    const validCity = getValidCityFilter(currentCity)

    if (typeof currentCity === 'string' && currentCity !== '' && validCity === undefined) {
      await router.replace({
        query: {
          ...route.query,
          ville: undefined,
        },
      })
      return
    }

    // Update URL if page differs from current
    if (validPage !== currentPage.value) {
      await router.push({
        query: {
          ...route.query,
          page: validPage > 1 ? String(validPage) : undefined,
        },
      })
      return // Watch will trigger load
    }

    // Committing to a fetch for this exact query → record its signature so a
    // later keep-alive return with the same query is served from cache.
    loadedSignature.value = querySignature()
    isLoading.value = true
    error.value = null
    const currentRequestId = ++requestId

    try {
      const response = await fetchPublicFaces(validPage, perPage, filters.value)
      // Discard stale responses from superseded requests
      if (currentRequestId !== requestId) return
      faces.value = response.data
      meta.value = response.meta
    } catch (err: unknown) {
      if (currentRequestId !== requestId) return
      console.error('Failed to fetch faces:', err)
      error.value = 'Une erreur est survenue lors du chargement des talents. Veuillez réessayer.'
      faces.value = []
    } finally {
      if (currentRequestId === requestId) {
        isLoading.value = false
      }
    }
  }

  /**
   * Update filters and reset to page 1
   */
  async function updateFilters(newFilters: FacesFilterParams): Promise<void> {
    const query: Record<string, string> = {}
    if (newFilters.categorie) query.categorie = newFilters.categorie
    if (newFilters.niche) query.niche = newFilters.niche
    if (newFilters.ville) query.ville = newFilters.ville
    if (newFilters.search) query.search = newFilters.search
    // Reset page to 1 when filters change (omit page param)

    await router.push({ query })
  }

  /**
   * Clear all filters and reset to page 1
   */
  async function clearFilters(): Promise<void> {
    await router.push({ query: {} })
  }

  function nextPage(): void {
    if (hasNextPage.value) {
      loadPage(currentPage.value + 1)
    }
  }

  function previousPage(): void {
    if (hasPreviousPage.value) {
      loadPage(currentPage.value - 1)
    }
  }

  function retry(): void {
    loadPage(currentPage.value)
  }

  // Watch for URL changes (page or filters) and reload data.
  //
  // This page is cached under <keep-alive>, so this watcher keeps running while
  // the page is off-screen (Vue 3.5 does not pause a deactivated child's
  // watchers). Two query churns must NOT trigger a reload:
  //  - navigating AWAY to a profile drops our query params → route.name is no
  //    longer this listing → a naive reload would refetch page 1 and clobber the
  //    cached grid;
  //  - returning restores the exact same query → the cache is still valid, so a
  //    refetch is pure waste (skeleton flash + reshuffle of the rotated grid).
  // A genuine page/filter change while ON the listing changes the signature and
  // falls through to loadPage. (Pausing on deactivated does NOT work: the
  // pre-flush watcher fires before onDeactivated — see keepAliveQueryGuard.spec.)
  watch(
    () => TRACKED_QUERY_KEYS.map((key) => route.query[key]),
    () => {
      if (route.name !== listRouteName) return
      if (querySignature() === loadedSignature.value) return
      loadPage(currentPage.value)
    }
  )

  // Initial load
  onMounted(() => {
    loadPage(currentPage.value)
  })

  return {
    // State
    faces,
    meta,
    isLoading,
    error,

    // Computed
    currentPage,
    totalPages,
    totalItems,
    hasNextPage,
    hasPreviousPage,
    isEmpty,
    filters,
    hasActiveFilters,

    // Methods
    loadPage,
    nextPage,
    previousPage,
    retry,
    updateFilters,
    clearFilters,
  }
}
