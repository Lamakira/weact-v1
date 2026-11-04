import { ref } from 'vue'
import { candidatureApi } from '../services/candidatureApi'
import { formatApiError, getApiErrorCode, GENERIC_VALIDATION_MESSAGE } from '@/services/errorFormatter'
import type { ImageSignature } from '@/features/imageContract/types'
import type { CandidatureResponse } from '../types'

type ConfirmErrorPayload = {
  error?: { message?: string }
  message?: string
}

type ConfirmAxiosError = {
  response?: {
    status?: number
    data?: ConfirmErrorPayload
  }
}

function resolveConfirmErrorMessage(err: unknown): string {
  if (!err || typeof err !== 'object' || !('response' in err)) {
    return 'Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.'
  }

  const { response } = err as ConfirmAxiosError
  if (!response) {
    return 'Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.'
  }

  const backendMessage = formatApiError(err, '')

  switch (response.status) {
    case 400:
      return backendMessage || 'Cette candidature ne peut pas être confirmée'
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
        : 'Cette candidature ne peut pas être confirmée dans son état actuel.'
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

export function useConfirmCandidature() {
  const isConfirming = ref(false)
  const error = ref<string | null>(null)
  /** Code d'erreur serveur (ex. SIGNATURE_CODE_INVALID) : la fenêtre de signature garde son état dessus. */
  const errorCode = ref<string | null>(null)
  const successMessage = ref<string | null>(null)

  async function confirmCandidature(
    candidatureId: string,
    signature?: ImageSignature,
  ): Promise<CandidatureResponse | null> {
    isConfirming.value = true
    error.value = null
    errorCode.value = null
    successMessage.value = null

    try {
      const response = await candidatureApi.confirmCandidature(candidatureId, signature)
      successMessage.value = response.message || 'Participation confirmée'
      return response
    } catch (err: unknown) {
      error.value = resolveConfirmErrorMessage(err)
      errorCode.value = getApiErrorCode(err)
      return null
    } finally {
      isConfirming.value = false
    }
  }

  function reset(): void {
    error.value = null
    errorCode.value = null
    successMessage.value = null
  }

  return {
    isConfirming,
    error,
    errorCode,
    successMessage,
    confirmCandidature,
    reset,
  }
}
