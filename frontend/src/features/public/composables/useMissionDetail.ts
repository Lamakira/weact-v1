import { ref } from 'vue'
import {
  fetchPublicMissionDetail,
  type PublicMission,
  type PublicMissionDetailResult,
} from '../services/publicMissionsApi'

/**
 * Composable for fetching and displaying a single public mission detail.
 *
 * Note: This composable creates NEW state for each component instance (non-singleton).
 * Each call to useMissionDetail() returns independent refs. This is intentional
 * as different components may need to display different mission details.
 */
export function useMissionDetail() {
  const mission = ref<PublicMission | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const notFound = ref(false)

  /**
   * Fetch a mission's public detail by ID
   */
  async function fetchMission(id: number): Promise<PublicMissionDetailResult> {
    isLoading.value = true
    error.value = null
    notFound.value = false
    mission.value = null

    try {
      const result = await fetchPublicMissionDetail(id)

      if (result.success && result.mission) {
        mission.value = result.mission
      } else {
        if (result.notFound) {
          notFound.value = true
        }
        error.value = result.error ?? null
      }

      return result
    } finally {
      isLoading.value = false
    }
  }

  return {
    // State
    mission,
    isLoading,
    error,
    notFound,
    // Actions
    fetchMission,
  }
}
