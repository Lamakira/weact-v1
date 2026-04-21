import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { candidatureApi } from '../services/candidatureApi'
import { formatApiError } from '@/services/errorFormatter'

/**
 * Composable for cancelling a pending candidature (Face only)
 * Handles API call, loading, error states
 */
export function useCancelCandidature() {
  const isCancelling = ref(false)
  const error = ref<string | null>(null)
  const successMessage = ref<string | null>(null)

  /**
   * Cancel a pending candidature
   * @param candidatureId The candidature ID to cancel
   * @returns true if successful, false otherwise
   */
  async function cancelCandidature(candidatureId: string): Promise<boolean> {
    isCancelling.value = true
    error.value = null
    successMessage.value = null

    try {
      const response = await candidatureApi.cancelCandidature(candidatureId)
      successMessage.value = response.message || 'Candidature annulée avec succès.'
      return true
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.response?.status === 400) {
          error.value = formatApiError(err, 'Cette candidature ne peut pas être annulée')
        } else if (err.response?.status === 403) {
          error.value = formatApiError(err, 'Vous n\'êtes pas autorisé à effectuer cette action')
        } else if (err.response?.status === 404) {
          error.value = 'Candidature introuvable'
        } else {
          error.value = formatApiError(err, 'Une erreur est survenue. Veuillez réessayer.')
        }
      } else {
        error.value = formatApiError(err, 'Une erreur est survenue. Veuillez réessayer.')
      }
      return false
    } finally {
      isCancelling.value = false
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
    isCancelling,
    error,
    successMessage,
    cancelCandidature,
    reset,
  }
}
