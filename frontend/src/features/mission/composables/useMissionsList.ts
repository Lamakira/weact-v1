import { ref, computed } from 'vue'
import { missionApi } from '../services/missionApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Mission, MissionStatusType } from '../types'

/**
 * Composable for managing the producer's missions list
 * Handles fetching, loading states, error handling, and filtering
 */
export function useMissionsList() {
  const missions = ref<Mission[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const message = ref<string | null>(null)
  const statusFilter = ref<MissionStatusType | ''>('')

  /**
   * Filtered missions based on status filter
   */
  const filteredMissions = computed(() => {
    if (!statusFilter.value) {
      return missions.value
    }
    return missions.value.filter((m) => m.status === statusFilter.value)
  })

  /**
   * Whether the filtered missions list is empty
   */
  const isEmpty = computed(() => filteredMissions.value.length === 0)

  /**
   * Whether the original missions list is empty (no missions at all)
   */
  const hasNoMissions = computed(() => missions.value.length === 0)

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
  function removeMissionFromList(missionId: string): void {
    missions.value = missions.value.filter((m) => m.id !== missionId)
  }

  /**
   * Set the status filter
   */
  function setStatusFilter(status: MissionStatusType | ''): void {
    statusFilter.value = status
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
    statusFilter.value = ''
  }

  return {
    // State
    missions: filteredMissions,
    allMissions: missions,
    isLoading,
    error,
    message,
    isEmpty,
    hasNoMissions,
    hasLoaded,
    statusFilter,

    // Actions
    fetchMissions,
    refreshMissions,
    removeMissionFromList,
    setStatusFilter,
    reset,
  }
}
