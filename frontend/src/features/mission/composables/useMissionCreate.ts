import { ref, type Ref } from 'vue'
import { missionApi } from '../services/missionApi'
import type { Mission, MissionCreateResult, CreateMissionData } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

export interface UseMissionCreateReturn {
  isSubmitting: Ref<boolean>
  error: Ref<string | null>
  validationErrors: Ref<Record<string, string[]>>
  createdMission: Ref<Mission | null>
  createMission: (data: CreateMissionData) => Promise<MissionCreateResult>
  clearError: () => void
  reset: () => void
}

/**
 * Composable for mission creation operations
 */
export function useMissionCreate(): UseMissionCreateReturn {
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]>>({})
  const createdMission = ref<Mission | null>(null)

  /**
   * Clear the current error state
   */
  function clearError(): void {
    error.value = null
    validationErrors.value = {}
  }

  /**
   * Reset all state to initial values
   */
  function reset(): void {
    isSubmitting.value = false
    error.value = null
    validationErrors.value = {}
    createdMission.value = null
  }

  /**
   * Create a new mission
   */
  async function createMission(data: CreateMissionData): Promise<MissionCreateResult> {
    isSubmitting.value = true
    error.value = null
    validationErrors.value = {}

    try {
      const response = await missionApi.createMission(data)
      createdMission.value = response.data

      return {
        success: true,
        data: response.data,
        message: response.message || 'Mission publiée avec succès',
      }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      error.value = message
      validationErrors.value = errors

      return {
        success: false,
        errors,
        message,
      }
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    isSubmitting,
    error,
    validationErrors,
    createdMission,
    createMission,
    clearError,
    reset,
  }
}
