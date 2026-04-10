import { computed, type Ref, type ComputedRef } from 'vue'
import { faceApi } from '../services/faceApi'
import type { ProfileCompletionInfo, ProfileCompletionMissingItem } from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const PROFILE_COMPLETION_CACHE_TTL_MS = 2 * 60 * 1000

const profileCompletionResource = createSharedCachedResource<ProfileCompletionInfo | null>({
  key: 'face-profile-completion',
  initialValue: null,
  ttlMs: PROFILE_COMPLETION_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getProfileCompletion()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseProfileCompletionReturn {
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
  const completionInfo = profileCompletionResource.data
  const isLoading = profileCompletionResource.isLoading
  const error = profileCompletionResource.error

  /**
   * Clear the current error
   */
  function clearError(): void {
    profileCompletionResource.clearError()
  }

  /**
   * Fetch the current profile completion status
   */
  async function fetchCompletion(): Promise<void> {
    await profileCompletionResource.fetch()
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
