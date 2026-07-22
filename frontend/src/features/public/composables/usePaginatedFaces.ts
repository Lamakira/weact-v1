import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  fetchPublicFaces,
  type PublicFace,
  type PaginationMeta,
  type FacesFilterParams,
} from '../services/publicFacesApi'
import { BENIN_CITY_VALUES } from '@/shared/constants/beninCities'
import { useKeepAliveListingGuard } from './useKeepAliveListingGuard'

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

  // Keep-alive reload guard (rationale in useKeepAliveListingGuard).
  const { markLoaded, markFailed, shouldSkipReload } = useKeepAliveListingGuard(TRACKED_QUERY_KEYS)

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

  // Pinned ranking generation for the public carousel — the listing rotates
  // every few minutes, so paging must stay anchored on the ranking page 1 was
  // served from, otherwise page 2 could repeat or skip Faces.
  //
  // DELIBERATELY a plain `let`, NOT a URL query param and NOT a ref:
  //  - the URL is signed by useKeepAliveListingGuard to decide whether a
  //    keep-alive return must refetch; an extra key there would make every
  //    return look like a genuine query change and refetch for nothing;
  //  - nothing renders it, so reactivity would be pure overhead.
  // Its lifetime is the composable instance's, i.e. the listing component's —
  // and that component is KEPT ALIVE (KeepAliveRouterView), so the pin really
  // survives navigating away and back, for as long as the tab lives. It is
  // dropped only by a filter change or by the server answering with another
  // generation, never by a simple leave/return.
  let pinnedGeneration: number | null = null
  // Filter set the pin belongs to: a different filter set is a different
  // listing, whose first response must re-pin from scratch.
  let pinnedFilterSignature: string | null = null

  function filterSignature(): string {
    const { categorie, niche, ville, search } = filters.value
    return JSON.stringify([categorie ?? '', niche ?? '', ville ?? '', search ?? ''])
  }

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
    markLoaded()
    isLoading.value = true
    error.value = null
    const currentRequestId = ++requestId

    // Filters changed since the pin was taken → drop it, the new listing gets
    // its own reference generation from its own first response.
    const signature = filterSignature()
    if (signature !== pinnedFilterSignature) {
      pinnedFilterSignature = signature
      pinnedGeneration = null
    }

    try {
      const response = await fetchPublicFaces(validPage, perPage, filters.value, pinnedGeneration)
      // Discard stale responses from superseded requests
      if (currentRequestId !== requestId) return
      faces.value = response.data
      meta.value = response.meta
      // Pin the generation the API actually served — and RE-pin whenever it
      // differs from what we asked for. The server arbitrates: a filtered
      // request is always answered from the nightly ranking, the carousel is
      // switched off, or the pinned generation has been purged. Replaying a
      // dead pin forever would make every page come from a different ranking,
      // which is exactly the duplicate/skip the pin exists to prevent.
      if (
        typeof response.meta.generation === 'number' &&
        response.meta.generation !== pinnedGeneration
      ) {
        pinnedGeneration = response.meta.generation
      }
    } catch (err: unknown) {
      if (currentRequestId !== requestId) return
      // Failed query → not loaded (see useKeepAliveListingGuard). Only the most
      // recent request reaches this line — superseded ones returned above — so
      // a fresher request's signature can't be erased.
      markFailed()
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
  // Keep-alive churn (leave/return) must not reload — see useKeepAliveListingGuard.
  watch(
    () => TRACKED_QUERY_KEYS.map((key) => route.query[key]),
    () => {
      if (shouldSkipReload()) return
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
