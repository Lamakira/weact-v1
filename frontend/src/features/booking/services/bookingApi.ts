import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { CreateBookingData, BookingResponse } from '../types'

/**
 * Booking API service
 */
export const bookingApi = {
  /**
   * Create a new booking request
   * @param data The booking data to create
   */
  async createBooking(data: CreateBookingData): Promise<BookingResponse> {
    await getCsrfCookie()

    const response = await apiClient.post<BookingResponse>('/bookings', data)
    return response.data
  },
}
