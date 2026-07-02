import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { candidatureApi } from '../services/candidatureApi'
import { formatApiError, getApiErrorCode } from '@/services/errorFormatter'
import type { CandidatureResponse } from '../types'

/**
 * Composable for accepting a candidature (Producer — UGC product-only, 8-3).
 * Transitions a `pending` candidature to `accepted` via
 * `/producer/candidatures/{id}/accept`. `errorCode` is exposed so callers can
 * resync the list on capacity errors (MISSION_FULL / ALREADY_ACCEPTED, AC9).
 */
export function useAcceptCandidature() {
  const isAccepting = ref(false)
  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)
  const successMessage = ref<string | null>(null)

  async function acceptCandidature(candidatureId: string): Promise<CandidatureResponse | null> {
    isAccepting.value = true
    error.value = null
    errorCode.value = null
    successMessage.value = null

    try {
      const response = await candidatureApi.acceptCandidature(candidatureId)
      successMessage.value = response.message || 'Candidature acceptée'
      return response
    } catch (err: unknown) {
      errorCode.value = getApiErrorCode(err)
      if (isAxiosError(err) && errorCode.value === 'MISSION_FULL') {
        error.value = formatApiError(err, 'Toutes les places de cette mission sont déjà pourvues.')
      } else if (isAxiosError(err) && errorCode.value === 'UGC_SUBSCRIPTION_REQUIRED') {
        error.value = formatApiError(err, "Cette Face n'est plus abonnée — son acceptation est impossible.")
      } else if (isAxiosError(err) && err.response?.status === 403) {
        error.value = formatApiError(err, "Vous n'êtes pas autorisé à effectuer cette action.")
      } else {
        error.value = formatApiError(err, 'Cette candidature ne peut pas être acceptée.')
      }
      return null
    } finally {
      isAccepting.value = false
    }
  }

  function reset(): void {
    error.value = null
    errorCode.value = null
    successMessage.value = null
  }

  return {
    isAccepting,
    error,
    errorCode,
    successMessage,
    acceptCandidature,
    reset,
  }
}
