import { ref } from 'vue'
import { candidatureApi } from '../services/candidatureApi'
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
      // Handle API error response
      if (err && typeof err === 'object' && 'response' in err) {
        const axiosError = err as { response?: { data?: { error?: { message?: string }; message?: string }; status?: number } }
        if (axiosError.response?.status === 400) {
          error.value = axiosError.response.data?.error?.message || 'Cette candidature ne peut pas être refusée'
        } else if (axiosError.response?.status === 403) {
          error.value = axiosError.response.data?.message || "Vous n'êtes pas autorisé à effectuer cette action"
        } else if (axiosError.response?.status === 404) {
          error.value = 'Candidature introuvable'
        } else {
          error.value = 'Une erreur est survenue. Veuillez réessayer.'
        }
      } else {
        error.value = 'Une erreur est survenue. Veuillez réessayer.'
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
