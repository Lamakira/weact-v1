import { ref } from 'vue'
import { candidatureApi } from '../services/candidatureApi'
import { formatApiError } from '@/services/errorFormatter'

/**
 * Composable for contesting an absence declared by the Producer on a mission
 * (Face only). Handles API call, loading and error states.
 */
export function useContestMissionAbsence() {
  const isContesting = ref(false)
  const error = ref<string | null>(null)

  /**
   * @param missionId The mission UUID
   * @returns true if the contest was recorded, false otherwise
   */
  async function contestAbsence(missionId: string): Promise<boolean> {
    isContesting.value = true
    error.value = null

    try {
      await candidatureApi.contestMissionAbsence(missionId)
      return true
    } catch (err: unknown) {
      error.value = formatApiError(err, 'Impossible d\'envoyer votre contestation. Veuillez réessayer.')
      return false
    } finally {
      isContesting.value = false
    }
  }

  function reset(): void {
    error.value = null
  }

  return {
    isContesting,
    error,
    contestAbsence,
    reset,
  }
}
