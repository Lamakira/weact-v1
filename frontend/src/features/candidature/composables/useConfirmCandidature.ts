import { ref } from 'vue'
import { candidatureApi } from '../services/candidatureApi'
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

  const backendMessage = response.data?.error?.message ?? response.data?.message

  switch (response.status) {
    case 400:
      return backendMessage || 'Cette candidature ne peut pas être confirmée'
    case 403:
      return backendMessage || "Vous n'êtes pas autorisé à effectuer cette action"
    case 404:
      return 'Candidature introuvable'
    case 422:
      return backendMessage || 'Cette candidature ne peut pas être confirmée dans son état actuel.'
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
  const successMessage = ref<string | null>(null)

  async function confirmCandidature(candidatureId: string): Promise<CandidatureResponse | null> {
    isConfirming.value = true
    error.value = null
    successMessage.value = null

    try {
      const response = await candidatureApi.confirmCandidature(candidatureId)
      successMessage.value = response.message || 'Participation confirmée'
      return response
    } catch (err: unknown) {
      error.value = resolveConfirmErrorMessage(err)
      return null
    } finally {
      isConfirming.value = false
    }
  }

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
