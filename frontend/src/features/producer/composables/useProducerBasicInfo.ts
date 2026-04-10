import { ref, type Ref } from 'vue'
import { producerApi } from '../services/producerApi'
import type { ProducerBasicInfo, ProducerBasicInfoFormData, ProducerBasicInfoResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const BASIC_INFO_CACHE_TTL_MS = 5 * 60 * 1000

const basicInfoResource = createSharedCachedResource<ProducerBasicInfo | null>({
  key: 'producer-basic-info',
  initialValue: null,
  ttlMs: BASIC_INFO_CACHE_TTL_MS,
  load: async () => {
    const response = await producerApi.getBasicInfo()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseProducerBasicInfoReturn {
  basicInfo: Ref<ProducerBasicInfo | null>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  error: Ref<string | null>
  fetchBasicInfo: () => Promise<void>
  updateBasicInfo: (data: ProducerBasicInfoFormData) => Promise<ProducerBasicInfoResult>
  clearError: () => void
}

/**
 * Composable for Producer basic info operations
 * - For agencies: agency_name
 * - For particuliers: first_name, last_name
 */
export function useProducerBasicInfo(): UseProducerBasicInfoReturn {
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
   * Update basic info
   */
  async function updateBasicInfo(data: ProducerBasicInfoFormData): Promise<ProducerBasicInfoResult> {
    isSaving.value = true
    error.value = null

    try {
      const response = await producerApi.updateBasicInfo(data)
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
