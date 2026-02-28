import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import type { Booking, BookingFilterStatus } from '../types'
import { bookingApi } from '../services/bookingApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

/**
 * Composable for fetching and managing bookings list (Face or Producer)
 */
export function useBookingsList() {
  const bookings = ref<Booking[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Pagination state
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const perPage = ref(15)

  // Filter state
  const statusFilter = ref<BookingFilterStatus>('')

  // Request tracking to prevent race conditions
  let currentRequestId = 0

  // Computed
  const hasNextPage = computed(() => currentPage.value < lastPage.value)
  const hasPrevPage = computed(() => currentPage.value > 1)
  const isEmpty = computed(() => bookings.value.length === 0 && !isLoading.value)

  /**
   * Fetch bookings with current page and filter
   */
  async function fetchBookings(page: number = 1): Promise<void> {
    const requestId = ++currentRequestId
    isLoading.value = true
    error.value = null

    try {
      const response = await bookingApi.getBookings(page, statusFilter.value)

      // Ignore stale responses
      if (requestId !== currentRequestId) return

      bookings.value = response.data
      currentPage.value = response.meta.current_page
      lastPage.value = response.meta.last_page
      total.value = response.meta.total
      perPage.value = response.meta.per_page
    } catch (err: unknown) {
      if (requestId !== currentRequestId) return

      if (isAxiosError(err)) {
        if (err.response?.status === 401) {
          error.value = 'Veuillez vous connecter pour voir vos bookings'
        } else {
          error.value = getApiErrorMessage(err)
        }
      } else {
        error.value = 'Une erreur inattendue est survenue'
      }
    } finally {
      if (requestId === currentRequestId) {
        isLoading.value = false
      }
    }
  }

  async function nextPage(): Promise<void> {
    if (hasNextPage.value) {
      await fetchBookings(currentPage.value + 1)
    }
  }

  async function prevPage(): Promise<void> {
    if (hasPrevPage.value) {
      await fetchBookings(currentPage.value - 1)
    }
  }

  async function goToPage(page: number): Promise<void> {
    if (page >= 1 && page <= lastPage.value) {
      await fetchBookings(page)
    }
  }

  async function setStatusFilter(status: BookingFilterStatus): Promise<void> {
    statusFilter.value = status
    await fetchBookings(1)
  }

  async function clearFilter(): Promise<void> {
    await setStatusFilter('')
  }

  async function refresh(): Promise<void> {
    await fetchBookings(currentPage.value)
  }

  return {
    // State
    bookings,
    isLoading,
    error,
    // Pagination
    currentPage,
    lastPage,
    total,
    perPage,
    hasNextPage,
    hasPrevPage,
    isEmpty,
    // Filter
    statusFilter,
    // Actions
    fetchBookings,
    nextPage,
    prevPage,
    goToPage,
    setStatusFilter,
    clearFilter,
    refresh,
  }
}
