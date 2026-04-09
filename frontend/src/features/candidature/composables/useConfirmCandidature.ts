import { ref } from 'vue'
import { candidatureApi } from '../services/candidatureApi'
import type { CandidatureResponse } from '../types'

/**
 * Composable for confirming participation in a mission (Face only)
 * Handles API call, loading, error states
 */
export function useConfirmCandidature() {
  const isConfirming = ref(false)
  const error = ref<string | null>(null)
  const successMessage = ref<string | null>(null)

  /**
   * Confirm participation in a mission
   * @param candidatureId The candidature ID to confirm
   * @returns The updated candidature data or null if failed
   */
  async function confirmCandidature(candidatureId: string): Promise<CandidatureResponse | null> {
    isConfirming.value = true
    error.value = null
    successMessage.value = null

    try {
      const response = await candidatureApi.confirmCandidature(candidatureId)
      successMessage.value = response.message || 'Participation confirmée'
      return response
    } catch (err: unknown) {
      // Handle API error response
      if (err && typeof err === 'object' && 'response' in err) {
        const axiosError = err as { response?: { data?: { error?: { message?: string }; message?: string }; status?: number } }
        if (axiosError.response?.status === 400) {
          error.value = axiosError.response.data?.error?.message || 'Cette candidature ne peut pas être confirmée'
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
      isConfirming.value = false
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
    isConfirming,
    error,
    successMessage,
    confirmCandidature,
    reset,
  }
}
