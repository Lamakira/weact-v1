import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  fetchPublicFaces,
  type PublicFace,
  type PaginationMeta,
} from '../services/publicFacesApi'

/**
 * Composable for managing paginated faces list with URL sync
 *
 * Features:
 * - Reactive loading, error, and data states
 * - URL query param sync for pagination
 * - Automatic fetch on page change
 */
export function usePaginatedFaces(perPage: number = 15) {
  const route = useRoute()
  const router = useRouter()

  // State
  const faces = ref<PublicFace[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Computed properties
  const currentPage = computed(() => {
    const page = Number(route.query.page) || 1
    return Math.max(1, page)
  })

  const totalPages = computed(() => meta.value?.last_page ?? 1)
  const totalItems = computed(() => meta.value?.total ?? 0)
  const hasNextPage = computed(() => currentPage.value < totalPages.value)
  const hasPreviousPage = computed(() => currentPage.value > 1)
  const isEmpty = computed(() => !isLoading.value && faces.value.length === 0)

  /**
   * Load faces for a specific page
   */
  async function loadPage(page: number): Promise<void> {
    // Validate page number
    const validPage = Math.max(1, page)

    // Update URL if different from current
    if (validPage !== currentPage.value) {
      await router.push({
        query: { ...route.query, page: validPage > 1 ? String(validPage) : undefined },
      })
      return // Watch will trigger load
    }

    isLoading.value = true
    error.value = null

    try {
      const response = await fetchPublicFaces(validPage, perPage)
      faces.value = response.data
      meta.value = response.meta
    } catch (err) {
      console.error('Failed to fetch faces:', err)
      error.value = 'Une erreur est survenue lors du chargement des talents. Veuillez réessayer.'
      faces.value = []
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Go to next page
   */
  function nextPage(): void {
    if (hasNextPage.value) {
      loadPage(currentPage.value + 1)
    }
  }

  /**
   * Go to previous page
   */
  function previousPage(): void {
    if (hasPreviousPage.value) {
      loadPage(currentPage.value - 1)
    }
  }

  /**
   * Retry loading current page after error
   */
  function retry(): void {
    loadPage(currentPage.value)
  }

  // Watch for URL changes and reload data
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

    // Methods
    loadPage,
    nextPage,
    previousPage,
    retry,
  }
}
