import apiClient from '@/services/apiClient'
import { syncMessagesUnreadFromServer } from '@/stores/messagesUnread'
import { getSocketIdHeaders } from '../utils/realtime'
import type {
  ConversationResponse,
  MarkReadResponse,
  ConversationsListResponse,
  MessageResponse,
  SendMessageData,
} from '../types'

/**
 * Messaging API service
 * Endpoints for Face users to manage conversations and messages
 */
export const messagingApi = {
  /**
   * Get list of all conversations for Face user
   * @param page Optional page number for pagination
   * @returns Paginated list of conversations
   */
  async getConversations(page = 1): Promise<ConversationsListResponse> {
    const response = await apiClient.get<ConversationsListResponse>('/face/conversations', {
      params: { page },
    })
    syncMessagesUnreadFromServer(response.data?.meta?.unread_conversations_count)
    return response.data
  },

  /**
   * Get a conversation with its messages (Face)
   * Also marks unread messages from other participant as read
   * @param conversationId The conversation ID to fetch
   * @returns Conversation data with messages
   */
  async getConversation(conversationId: string): Promise<ConversationResponse> {
    const response = await apiClient.get<ConversationResponse>(
      `/face/conversations/${conversationId}`,
    )
    syncMessagesUnreadFromServer(response.data?.meta?.unread_conversations_count)
    return response.data
  },

  /**
   * Send a message in a conversation (Face)
   * @param conversationId The conversation ID to send message to
   * @param data Message content
   * @returns Created message data
   */
  async sendMessage(conversationId: string, data: SendMessageData): Promise<MessageResponse> {
    const headers = await getSocketIdHeaders()
    const response = await apiClient.post<MessageResponse>(
      `/face/conversations/${conversationId}/messages`,
      data,
      headers ? { headers } : undefined,
    )
    return response.data
  },

  // ==========================================================================
  // Producer Endpoints
  // ==========================================================================

  /**
   * Get list of all conversations for Producer user
   * @param page Optional page number for pagination
   * @returns Paginated list of conversations
   */
  async getProducerConversations(page = 1): Promise<ConversationsListResponse> {
    const response = await apiClient.get<ConversationsListResponse>('/producer/conversations', {
      params: { page },
    })
    syncMessagesUnreadFromServer(response.data?.meta?.unread_conversations_count)
    return response.data
  },

  /**
   * Get a conversation with its messages (Producer)
   * Also marks unread messages from Face as read
   * @param conversationId The conversation ID to fetch
   * @returns Conversation data with messages
   */
  async getProducerConversation(conversationId: string): Promise<ConversationResponse> {
    const response = await apiClient.get<ConversationResponse>(
      `/producer/conversations/${conversationId}`,
    )
    syncMessagesUnreadFromServer(response.data?.meta?.unread_conversations_count)
    return response.data
  },

  /**
   * Send a message in a conversation (Producer)
   * @param conversationId The conversation ID to send message to
   * @param data Message content
   * @returns Created message data
   */
  async sendProducerMessage(
    conversationId: string,
    data: SendMessageData,
  ): Promise<MessageResponse> {
    const headers = await getSocketIdHeaders()
    const response = await apiClient.post<MessageResponse>(
      `/producer/conversations/${conversationId}/messages`,
      data,
      headers ? { headers } : undefined,
    )
    return response.data
  },

  /**
   * Mark the other participant's messages as read without reloading the thread (idempotent)
   * @param role Which API namespace to use
   */
  async markConversationRead(role: 'face' | 'producer', conversationId: string): Promise<MarkReadResponse> {
    const headers = await getSocketIdHeaders()
    const response = await apiClient.post<MarkReadResponse>(
      `/${role}/conversations/${conversationId}/read`,
      {},
      headers ? { headers } : undefined,
    )
    syncMessagesUnreadFromServer(response.data?.data?.unread_conversations_count)
    return response.data
  },
}
