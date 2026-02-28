import { ref, type Ref } from 'vue'
import { bookingApi } from '../services/bookingApi'
import type { Booking } from '../types'

export interface UseBookingDetailReturn {
  booking: Ref<Booking | null>
  isLoading: Ref<boolean>
  error: Ref<string | null>
  notFound: Ref<boolean>
  fetchBooking: (id: number) => Promise<void>
  refresh: () => Promise<void>
}

export function useBookingDetail(): UseBookingDetailReturn {
  const booking = ref<Booking | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const notFound = ref(false)
  let currentId: number | null = null

  async function fetchBooking(id: number): Promise<void> {
    currentId = id
    isLoading.value = true
    error.value = null
    notFound.value = false

    try {
      const response = await bookingApi.fetchBooking(id)
      booking.value = response.data
    } catch (err: unknown) {
      const axiosError = err as { response?: { status?: number } }
      if (axiosError.response?.status === 404) {
        notFound.value = true
        error.value = 'Booking non trouvé'
      } else if (axiosError.response?.status === 403) {
        error.value = 'Vous n\'avez pas accès à ce booking'
      } else {
        error.value = 'Une erreur est survenue. Veuillez réessayer.'
      }
    } finally {
      isLoading.value = false
    }
  }

  async function refresh(): Promise<void> {
    if (currentId !== null) {
      await fetchBooking(currentId)
    }
  }

  return {
    booking,
    isLoading,
    error,
    notFound,
    fetchBooking,
    refresh,
  }
}
