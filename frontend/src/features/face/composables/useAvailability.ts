import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { AvailabilityInfo, AvailabilityResult, AvailabilityFormData } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

interface UseAvailabilityReturn {
  availabilityInfo: Ref<AvailabilityInfo | null>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  error: Ref<string | null>
  fetchAvailability: () => Promise<void>
  updateAvailability: (data: AvailabilityFormData) => Promise<AvailabilityResult>
  toggleAvailability: () => Promise<AvailabilityResult>
  clearError: () => void
}

/**
 * Composable for Face availability operations
 */
export function useAvailability(): UseAvailabilityReturn {
  const availabilityInfo = ref<AvailabilityInfo | null>(null)
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
   * Fetch the current availability status
   */
  async function fetchAvailability(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await faceApi.getAvailability()
      availabilityInfo.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update availability status
   */
  async function updateAvailability(data: AvailabilityFormData): Promise<AvailabilityResult> {
    isSaving.value = true
    error.value = null

    // Store previous state for rollback
    const previousState = availabilityInfo.value
      ? { ...availabilityInfo.value }
      : null

    // Optimistic update (always apply, even on first load)
    availabilityInfo.value = {
      is_available: data.is_available,
      availability_badge: data.is_available ? 'Disponible' : 'Indisponible',
      availability_badge_color: data.is_available ? 'green' : 'grey',
    }

    try {
      const response = await faceApi.updateAvailability(data)
      availabilityInfo.value = response.data

      return {
        success: true,
        data: response.data,
        message: response.message,
      }
    } catch (err) {
      // Rollback on error
      if (previousState) {
        availabilityInfo.value = previousState
      }

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

  /**
   * Toggle availability status (inverts current status)
   */
  async function toggleAvailability(): Promise<AvailabilityResult> {
    const currentStatus = availabilityInfo.value?.is_available ?? true
    return updateAvailability({ is_available: !currentStatus })
  }

  return {
    availabilityInfo,
    isLoading,
    isSaving,
    error,
    fetchAvailability,
    updateAvailability,
    toggleAvailability,
    clearError,
  }
}
