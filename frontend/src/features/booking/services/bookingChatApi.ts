import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { BookingMessageListResponse, BookingMessageResponse } from '../types'

/**
 * Booking chat API service — handles REST calls for booking messages.
 * Real-time delivery is handled separately via Laravel Echo in useBookingChat.
 */
export const bookingChatApi = {
  /**
   * Fetch paginated messages for a booking (oldest first, 30 per page).
   */
  async fetchMessages(bookingId: number, page = 1): Promise<BookingMessageListResponse> {
    const response = await apiClient.get<BookingMessageListResponse>(
      `/bookings/${bookingId}/messages?page=${page}`,
    )
    return response.data
  },

  /**
   * Send a new message in a booking chat.
   */
  async sendMessage(bookingId: number, content: string): Promise<BookingMessageResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingMessageResponse>(
      `/bookings/${bookingId}/messages`,
      { content },
    )
    return response.data
  },
}
