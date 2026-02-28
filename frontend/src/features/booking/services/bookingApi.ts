import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { CreateBookingData, BookingResponse } from '../types'

/**
 * Booking API service
 */
export const bookingApi = {
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
}
