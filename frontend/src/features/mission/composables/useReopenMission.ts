import { ref } from 'vue'
import { missionApi } from '../services/missionApi'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Mission } from '../types'

/**
 * Composable for reopening closed missions
 * Manages reopening state, error handling, and API communication
 */
export function useReopenMission() {
  const isReopening = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  /**
   * Reopen a mission by ID
   * @param missionId The ID of the mission to reopen
   * @returns Promise resolving to success status, message, and updated mission data
   */
  async function reopenMission(
    missionId: string
  ): Promise<{ success: boolean; message: string; data?: Mission }> {
    isReopening.value = true
    error.value = null
    fieldErrors.value = {}

    try {
      const response = await missionApi.reopenMission(missionId)
      return { success: true, message: response.message || 'Mission réouverte avec succès', data: response.data }
    } catch (err: unknown) {
      const errorDetails = getApiErrorDetails(err)
      error.value = getApiErrorMessage(err)
      fieldErrors.value = errorDetails

      return { success: false, message: error.value }
    } finally {
      isReopening.value = false
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
    isReopening,
    error,
    fieldErrors,

    // Actions
    reopenMission,
    resetError,
  }
}
