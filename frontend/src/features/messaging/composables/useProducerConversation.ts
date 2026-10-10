import { ref, computed } from 'vue'
import { messagingApi } from '../services/messagingApi'
import type { Conversation, Message } from '../types'

/**
 * Composable for managing Producer conversation state
 * Handles loading conversation data with messages from Producer perspective
 */
export function useProducerConversation() {
  const conversation = ref<Conversation | null>(null)
  const isLoading = ref(false)
  const isRefreshing = ref(false)
  const error = ref<string | null>(null)
  const refreshError = ref<string | null>(null)

  // Incrémenté à chaque ouverture / réinitialisation de fil : toute réponse réseau qui
  // revient après un changement de fil est périmée et ne doit JAMAIS être appliquée
  // (sinon le fil B afficherait A alors que l'envoi part vers B).
  let epoch = 0

  // Computed properties
  const messages = computed(() => conversation.value?.messages ?? [])
  const otherParticipant = computed(() => conversation.value?.other_participant)
  const missionTitle = computed(() => conversation.value?.mission_title ?? '')
  const context = computed(() => conversation.value?.context ?? null)
  const unreadCount = computed(() => conversation.value?.unread_count ?? 0)

  /**
   * Load conversation data from API (Producer endpoint)
   * @param conversationId The conversation ID to load
   */
  async function loadConversation(conversationId: string): Promise<boolean> {
    const requestEpoch = ++epoch
    isLoading.value = true
    error.value = null

    try {
      const response = await messagingApi.getProducerConversation(conversationId)
      if (requestEpoch !== epoch || response.data.id !== conversationId) return false
      conversation.value = response.data
      return true
    } catch (err: unknown) {
      if (requestEpoch !== epoch) return false
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
      if (requestEpoch === epoch) isLoading.value = false
    }
  }

  /**
   * Add a new message to the conversation (used after sending)
   * @param message The message to add
   */
  function addMessage(message: Message): void {
    if (conversation.value && !conversation.value.messages.some((m) => m.id === message.id)) {
      conversation.value.messages.push(message)
    }
  }

  /**
   * Apply a read receipt: the other participant read my messages up to lastReadMessageId
   */
  function markOwnMessagesRead(lastReadMessageId: number, readAt: string): void {
    for (const message of conversation.value?.messages ?? []) {
      if (message.is_own_message && !message.read_at && message.id <= lastReadMessageId) {
        message.read_at = readAt
      }
    }
  }

  /**
   * After a successful POST /read: the messages received so far are read server-side
   */
  function markReceivedMessagesRead(readAt: string): void {
    for (const message of conversation.value?.messages ?? []) {
      if (!message.is_own_message && !message.read_at) message.read_at = readAt
    }
  }

  /**
   * Silent resync (polling fallback): replaces messages without touching loading/error state
   */
  async function syncConversation(conversationId: string): Promise<void> {
    const requestEpoch = epoch
    try {
      const response = await messagingApi.getProducerConversation(conversationId)
      if (
        requestEpoch !== epoch
        || response.data.id !== conversationId
        || conversation.value?.id !== conversationId
      ) {
        return
      }
      conversation.value = response.data
    } catch (err: unknown) {
      if (requestEpoch !== epoch) return
      // Accès retiré / conversation supprimée : état d'erreur existant, le temps réel
      // et le polling s'arrêtent (le composant passe l'uuid à null quand `error` est posé)
      const status = (err as { response?: { status?: number } })?.response?.status
      if (status === 403) error.value = "Vous n'avez pas accès à cette conversation"
      else if (status === 404) error.value = 'Conversation introuvable'
      // Autres erreurs : silencieux, le prochain tick réessaiera
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

    const requestEpoch = epoch
    isRefreshing.value = true
    refreshError.value = null

    try {
      const response = await messagingApi.getProducerConversation(conversationId)
      if (requestEpoch !== epoch || response.data.id !== conversationId) return false
      conversation.value = response.data
      return true
    } catch (err: unknown) {
      if (requestEpoch !== epoch) return false
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
    epoch++
    isLoading.value = false
    isRefreshing.value = false
    conversation.value = null
    error.value = null
    refreshError.value = null
  }

  return {
    conversation,
    messages,
    otherParticipant,
    missionTitle,
    context,
    unreadCount,
    isLoading,
    isRefreshing,
    error,
    refreshError,
    loadConversation,
    refreshConversation,
    addMessage,
    markOwnMessagesRead,
    markReceivedMessagesRead,
    syncConversation,
    clearRefreshError,
    reset,
  }
}
