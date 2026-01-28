import apiClient from '@/services/apiClient'
import type { ConversationResponse, MessageResponse, SendMessageData } from '../types'

/**
 * Messaging API service
 * Endpoints for Face users to manage conversations and messages
 */
export const messagingApi = {
  /**
   * Get a conversation with its messages (Face)
   * Also marks unread messages from other participant as read
   * @param conversationId The conversation ID to fetch
   * @returns Conversation data with messages
   */
  async getConversation(conversationId: number): Promise<ConversationResponse> {
    const response = await apiClient.get<ConversationResponse>(
      `/face/conversations/${conversationId}`,
    )
    return response.data
  },

  /**
   * Send a message in a conversation (Face)
   * @param conversationId The conversation ID to send message to
   * @param data Message content
   * @returns Created message data
   */
  async sendMessage(conversationId: number, data: SendMessageData): Promise<MessageResponse> {
    const response = await apiClient.post<MessageResponse>(
      `/face/conversations/${conversationId}/messages`,
      data,
    )
    return response.data
  },
}
