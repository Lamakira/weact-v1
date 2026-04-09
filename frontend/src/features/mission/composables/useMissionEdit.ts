import { ref, type Ref } from 'vue'
import { missionApi } from '../services/missionApi'
import type { Mission, MissionUpdateResult, UpdateMissionData } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

export interface UseMissionEditReturn {
  isLoading: Ref<boolean>
  isSubmitting: Ref<boolean>
  error: Ref<string | null>
  validationErrors: Ref<Record<string, string[]>>
  mission: Ref<Mission | null>
  loadMission: (id: string) => Promise<boolean>
  updateMission: (id: string, data: UpdateMissionData) => Promise<MissionUpdateResult>
  clearError: () => void
  reset: () => void
}

/**
 * Composable for mission edit operations
 */
export function useMissionEdit(): UseMissionEditReturn {
  const isLoading = ref(false)
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]>>({})
  const mission = ref<Mission | null>(null)

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
    isLoading.value = false
    isSubmitting.value = false
    error.value = null
    validationErrors.value = {}
    mission.value = null
  }

  /**
   * Load a mission by ID
   */
  async function loadMission(id: string): Promise<boolean> {
    isLoading.value = true
    error.value = null

    try {
      const response = await missionApi.getMission(id)
      mission.value = response.data
      return true
    } catch (err) {
      const message = getApiErrorMessage(err)
      error.value = message
      return false
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update an existing mission
   */
  async function updateMission(id: string, data: UpdateMissionData): Promise<MissionUpdateResult> {
    isSubmitting.value = true
    error.value = null
    validationErrors.value = {}

    try {
      const response = await missionApi.updateMission(id, data)
      mission.value = response.data

      return {
        success: true,
        data: response.data,
        message: response.message || 'Mission modifiée avec succès',
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
    isLoading,
    isSubmitting,
    error,
    validationErrors,
    mission,
    loadMission,
    updateMission,
    clearError,
    reset,
  }
}
