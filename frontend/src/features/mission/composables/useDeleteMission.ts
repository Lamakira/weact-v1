import { ref } from 'vue'
import { missionApi } from '../services/missionApi'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

/**
 * Composable for deleting missions
 * Manages deletion state, error handling, and API communication
 */
export function useDeleteMission() {
  const isDeleting = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  /**
   * Delete a mission by ID
   * @param missionId The ID of the mission to delete
   * @returns Promise resolving to success status and message
   */
  async function deleteMission(missionId: string): Promise<{ success: boolean; message: string }> {
    isDeleting.value = true
    error.value = null
    fieldErrors.value = {}

    try {
      const response = await missionApi.deleteMission(missionId)
      return { success: true, message: response.message }
    } catch (err: unknown) {
      const errorDetails = getApiErrorDetails(err)
      error.value = getApiErrorMessage(err)
      fieldErrors.value = errorDetails

      return { success: false, message: error.value }
    } finally {
      isDeleting.value = false
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
    isDeleting,
    error,
    fieldErrors,

    // Actions
    deleteMission,
    resetError,
  }
}
