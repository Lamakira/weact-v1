import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type {
  CategoryNicheInfo,
  CategoryNicheResult,
  CategoryOption,
  NicheOption,
} from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const CATEGORY_NICHE_CACHE_TTL_MS = 5 * 60 * 1000
const CATEGORY_OPTIONS_CACHE_TTL_MS = 30 * 60 * 1000
const NICHE_OPTIONS_CACHE_TTL_MS = 30 * 60 * 1000

const categoryNicheResource = createSharedCachedResource<CategoryNicheInfo | null>({
  key: 'face-category-niche',
  initialValue: null,
  ttlMs: CATEGORY_NICHE_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getCategoryNiche()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

const categoryOptionsResource = createSharedCachedResource<CategoryOption[]>({
  key: 'face-category-options',
  initialValue: [],
  ttlMs: CATEGORY_OPTIONS_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getCategoryOptions()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

const nicheOptionsResource = createSharedCachedResource<NicheOption[]>({
  key: 'face-niche-options',
  initialValue: [],
  ttlMs: NICHE_OPTIONS_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getNicheOptions()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseCategoryNicheReturn {
  categoryNicheInfo: Ref<CategoryNicheInfo | null>
  categoryOptions: Ref<CategoryOption[]>
  nicheOptions: Ref<NicheOption[]>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  error: Ref<string | null>
  fetchCategoryNiche: () => Promise<void>
  updateCategoryNiche: (data: {
    categories?: string[] | null
    niches?: string[] | null
  }) => Promise<CategoryNicheResult>
  fetchCategoryOptions: () => Promise<void>
  fetchNicheOptions: () => Promise<void>
  clearError: () => void
}

/**
 * Composable for Face category and niche operations (multi-select)
 */
export function useCategoryNiche(): UseCategoryNicheReturn {
  const categoryNicheInfo = categoryNicheResource.data
  const categoryOptions = categoryOptionsResource.data
  const nicheOptions = nicheOptionsResource.data
  const isLoading = categoryNicheResource.isLoading
  const isSaving = ref(false)
  const error = categoryNicheResource.error

  /**
   * Clear the current error
   */
  function clearError(): void {
    categoryNicheResource.clearError()
  }

  /**
   * Fetch the current categories and niches
   */
  async function fetchCategoryNiche(): Promise<void> {
    await categoryNicheResource.fetch()
  }

  /**
   * Update categories and/or niches (multi-select arrays)
   */
  async function updateCategoryNiche(data: {
    categories?: string[] | null
    niches?: string[] | null
  }): Promise<CategoryNicheResult> {
    isSaving.value = true
    error.value = null

    try {
      const response = await faceApi.updateCategoryNiche(data)
      categoryNicheResource.setData(response.data)

      return {
        success: true,
        data: response.data,
        message: response.message,
      }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      error.value = message

      return {
        success: false,
        errors,
        message,
      }
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Fetch category options for dropdown
   */
  async function fetchCategoryOptions(): Promise<void> {
    error.value = null
    await categoryOptionsResource.fetch()

    if (categoryOptionsResource.error.value) {
      error.value = categoryOptionsResource.error.value
    }
  }

  /**
   * Fetch niche options for dropdown
   */
  async function fetchNicheOptions(): Promise<void> {
    error.value = null
    await nicheOptionsResource.fetch()

    if (nicheOptionsResource.error.value) {
      error.value = nicheOptionsResource.error.value
    }
  }

  return {
    categoryNicheInfo,
    categoryOptions,
    nicheOptions,
    isLoading,
    isSaving,
    error,
    fetchCategoryNiche,
    updateCategoryNiche,
    fetchCategoryOptions,
    fetchNicheOptions,
    clearError,
  }
}
