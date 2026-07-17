import { ref, type Ref } from 'vue'
import {
  adminBookingsApi,
  type AdminBookingData,
  type AdminBookingListParams,
} from '../services/adminBookingsApi'
import { getApiErrorMessage } from '../services/adminAuthApi'

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

/**
 * Composable for admin booking management operations (read-only)
 */
export function useAdminBookings() {
  const bookings: Ref<AdminBookingData[]> = ref([])
  const booking: Ref<AdminBookingData | null> = ref(null)
  const pagination: Ref<PaginationMeta | null> = ref(null)
  const isLoading = ref(false)
  const error: Ref<string | null> = ref(null)

  /**
   * Fetch paginated list of bookings with optional search/filter
   */
  async function fetchBookings(params?: AdminBookingListParams): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminBookingsApi.getBookings(params)
      bookings.value = response.data
      pagination.value = response.meta
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      bookings.value = []
      pagination.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Fetch a single booking by ID
   */
  async function fetchBooking(id: string): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminBookingsApi.getBooking(id)
      booking.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      booking.value = null
    } finally {
      isLoading.value = false
    }
  }

  return {
    bookings,
    booking,
    pagination,
    isLoading,
    error,
    fetchBookings,
    fetchBooking,
  }
}
