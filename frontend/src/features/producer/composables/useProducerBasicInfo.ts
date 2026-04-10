import { ref, type Ref } from 'vue'
import { producerApi } from '../services/producerApi'
import type { ProducerBasicInfo, ProducerBasicInfoFormData, ProducerBasicInfoResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

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
  const basicInfo = ref<ProducerBasicInfo | null>(null)
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
   * Fetch the current basic info
   */
  async function fetchBasicInfo(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await producerApi.getBasicInfo()
      basicInfo.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update basic info
   */
  async function updateBasicInfo(data: ProducerBasicInfoFormData): Promise<ProducerBasicInfoResult> {
    isSaving.value = true
    error.value = null

    try {
      const response = await producerApi.updateBasicInfo(data)
      basicInfo.value = response.data

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
