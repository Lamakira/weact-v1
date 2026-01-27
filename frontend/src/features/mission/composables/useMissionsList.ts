import { ref, computed } from 'vue'
import { missionApi } from '../services/missionApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Mission } from '../types'

/**
 * Composable for managing the producer's missions list
 * Handles fetching, loading states, and error handling
 */
export function useMissionsList() {
  const missions = ref<Mission[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const message = ref<string | null>(null)

  /**
   * Whether the missions list is empty
   */
  const isEmpty = computed(() => missions.value.length === 0)

  /**
   * Whether missions have been successfully loaded
   */
  const hasLoaded = ref(false)

  /**
   * Fetch all missions for the authenticated producer
   * Orders by most recent first
   */
  async function fetchMissions(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await missionApi.getMissions()
      missions.value = response.data
      message.value = response.message ?? null
      hasLoaded.value = true
    } catch (err: unknown) {
      error.value = getApiErrorMessage(err)
      missions.value = []
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Refresh the missions list (alias for fetchMissions)
   * Useful after deletion or other mutations
   */
  async function refreshMissions(): Promise<void> {
    await fetchMissions()
  }

  /**
   * Remove a mission from the local list by ID
   * Used for optimistic updates after successful deletion
   * @param missionId The ID of the mission to remove
   */
  function removeMissionFromList(missionId: number): void {
    missions.value = missions.value.filter((m) => m.id !== missionId)
  }

  /**
   * Reset all state
   */
  function reset(): void {
    missions.value = []
    isLoading.value = false
    error.value = null
    message.value = null
    hasLoaded.value = false
  }

  return {
    // State
    missions,
    isLoading,
    error,
    message,
    isEmpty,
    hasLoaded,

    // Actions
    fetchMissions,
    refreshMissions,
    removeMissionFromList,
    reset,
  }
}
