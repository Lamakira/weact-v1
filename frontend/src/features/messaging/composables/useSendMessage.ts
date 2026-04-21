import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { messagingApi } from '../services/messagingApi'
import { formatApiError, getApiErrorDetails } from '@/services/errorFormatter'
import type { Message } from '../types'

/**
 * Composable for sending messages in a conversation
 * Handles API call, loading state, and validation errors
 */
export function useSendMessage() {
  const isSending = ref(false)
  const error = ref<string | null>(null)
  const validationErrors = ref<string[]>([])

  /**
   * Send a message in a conversation
   * @param conversationId The conversation ID to send message to
   * @param content The message content
   * @returns The created message or null if failed
   */
  async function sendMessage(conversationId: string, content: string): Promise<Message | null> {
    isSending.value = true
    error.value = null
    validationErrors.value = []

    // Client-side validation
    const trimmedContent = content.trim()
    if (!trimmedContent) {
      error.value = 'Le message ne peut pas être vide.'
      isSending.value = false
      return null
    }

    if (trimmedContent.length > 5000) {
      error.value = 'Le message ne peut pas dépasser 5000 caractères.'
      isSending.value = false
      return null
    }

    try {
      const response = await messagingApi.sendMessage(conversationId, { content: trimmedContent })
      return response.data
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.response?.status === 422) {
          const errors = getApiErrorDetails(err)
          const firstField = Object.keys(errors)[0]
          const firstMessage = firstField
            ? (errors[firstField]?.[0] ?? formatApiError(err, 'Erreur de validation'))
            : formatApiError(err, 'Erreur de validation')

          validationErrors.value = errors.content ?? []
          error.value = firstMessage || 'Erreur de validation'
        } else if (err.response?.status === 403) {
          error.value = "Vous n'êtes pas autorisé à envoyer des messages dans cette conversation"
        } else if (err.response?.status === 404) {
          error.value = 'Conversation introuvable'
        } else {
          error.value = formatApiError(err, "Impossible d'envoyer le message. Veuillez réessayer.")
        }
      } else {
        error.value = formatApiError(err, 'Une erreur est survenue. Veuillez réessayer.')
      }
      console.error('Failed to send message:', err)
      return null
    } finally {
      isSending.value = false
    }
  }

  /**
   * Reset error state
   */
  function resetError(): void {
    error.value = null
    validationErrors.value = []
  }

  return {
    isSending,
    error,
    validationErrors,
    sendMessage,
    resetError,
  }
}
