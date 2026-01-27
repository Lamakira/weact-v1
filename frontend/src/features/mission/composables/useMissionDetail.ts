import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type { Mission, MissionCandidature } from '../types'
import { faceMissionApi } from '../services/faceMissionApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

/**
 * Composable for fetching and managing mission detail state
 */
export function useMissionDetail() {
  const mission = ref<Mission | null>(null)
  const candidature = ref<MissionCandidature | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const notFound = ref(false)

  /**
   * Fetch mission detail by ID
   * @param id The mission ID
   */
  async function fetchMission(id: number): Promise<void> {
    isLoading.value = true
    error.value = null
    notFound.value = false

    try {
      const response = await faceMissionApi.getMissionDetail(id)
      mission.value = response.data
      candidature.value = response.candidature ?? null
    } catch (err: unknown) {
      if (isAxiosError(err) && err.response?.status === 404) {
        notFound.value = true
      } else {
        error.value = getApiErrorMessage(err)
      }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update candidature after successful application
   * @param newCandidature The newly created candidature
   */
  function setCandidature(newCandidature: MissionCandidature): void {
    candidature.value = newCandidature
  }

  return {
    mission,
    candidature,
    isLoading,
    error,
    notFound,
    fetchMission,
    setCandidature,
  }
}
