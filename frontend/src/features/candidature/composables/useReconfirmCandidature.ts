import { ref } from 'vue'
import { candidatureApi } from '../services/candidatureApi'
import { formatApiError, GENERIC_VALIDATION_MESSAGE } from '@/services/errorFormatter'
import type { CandidatureResponse } from '../types'

type ReconfirmErrorPayload = {
  error?: { message?: string }
  message?: string
}

type ReconfirmAxiosError = {
  response?: {
    status?: number
    data?: ReconfirmErrorPayload
  }
}

function resolveReconfirmErrorMessage(err: unknown): string {
  if (!err || typeof err !== 'object' || !('response' in err)) {
    return 'Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.'
  }

  const { response } = err as ReconfirmAxiosError
  if (!response) {
    return 'Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.'
  }

  const backendMessage = formatApiError(err, '')

  switch (response.status) {
    case 400:
      return backendMessage || 'Cette participation ne peut pas être reconfirmée'
    case 403:
      return backendMessage || "Vous n'êtes pas autorisé à effectuer cette action"
    case 404:
      return 'Candidature introuvable'
    case 422: {
      const normalized = backendMessage.endsWith('.')
        ? backendMessage.slice(0, -1)
        : backendMessage
      return normalized && normalized !== GENERIC_VALIDATION_MESSAGE
        ? backendMessage
        : 'Cette participation ne peut pas être reconfirmée dans son état actuel.'
    }
    case 429:
      return 'Trop de tentatives. Veuillez réessayer dans quelques instants.'
    case 500:
    case 502:
    case 503:
    case 504:
      return 'Le serveur a rencontré une erreur. Veuillez réessayer plus tard.'
    default:
      return backendMessage || 'Une erreur est survenue. Veuillez réessayer.'
  }
}

/**
 * Composable for reconfirming participation on a UGC mission (Face — 8-3).
 * Transitions an `accepted` candidature to `confirmed` via the dedicated
 * `/face/candidatures/{id}/reconfirm` endpoint (separate from the cash `confirm`).
 */
export function useReconfirmCandidature() {
  const isReconfirming = ref(false)
  const error = ref<string | null>(null)
  const successMessage = ref<string | null>(null)

  async function reconfirmCandidature(candidatureId: string): Promise<CandidatureResponse | null> {
    isReconfirming.value = true
    error.value = null
    successMessage.value = null

    try {
      const response = await candidatureApi.reconfirmCandidature(candidatureId)
      successMessage.value = response.message || 'Participation reconfirmée'
      return response
    } catch (err: unknown) {
      error.value = resolveReconfirmErrorMessage(err)
      return null
    } finally {
      isReconfirming.value = false
    }
  }

  function reset(): void {
    error.value = null
    successMessage.value = null
  }

  return {
    isReconfirming,
    error,
    successMessage,
    reconfirmCandidature,
    reset,
  }
}
