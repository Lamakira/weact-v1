import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { BasicInfo, BasicInfoFormData, BasicInfoResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const BASIC_INFO_CACHE_TTL_MS = 5 * 60 * 1000

const basicInfoResource = createSharedCachedResource<BasicInfo | null>({
  key: 'face-basic-info',
  initialValue: null,
  ttlMs: BASIC_INFO_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getBasicInfo()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseBasicInfoReturn {
  basicInfo: Ref<BasicInfo | null>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  error: Ref<string | null>
  fetchBasicInfo: () => Promise<void>
  updateBasicInfo: (data: BasicInfoFormData) => Promise<BasicInfoResult>
  clearError: () => void
}

/**
 * Composable for Face basic info operations (nom, prenom, username)
 */
export function useBasicInfo(): UseBasicInfoReturn {
  const basicInfo = basicInfoResource.data
  const isLoading = basicInfoResource.isLoading
  const isSaving = ref(false)
  const error = basicInfoResource.error

  /**
   * Clear the current error
   */
  function clearError(): void {
    basicInfoResource.clearError()
  }

  /**
   * Fetch the current basic info
   */
  async function fetchBasicInfo(): Promise<void> {
    await basicInfoResource.fetch()
  }

  /**
   * Update basic info (nom, prenom, username)
   */
  async function updateBasicInfo(data: BasicInfoFormData): Promise<BasicInfoResult> {
    isSaving.value = true
    error.value = null

    try {
      const response = await faceApi.updateBasicInfo(data)
      basicInfoResource.setData(response.data)

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

  return {
    basicInfo,
    isLoading,
    isSaving,
    error,
    fetchBasicInfo,
    updateBasicInfo,
    clearError,
  }
}
