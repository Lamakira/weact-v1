import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type {
  CreateBookingData,
  BookingResponse,
  BookingListResponse,
  BookingFilterStatus,
  CancellationReasonValue,
  BookingRatingResponse,
} from '../types'

/**
 * Booking API service
 */
export const bookingApi = {
  /**
   * Get paginated list of authenticated user's bookings
   */
  async getBookings(page: number = 1, status?: BookingFilterStatus): Promise<BookingListResponse> {
    const params = new URLSearchParams()
    params.append('page', String(page))
    if (status) {
      params.append('status', status)
    }
    const response = await apiClient.get<BookingListResponse>(`/bookings?${params.toString()}`)
    return response.data
  },

  /**
   * Create a new booking request
   */
  async createBooking(data: CreateBookingData): Promise<BookingResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingResponse>('/bookings', data)
    return response.data
  },

  /**
   * Fetch a single booking by ID
   */
  async fetchBooking(id: number): Promise<BookingResponse> {
    const response = await apiClient.get<BookingResponse>(`/bookings/${id}`)
    return response.data
  },

  /**
   * Accept a pending booking (Face only)
   */
  async acceptBooking(id: number): Promise<BookingResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingResponse>(`/bookings/${id}/accept`)
    return response.data
  },

  /**
   * Refuse a booking (Face only)
   */
  async refuseBooking(id: number, reason?: string): Promise<BookingResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingResponse>(`/bookings/${id}/refuse`, {
      cancellation_reason: reason || undefined,
    })
    return response.data
  },

  /**
   * Cancel a booking (Producer or Face, depending on status/role)
   */
  async cancelBooking(
    id: number,
    cancellationReason: CancellationReasonValue,
    customReason?: string,
  ): Promise<BookingResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingResponse>(`/bookings/${id}/cancel`, {
      cancellation_reason: cancellationReason,
      custom_cancellation_reason: customReason || undefined,
    })
    return response.data
  },

  /**
   * Confirm booking completion (Face or Producer)
   */
  async confirmBooking(id: number): Promise<BookingResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingResponse>(`/bookings/${id}/confirm`)
    return response.data
  },

  /**
   * Submit a rating for a completed booking.
   */
  async rateBooking(bookingId: number, score: number, comment?: string): Promise<BookingRatingResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingRatingResponse>(`/bookings/${bookingId}/rate`, {
      score,
      comment: comment || undefined,
    })
    return response.data
  },

  /**
   * Report a Face no-show on a paid booking (Producer only)
   */
  async reportNoShow(bookingId: number): Promise<BookingResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingResponse>(`/bookings/${bookingId}/report-no-show`)
    return response.data
  },

  /**
   * Check Fedapay transaction status and process if approved.
   * Fallback polling when webhook delivery is unreliable (sandbox/ngrok).
   */
  async checkPaymentStatus(id: number): Promise<BookingResponse> {
    const response = await apiClient.get<BookingResponse>(`/bookings/${id}/payment-status`)
    return response.data
  },

  /**
   * Initiate payment for an accepted booking (Producer only).
   * Returns the booking and a FedaPay hosted checkout URL.
   */
  async payBooking(bookingId: number): Promise<BookingResponse & { checkout_url: string }> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingResponse & { checkout_url: string }>(
      `/bookings/${bookingId}/pay`,
    )
    return response.data
  },
}
