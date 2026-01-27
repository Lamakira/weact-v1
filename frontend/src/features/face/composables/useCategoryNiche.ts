import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type {
  CategoryNicheInfo,
  CategoryNicheResult,
  FaceCategory,
  FaceNiche,
  CategoryOption,
  NicheOption,
} from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

export interface UseCategoryNicheReturn {
  categoryNicheInfo: Ref<CategoryNicheInfo | null>
  categoryOptions: Ref<CategoryOption[]>
  nicheOptions: Ref<NicheOption[]>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  error: Ref<string | null>
  fetchCategoryNiche: () => Promise<void>
  updateCategoryNiche: (data: {
    categorie?: FaceCategory | null
    niche?: FaceNiche | null
  }) => Promise<CategoryNicheResult>
  fetchCategoryOptions: () => Promise<void>
  fetchNicheOptions: () => Promise<void>
  clearError: () => void
  validateCategory: (categorie: FaceCategory | null) => { valid: boolean; error?: string }
  validateNiche: (niche: FaceNiche | null) => { valid: boolean; error?: string }
}

/**
 * Composable for Face category and niche operations
 */
export function useCategoryNiche(): UseCategoryNicheReturn {
  const categoryNicheInfo = ref<CategoryNicheInfo | null>(null)
  const categoryOptions = ref<CategoryOption[]>([])
  const nicheOptions = ref<NicheOption[]>([])
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  /**
   * Validate category value
   * Note: Full validation is done by the backend. This function
   * only performs basic null checks. Invalid enum values will be
   * rejected by the backend with appropriate French error messages.
   */
  function validateCategory(_categorie: FaceCategory | null): { valid: boolean; error?: string } {
    // Delegate to backend for enum validation
    return { valid: true }
  }

  /**
   * Validate niche value
   * Note: Full validation is done by the backend. This function
   * only performs basic null checks. Invalid enum values will be
   * rejected by the backend with appropriate French error messages.
   */
  function validateNiche(_niche: FaceNiche | null): { valid: boolean; error?: string } {
    // Delegate to backend for enum validation
    return { valid: true }
  }

  /**
   * Clear the current error
   */
  function clearError(): void {
    error.value = null
  }

  /**
   * Fetch the current category and niche
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
   * Update category and/or niche
   * Note: Enum validation is delegated to the backend which returns
   * appropriate French error messages for invalid values.
   */
  async function updateCategoryNiche(data: {
    categorie?: FaceCategory | null
    niche?: FaceNiche | null
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
    validateCategory,
    validateNiche,
  }
}
