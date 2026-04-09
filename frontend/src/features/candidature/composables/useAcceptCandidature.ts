import { ref } from 'vue'
import { candidatureApi } from '../services/candidatureApi'
import type { CandidatureResponse } from '../types'

/**
 * Composable for accepting a candidature
 * Handles API call, loading, error states
 */
export function useAcceptCandidature() {
  const isAccepting = ref(false)
  const error = ref<string | null>(null)
  const successMessage = ref<string | null>(null)

  /**
   * Accept a candidature
   * @param candidatureId The candidature ID to accept
   * @returns The updated candidature data or null if failed
   */
  async function acceptCandidature(candidatureId: string): Promise<CandidatureResponse | null> {
    isAccepting.value = true
    error.value = null
    successMessage.value = null

    try {
      const response = await candidatureApi.acceptCandidature(candidatureId)
      successMessage.value = response.message || 'Candidature acceptée avec succès'
      return response
    } catch (err: unknown) {
      // Handle API error response
      if (err && typeof err === 'object' && 'response' in err) {
        const axiosError = err as { response?: { data?: { error?: { message?: string }; message?: string }; status?: number } }
        if (axiosError.response?.status === 400) {
          error.value = axiosError.response.data?.error?.message || 'Cette candidature ne peut pas être acceptée'
        } else if (axiosError.response?.status === 403) {
          error.value = axiosError.response.data?.message || 'Vous n\'êtes pas autorisé à effectuer cette action'
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
      isAccepting.value = false
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
    isAccepting,
    error,
    successMessage,
    acceptCandidature,
    reset,
  }
}
