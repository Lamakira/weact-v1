import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  fetchPublicMissions,
  type PublicMission,
} from '../services/publicMissionsApi'
import type { PaginationMeta } from '../services/publicFacesApi'

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

  // State
  const missions = ref<PublicMission[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
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

  /**
   * Load missions for a specific page
   */
  async function loadPage(page: number): Promise<void> {
    const validPage = Math.max(1, page)

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
      const response = await fetchPublicMissions(validPage, perPage)
      // Discard stale responses from superseded requests
      if (currentRequestId !== requestId) return
      missions.value = response.data
      meta.value = response.meta
    } catch (err: unknown) {
      if (currentRequestId !== requestId) return
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
    loadPage(currentPage.value)
  }

  // Watch for URL page change and reload data
  watch(
    () => route.query.page,
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
    missions,
    meta,
    isLoading,
    error,

    // Computed
    currentPage,
    totalPages,
    totalMissions,
    hasNextPage,
    hasPreviousPage,
    isEmpty,

    // Methods
    loadPage,
    nextPage,
    previousPage,
    retry,
  }
}
