import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  fetchPublicArticles,
  type PublicArticle,
} from '../services/publicArticlesApi'
import type { PaginationMeta } from '../services/publicFacesApi'

/**
 * Composable for managing paginated articles list with URL sync and category filter
 *
 * Features:
 * - Reactive loading, error, and data states
 * - URL query param sync for pagination and category filter
 * - Stale request prevention with requestId counter
 * - Automatic fetch on page/category change
 */
export function usePaginatedArticles(perPage: number = 15) {
  const route = useRoute()
  const router = useRouter()

  // State
  const articles = ref<PublicArticle[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  let requestId = 0

  // Computed: read current page and category from URL
  const currentPage = computed((): number => {
    const page = Number(route.query.page) || 1
    return Math.max(1, page)
  })

  const currentCategory = computed((): string | undefined => {
    const cat = route.query.category
    return typeof cat === 'string' && cat ? cat : undefined
  })

  const totalPages = computed((): number => meta.value?.last_page ?? 1)
  const totalArticles = computed((): number => meta.value?.total ?? 0)
  const hasNextPage = computed((): boolean => currentPage.value < totalPages.value)
  const hasPreviousPage = computed((): boolean => currentPage.value > 1)
  const isEmpty = computed((): boolean => !isLoading.value && articles.value.length === 0)

  /**
   * Load articles for a specific page and optional category filter
   */
  async function loadPage(page: number, category?: string): Promise<void> {
    const validPage = Math.max(1, page)

    // Update URL if page or category differs
    const newQuery: Record<string, string | undefined> = {
      ...route.query as Record<string, string | undefined>,
      page: validPage > 1 ? String(validPage) : undefined,
      category: category || undefined,
    }

    const pageChanged = validPage !== currentPage.value
    const categoryChanged = category !== currentCategory.value

    if (pageChanged || categoryChanged) {
      await router.push({ query: newQuery })
      return // Watch will trigger load
    }

    isLoading.value = true
    error.value = null
    const currentRequestId = ++requestId

    try {
      const response = await fetchPublicArticles(validPage, perPage, category)
      if (currentRequestId !== requestId) return
      articles.value = response.data
      meta.value = response.meta
    } catch (err: unknown) {
      if (currentRequestId !== requestId) return
      console.error('Failed to fetch articles:', err)
      error.value = 'Une erreur est survenue lors du chargement des articles. Veuillez réessayer.'
      articles.value = []
      meta.value = null
    } finally {
      if (currentRequestId === requestId) {
        isLoading.value = false
      }
    }
  }

  function nextPage(): void {
    if (hasNextPage.value) {
      loadPage(currentPage.value + 1, currentCategory.value)
    }
  }

  function previousPage(): void {
    if (hasPreviousPage.value) {
      loadPage(currentPage.value - 1, currentCategory.value)
    }
  }

  function filterByCategory(category: string | undefined): void {
    loadPage(1, category)
  }

  function retry(): void {
    loadPage(currentPage.value, currentCategory.value)
  }

  // Watch for URL changes and reload
  watch(
    () => [route.query.page, route.query.category],
    () => {
      loadPage(currentPage.value, currentCategory.value)
    },
  )

  // Initial load
  onMounted(() => {
    loadPage(currentPage.value, currentCategory.value)
  })

  return {
    articles,
    meta,
    isLoading,
    error,
    currentPage,
    currentCategory,
    totalPages,
    totalArticles,
    hasNextPage,
    hasPreviousPage,
    isEmpty,
    loadPage,
    nextPage,
    previousPage,
    filterByCategory,
    retry,
  }
}
