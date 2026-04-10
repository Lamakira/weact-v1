import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type {
  CategoryNicheInfo,
  CategoryNicheResult,
  CategoryOption,
  NicheOption,
} from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

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
  const categoryNicheInfo = ref<CategoryNicheInfo | null>(null)
  const categoryOptions = ref<CategoryOption[]>([])
  const nicheOptions = ref<NicheOption[]>([])
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  /**
   * Clear the current error
   */
  function clearError(): void {
    error.value = null
  }

  /**
   * Fetch the current categories and niches
   */
  async function fetchCategoryNiche(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await faceApi.getCategoryNiche()
      categoryNicheInfo.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
    } finally {
      isLoading.value = false
    }
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
      categoryNicheInfo.value = response.data

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
    try {
      const response = await faceApi.getCategoryOptions()
      categoryOptions.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
    }
  }

  /**
   * Fetch niche options for dropdown
   */
  async function fetchNicheOptions(): Promise<void> {
    try {
      const response = await faceApi.getNicheOptions()
      nicheOptions.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
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
