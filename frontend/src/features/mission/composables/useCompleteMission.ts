import { ref } from 'vue'
import { missionApi } from '../services/missionApi'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Mission } from '../types'

/**
 * Composable for marking missions as completed
 * Manages completing state, error handling, and API communication
 */
export function useCompleteMission() {
  const isCompleting = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  /**
   * Mark a mission as completed by ID
   * @param missionId The ID of the mission to complete
   * @returns Promise resolving to success status, message, and updated mission data
   */
  async function completeMission(
    missionId: string
  ): Promise<{ success: boolean; message: string; data?: Mission }> {
    isCompleting.value = true
    error.value = null
    fieldErrors.value = {}

    try {
      const response = await missionApi.completeMission(missionId)
      return { success: true, message: response.message || 'Mission marquée comme terminée', data: response.data }
    } catch (err: unknown) {
      const errorDetails = getApiErrorDetails(err)
      error.value = getApiErrorMessage(err)
      fieldErrors.value = errorDetails

      return { success: false, message: error.value }
    } finally {
      isCompleting.value = false
    }
  }

  /**
   * Reset error state
   */
  function resetError(): void {
    error.value = null
    fieldErrors.value = {}
  }

  return {
    // State
    isCompleting,
    error,
    fieldErrors,

    // Actions
    completeMission,
    resetError,
  }
}
