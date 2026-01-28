import { ref } from 'vue'
import { messagingApi } from '../services/messagingApi'
import type { Message, ApiErrorResponse } from '../types'

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
  async function sendMessage(conversationId: number, content: string): Promise<Message | null> {
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
      // Handle API error response
      if (err && typeof err === 'object' && 'response' in err) {
        const axiosError = err as {
          response?: { data?: ApiErrorResponse; status?: number }
        }

        if (axiosError.response?.status === 422) {
          // Validation errors
          const errors = axiosError.response.data?.errors
          if (errors?.content && errors.content.length > 0) {
            validationErrors.value = errors.content
            error.value = errors.content[0] ?? 'Erreur de validation'
          } else {
            error.value = 'Erreur de validation'
          }
        } else if (axiosError.response?.status === 403) {
          error.value = "Vous n'êtes pas autorisé à envoyer des messages dans cette conversation"
        } else if (axiosError.response?.status === 404) {
          error.value = 'Conversation introuvable'
        } else {
          error.value = "Impossible d'envoyer le message. Veuillez réessayer."
        }
      } else {
        error.value = 'Une erreur est survenue. Veuillez réessayer.'
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
