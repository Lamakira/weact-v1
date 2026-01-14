import { ref, computed, type Ref, type ComputedRef } from 'vue'
import { faceApi } from '../services/faceApi'
import type { ProfileCompletionInfo, ProfileCompletionMissingItem } from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

export interface UseProfileCompletionReturn {
  completionInfo: Ref<ProfileCompletionInfo | null>
  isLoading: Ref<boolean>
  error: Ref<string | null>
  fetchCompletion: () => Promise<void>
  percentage: ComputedRef<number>
  missingItems: ComputedRef<ProfileCompletionMissingItem[]>
  isComplete: ComputedRef<boolean>
  clearError: () => void
}

/**
 * Composable for Face profile completion operations
 */
export function useProfileCompletion(): UseProfileCompletionReturn {
  const completionInfo = ref<ProfileCompletionInfo | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  /**
   * Clear the current error
   */
  function clearError(): void {
    error.value = null
  }

  /**
   * Fetch the current profile completion status
   */
  async function fetchCompletion(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await faceApi.getProfileCompletion()
      completionInfo.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Computed property for completion percentage (0-100)
   */
  const percentage = computed<number>(() => {
    return completionInfo.value?.profile_completion_percentage ?? 0
  })

  /**
   * Computed property for missing items array
   */
  const missingItems = computed<ProfileCompletionMissingItem[]>(() => {
    return completionInfo.value?.profile_completion_missing ?? []
  })

  /**
   * Computed property for whether profile is complete
   */
  const isComplete = computed<boolean>(() => {
    return completionInfo.value?.profile_completion_is_complete ?? false
  })

  return {
    completionInfo,
    isLoading,
    error,
    fetchCompletion,
    percentage,
    missingItems,
    isComplete,
    clearError,
  }
}
