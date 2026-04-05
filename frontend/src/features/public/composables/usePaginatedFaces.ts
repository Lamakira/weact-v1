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
export function usePaginatedFaces(perPage: number = 15) {
  const route = useRoute()
  const router = useRouter()

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

  // Watch for URL changes (page or filters) and reload data
  watch(
    () => [route.query.page, route.query.categorie, route.query.niche, route.query.ville, route.query.search],
    () => {
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
