import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { candidatureApi } from '../services/candidatureApi'
import { formatApiError } from '@/services/errorFormatter'
import type { ReleaseCandidatureResponse } from '../types'

/**
 * Composable pour libérer (dénouer) une candidature UGC acceptée (Producteur, 9-2).
 * Rembourse l'escrow hybride, libère le slot, rouvre la mission. Calque useRejectCandidature.
 */
export function useReleaseCandidature() {
  const isReleasing = ref(false)
  const error = ref<string | null>(null)
  const successMessage = ref<string | null>(null)

  /**
   * Release an accepted candidature's slot
   * @param candidatureId The candidature ID to release
   * @returns The release response or null if failed
   */
  async function releaseCandidature(
    candidatureId: string,
  ): Promise<ReleaseCandidatureResponse | null> {
    isReleasing.value = true
    error.value = null
    successMessage.value = null

    try {
      const response = await candidatureApi.releaseCandidature(candidatureId)
      successMessage.value = response.data.message || 'La place a été libérée'
      return response
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.response?.status === 400) {
          error.value = formatApiError(err, 'Cette candidature ne peut pas être libérée')
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
      isReleasing.value = false
    }
  }

  /**
   * Reset error and success states
   */
  function reset(): void {
    error.value = null
    successMessage.value = null
  }

  return { isReleasing, error, successMessage, releaseCandidature, reset }
}
