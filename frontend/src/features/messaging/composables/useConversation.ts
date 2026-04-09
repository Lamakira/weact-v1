import { ref, computed } from 'vue'
import { messagingApi } from '../services/messagingApi'
import type { Conversation, Message } from '../types'

/**
 * Composable for managing conversation state
 * Handles loading conversation data with messages
 */
export function useConversation() {
  const conversation = ref<Conversation | null>(null)
  const isLoading = ref(false)
  const isRefreshing = ref(false)
  const error = ref<string | null>(null)
  const refreshError = ref<string | null>(null)

  // Computed properties
  const messages = computed(() => conversation.value?.messages ?? [])
  const otherParticipant = computed(() => conversation.value?.other_participant)
  const missionTitle = computed(() => conversation.value?.mission_title ?? '')
  const unreadCount = computed(() => conversation.value?.unread_count ?? 0)

  /**
   * Load conversation data from API
   * @param conversationId The conversation ID to load
   */
  async function loadConversation(conversationId: string): Promise<boolean> {
    isLoading.value = true
    error.value = null

    try {
      const response = await messagingApi.getConversation(conversationId)
      conversation.value = response.data
      return true
    } catch (err: unknown) {
      // Handle API error response
      if (err && typeof err === 'object' && 'response' in err) {
        const axiosError = err as {
          response?: { data?: { message?: string }; status?: number }
        }
        if (axiosError.response?.status === 403) {
          error.value = "Vous n'avez pas accès à cette conversation"
        } else if (axiosError.response?.status === 404) {
          error.value = 'Conversation introuvable'
        } else {
          error.value = 'Impossible de charger la conversation'
        }
      } else {
        error.value = 'Une erreur est survenue. Veuillez réessayer.'
      }
      console.error('Failed to load conversation:', err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Add a new message to the conversation (used after sending)
   * @param message The message to add
   */
  function addMessage(message: Message): void {
    if (conversation.value) {
      conversation.value.messages.push(message)
    }
  }

  /**
   * Refresh conversation messages without showing full loading skeleton
   * Keeps existing messages visible during refresh
   * @param conversationId The conversation ID to refresh
   */
  async function refreshConversation(conversationId: string): Promise<boolean> {
    // Prevent double refresh
    if (isRefreshing.value) return false

    isRefreshing.value = true
    refreshError.value = null

    try {
      const response = await messagingApi.getConversation(conversationId)
      conversation.value = response.data
      return true
    } catch (err: unknown) {
      // Don't clear existing messages on refresh failure
      refreshError.value = 'Impossible de rafraîchir les messages'
      console.error('Failed to refresh conversation:', err)
      return false
    } finally {
      isRefreshing.value = false
    }
  }

  /**
   * Clear refresh error state
   */
  function clearRefreshError(): void {
    refreshError.value = null
  }

  /**
   * Reset conversation state
   */
  function reset(): void {
    conversation.value = null
    error.value = null
    refreshError.value = null
  }

  return {
    conversation,
    messages,
    otherParticipant,
    missionTitle,
    unreadCount,
    isLoading,
    isRefreshing,
    error,
    refreshError,
    loadConversation,
    refreshConversation,
    addMessage,
    clearRefreshError,
    reset,
  }
}
