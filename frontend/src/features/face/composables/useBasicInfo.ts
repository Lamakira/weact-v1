import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { BasicInfo, BasicInfoFormData, BasicInfoResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

export interface UseBasicInfoReturn {
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
  const basicInfo = ref<BasicInfo | null>(null)
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
      const response = await faceApi.getBasicInfo()
      basicInfo.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update basic info (nom, prenom, username)
   */
  async function updateBasicInfo(data: BasicInfoFormData): Promise<BasicInfoResult> {
    isSaving.value = true
    error.value = null

    try {
      const response = await faceApi.updateBasicInfo(data)
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
