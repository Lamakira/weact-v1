import { ref, computed, watch } from 'vue'
import {
  useRoute,
  useRouter,
  type LocationQuery,
  type LocationQueryRaw,
} from 'vue-router'
import {
  fetchPublicMissions,
  type PublicMission,
  type PublicMissionFilters,
} from '../services/publicMissionsApi'
import type { PaginationMeta } from '../services/publicFacesApi'
import { useKeepAliveListingGuard } from './useKeepAliveListingGuard'

/**
 * Composable for managing paginated missions list with URL sync
 *
 * Features:
 * - Reactive loading, error, and data states
 * - URL query param sync for pagination
 * - Stale request prevention with requestId counter
 * - Automatic fetch on page change
 */
export function usePaginatedMissions(perPage: number = 15) {
  const route = useRoute()
  const router = useRouter()
  // Also consumed by buildQuery below to preserve untracked query params.
  const trackedQueryKeys = ['page', 'type_mission', 'lieu', 'budget_min', 'budget_max'] as const

  // Keep-alive reload guard (rationale in useKeepAliveListingGuard).
  const { signatureOf, markLoaded, markFailed, shouldSkipReload } =
    useKeepAliveListingGuard(trackedQueryKeys)

  // State
  const missions = ref<PublicMission[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const filters = ref<PublicMissionFilters>({})
  let requestId = 0 // Counter to discard stale responses

  // Computed: read current page from URL
  const currentPage = computed((): number => {
    const page = Number(route.query.page) || 1
    return Math.max(1, page)
  })

  const totalPages = computed((): number => meta.value?.last_page ?? 1)
  const totalMissions = computed((): number => meta.value?.total ?? 0)
  const hasNextPage = computed((): boolean => currentPage.value < totalPages.value)
  const hasPreviousPage = computed((): boolean => currentPage.value > 1)
  const isEmpty = computed((): boolean => !isLoading.value && missions.value.length === 0)
  const hasActiveFilters = computed(() =>
    Object.values(filters.value).some((value) => value !== undefined && value !== null && value !== '')
  )

  function parsePositiveNumber(value: unknown): number | undefined {
    if (typeof value !== 'string' || value.trim() === '') return undefined

    const parsed = Number(value)

    if (!Number.isFinite(parsed) || parsed < 0) {
      return undefined
    }

    return parsed
  }

  function normalizeFilters(input: PublicMissionFilters): PublicMissionFilters {
    const normalized: PublicMissionFilters = {}

    if (input.type_mission) normalized.type_mission = input.type_mission

    const lieu = input.lieu?.trim()
    if (lieu) normalized.lieu = lieu

    if (typeof input.budget_min === 'number' && Number.isFinite(input.budget_min) && input.budget_min >= 0) {
      normalized.budget_min = input.budget_min
    }

    if (typeof input.budget_max === 'number' && Number.isFinite(input.budget_max) && input.budget_max >= 0) {
      normalized.budget_max = input.budget_max
    }

    return normalized
  }

  function parseFiltersFromQuery(query: LocationQuery): PublicMissionFilters {
    return normalizeFilters({
      type_mission: typeof query.type_mission === 'string' ? query.type_mission : undefined,
      lieu: typeof query.lieu === 'string' ? query.lieu : undefined,
      budget_min: parsePositiveNumber(query.budget_min),
      budget_max: parsePositiveNumber(query.budget_max),
    })
  }

  function buildQuery(
    page: number = currentPage.value,
    activeFilters: PublicMissionFilters = filters.value,
  ): LocationQueryRaw {
    const query: LocationQueryRaw = {}
    const normalizedFilters = normalizeFilters(activeFilters)
    const currentQuery = route.query as Record<string, unknown>

    Object.entries(currentQuery).forEach(([key, value]) => {
      if (!trackedQueryKeys.includes(key as (typeof trackedQueryKeys)[number])) {
        query[key] = value as string | string[] | null | undefined
      }
    })

    if (page > 1) query.page = String(page)
    if (normalizedFilters.type_mission) query.type_mission = normalizedFilters.type_mission
    if (normalizedFilters.lieu) query.lieu = normalizedFilters.lieu
    if (normalizedFilters.budget_min != null) query.budget_min = String(normalizedFilters.budget_min)
    if (normalizedFilters.budget_max != null) query.budget_max = String(normalizedFilters.budget_max)

    return query
  }

  /**
   * Load missions for a specific page
   */
  async function loadPage(page: number): Promise<void> {
    const validPage = Math.max(1, page)
    const nextQuery = buildQuery(validPage)

    if (signatureOf(nextQuery) !== signatureOf(route.query)) {
      await router.push({
        query: nextQuery,
      })
      return
    }

    await fetchMissions(validPage, filters.value)
  }

  async function fetchMissions(
    page: number = currentPage.value,
    activeFilters: PublicMissionFilters = filters.value,
  ): Promise<void> {
    // Record the query we're fetching for so a later keep-alive return with the
    // same query is served from cache rather than refetched.
    markLoaded()
    isLoading.value = true
    error.value = null
    const currentRequestId = ++requestId

    try {
      const response = await fetchPublicMissions(page, perPage, activeFilters)
      // Discard stale responses from superseded requests
      if (currentRequestId !== requestId) return
      missions.value = response.data
      meta.value = response.meta
    } catch (err: unknown) {
      if (currentRequestId !== requestId) return
      // Failed query → not loaded (see useKeepAliveListingGuard).
      markFailed()
      console.error('Failed to fetch missions:', err)
      error.value = 'Une erreur est survenue lors du chargement des missions. Veuillez réessayer.'
      missions.value = []
      meta.value = null
    } finally {
      if (currentRequestId === requestId) {
        isLoading.value = false
      }
    }
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
    void fetchMissions(currentPage.value, filters.value)
  }

  function updateFilters(newFilters: PublicMissionFilters): void {
    const normalizedFilters = normalizeFilters(newFilters)
    const nextQuery = buildQuery(1, normalizedFilters)

    if (signatureOf(nextQuery) === signatureOf(route.query)) {
      filters.value = normalizedFilters
      void fetchMissions(1, normalizedFilters)
      return
    }

    void router.replace({ query: nextQuery })
  }

  // Watch the route query to keep URL and data in sync.
  // Keep-alive churn (leave/return) must not reload — see useKeepAliveListingGuard.
  // `immediate` still runs on first mount, where route.name IS this listing and
  // the signature differs from null.
  watch(
    () => trackedQueryKeys.map((key) => route.query[key]),
    () => {
      if (shouldSkipReload()) return
      filters.value = parseFiltersFromQuery(route.query)
      void fetchMissions(currentPage.value, filters.value)
    },
    { immediate: true }
  )

  return {
    // State
    missions,
    meta,
    isLoading,
    error,
    filters,

    // Computed
    currentPage,
    totalPages,
    totalMissions,
    hasNextPage,
    hasPreviousPage,
    isEmpty,
    hasActiveFilters,

    // Methods
    loadPage,
    nextPage,
    previousPage,
    retry,
    updateFilters,
  }
}
