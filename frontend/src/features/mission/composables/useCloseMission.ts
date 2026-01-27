import { ref } from 'vue'
import { missionApi } from '../services/missionApi'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Mission } from '../types'

/**
 * Composable for closing missions
 * Manages closing state, error handling, and API communication
 */
export function useCloseMission() {
  const isClosing = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  /**
   * Close a mission by ID
   * @param missionId The ID of the mission to close
   * @returns Promise resolving to success status, message, and updated mission data
   */
  async function closeMission(
    missionId: number
  ): Promise<{ success: boolean; message: string; data?: Mission }> {
    isClosing.value = true
    error.value = null
    fieldErrors.value = {}

    try {
      const response = await missionApi.closeMission(missionId)
      return { success: true, message: response.message || 'Mission clôturée avec succès', data: response.data }
    } catch (err: unknown) {
      const errorDetails = getApiErrorDetails(err)
      error.value = getApiErrorMessage(err)
      fieldErrors.value = errorDetails

      return { success: false, message: error.value }
    } finally {
      isClosing.value = false
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
    isClosing,
    error,
    fieldErrors,

    // Actions
    closeMission,
    resetError,
  }
}
