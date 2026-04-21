import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { candidatureApi } from '../services/candidatureApi'
import { formatApiError } from '@/services/errorFormatter'
import type { CandidatureResponse } from '../types'

/**
 * Composable for rejecting a candidature
 * Handles API call, loading, error states
 */
export function useRejectCandidature() {
  const isRejecting = ref(false)
  const error = ref<string | null>(null)
  const successMessage = ref<string | null>(null)

  /**
   * Reject a candidature
   * @param candidatureId The candidature ID to reject
   * @returns The updated candidature data or null if failed
   */
  async function rejectCandidature(candidatureId: string): Promise<CandidatureResponse | null> {
    isRejecting.value = true
    error.value = null
    successMessage.value = null

    try {
      const response = await candidatureApi.rejectCandidature(candidatureId)
      successMessage.value = response.message || 'Candidature refusée'
      return response
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.response?.status === 400) {
          error.value = formatApiError(err, 'Cette candidature ne peut pas être refusée')
        } else if (err.response?.status === 403) {
          error.value = formatApiError(err, "Vous n'êtes pas autorisé à effectuer cette action")
        } else if (err.response?.status === 404) {
          error.value = 'Candidature introuvable'
        } else {
          error.value = formatApiError(err, 'Une erreur est survenue. Veuillez réessayer.')
        }
      } else {
        error.value = formatApiError(err, 'Une erreur est survenue. Veuillez réessayer.')
      }
      return null
    } finally {
      isRejecting.value = false
    }
  }

  /**
   * Reset error and success states
   */
  function reset(): void {
    error.value = null
    successMessage.value = null
  }

  return {
    isRejecting,
    error,
    successMessage,
    rejectCandidature,
    reset,
  }
}
