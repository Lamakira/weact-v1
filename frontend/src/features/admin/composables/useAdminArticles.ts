import { ref, type Ref } from 'vue'
import {
  adminArticlesApi,
  type AdminArticleData,
  type AdminArticleListParams,
  type CreateArticleForm,
  type UpdateArticleForm,
} from '../services/adminArticlesApi'
import { getApiErrorDetails, getApiErrorMessage } from '../services/adminAuthApi'

interface AdminArticleActionResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

/**
 * Composable for admin article management operations
 */
export function useAdminArticles() {
  const articles: Ref<AdminArticleData[]> = ref([])
  const article: Ref<AdminArticleData | null> = ref(null)
  const pagination: Ref<PaginationMeta | null> = ref(null)
  const isLoading = ref(false)
  const error: Ref<string | null> = ref(null)

  /**
   * Fetch paginated list of articles with optional search/filters
   */
  async function fetchArticles(params?: AdminArticleListParams): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminArticlesApi.getArticles(params)
      articles.value = response.data
      pagination.value = response.meta
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      articles.value = []
      pagination.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Fetch a single article by ID
   */
  async function fetchArticle(id: string): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminArticlesApi.getArticle(id)
      article.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      article.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Create a new article
   */
  async function createArticle(data: CreateArticleForm): Promise<AdminArticleActionResult> {
    isLoading.value = true

    try {
      const response = await adminArticlesApi.createArticle(data)
      article.value = response.data
      return { success: true, message: response.message }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      return { success: false, errors, message }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update an existing article
   */
  async function updateArticle(
    id: string,
    data: UpdateArticleForm,
  ): Promise<AdminArticleActionResult> {
    isLoading.value = true

    try {
      const response = await adminArticlesApi.updateArticle(id, data)
      article.value = response.data
      return { success: true, message: response.message }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      return { success: false, errors, message }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Delete an article
   */
  async function deleteArticle(id: string): Promise<AdminArticleActionResult> {
    isLoading.value = true

    try {
      const response = await adminArticlesApi.deleteArticle(id)
      return { success: true, message: response.message }
    } catch (err) {
      const message = getApiErrorMessage(err)
      return { success: false, message }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Toggle article status (draft ↔ published)
   */
  async function toggleStatus(id: string, currentStatus: string): Promise<AdminArticleActionResult> {
    isLoading.value = true

    try {
      const newStatus = currentStatus === 'draft' ? 'published' : 'draft'
      const response = await adminArticlesApi.updateArticleStatus(id, newStatus)
      // Update the article in the list if present
      const index = articles.value.findIndex((a) => a.id === id)
      if (index !== -1) {
        articles.value[index] = response.data
      }
      article.value = response.data
      return { success: true, message: response.message }
    } catch (err) {
      const message = getApiErrorMessage(err)
      return { success: false, message }
    } finally {
      isLoading.value = false
    }
  }

  return {
    articles,
    article,
    pagination,
    isLoading,
    error,
    fetchArticles,
    fetchArticle,
    createArticle,
    updateArticle,
    deleteArticle,
    toggleStatus,
  }
}
