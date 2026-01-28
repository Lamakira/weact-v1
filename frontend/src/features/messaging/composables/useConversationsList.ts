import { ref, computed } from 'vue'
import { messagingApi } from '../services/messagingApi'
import type { ConversationListItem, PaginationMeta } from '../types'

/**
 * Composable for managing Face conversations list state
 * Handles loading paginated conversations with latest message previews
 */
export function useConversationsList() {
  const conversations = ref<ConversationListItem[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const isLoading = ref(false)
  const isRefreshing = ref(false)
  const error = ref<string | null>(null)

  // Computed properties
  const hasConversations = computed(() => conversations.value.length > 0)
  const hasMorePages = computed(() => {
    if (!meta.value) return false
    return meta.value.current_page < meta.value.last_page
  })

  /**
   * Load conversations from API
   * @param page Page number to load (default: 1)
   */
  async function loadConversations(page = 1): Promise<boolean> {
    isLoading.value = true
    error.value = null

    try {
      const response = await messagingApi.getConversations(page)
      conversations.value = response.data
      meta.value = response.meta
      return true
    } catch (err: unknown) {
      // Handle API error response
      if (err && typeof err === 'object' && 'response' in err) {
        const axiosError = err as {
          response?: { data?: { message?: string }; status?: number }
        }
        if (axiosError.response?.status === 401) {
          error.value = 'Veuillez vous reconnecter'
        } else if (axiosError.response?.status === 403) {
          error.value = "Vous n'avez pas accès à cette ressource"
        } else {
          error.value = 'Impossible de charger les conversations'
        }
      } else {
        error.value = 'Une erreur est survenue. Veuillez réessayer.'
      }
      console.error('Failed to load conversations:', err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Refresh conversations without showing full loading state
   * Keeps existing data visible during refresh
   */
  async function refreshConversations(): Promise<boolean> {
    if (isRefreshing.value) return false

    isRefreshing.value = true
    error.value = null

    try {
      const response = await messagingApi.getConversations(1)
      conversations.value = response.data
      meta.value = response.meta
      return true
    } catch (err: unknown) {
      error.value = 'Impossible de rafraîchir les conversations'
      console.error('Failed to refresh conversations:', err)
      return false
    } finally {
      isRefreshing.value = false
    }
  }

  /**
   * Load next page of conversations (append to existing)
   */
  async function loadMoreConversations(): Promise<boolean> {
    if (!hasMorePages.value || isLoading.value) return false

    const nextPage = (meta.value?.current_page ?? 0) + 1

    try {
      const response = await messagingApi.getConversations(nextPage)
      conversations.value = [...conversations.value, ...response.data]
      meta.value = response.meta
      return true
    } catch (err: unknown) {
      console.error('Failed to load more conversations:', err)
      return false
    }
  }

  /**
   * Reset conversations state
   */
  function reset(): void {
    conversations.value = []
    meta.value = null
    error.value = null
  }

  return {
    conversations,
    meta,
    isLoading,
    isRefreshing,
    error,
    hasConversations,
    hasMorePages,
    loadConversations,
    refreshConversations,
    loadMoreConversations,
    reset,
  }
}
