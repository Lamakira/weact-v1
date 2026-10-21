import apiClient, { getCsrfCookie } from '@/services/apiClient'
import { syncMessagesUnreadFromServer } from '@/stores/messagesUnread'
import type { BookingMessageListResponse, BookingMessageResponse } from '../types'

/**
 * Booking chat API service — handles REST calls for booking messages.
 * Real-time delivery is handled separately via Laravel Echo in useBookingChat.
 */
export const bookingChatApi = {
  /**
   * Fetch paginated messages for a booking (page 1 = 30 most recent, each page in chronological order).
   */
  async fetchMessages(bookingId: string, page = 1): Promise<BookingMessageListResponse> {
    const response = await apiClient.get<BookingMessageListResponse>(
      `/bookings/${bookingId}/messages?page=${page}`,
    )
    return response.data
  },

  /**
   * Send a new message in a booking chat.
   */
  async sendMessage(bookingId: string, content: string): Promise<BookingMessageResponse> {
    await getCsrfCookie()

    let socketId: string | undefined
    try {
      const { echo } = await import('@/plugins/echo')
      socketId = echo.socketId()
    } catch {
      // If Echo is not ready, send without socket id (server still accepts request)
    }

    const response = await apiClient.post<BookingMessageResponse>(
      `/bookings/${bookingId}/messages`,
      { content },
      socketId
        ? {
            headers: {
              'X-Socket-ID': socketId,
            },
          }
        : undefined,
    )
    return response.data
  },

  /**
   * Mark the other party's messages as read (idempotent) and sync the Messages badge.
   */
  async markRead(bookingId: string): Promise<void> {
    const response = await apiClient.post<{ data: { unread_conversations_count?: number } }>(
      `/bookings/${bookingId}/messages/read`,
    )
    syncMessagesUnreadFromServer(response.data?.data?.unread_conversations_count)
  },
}
