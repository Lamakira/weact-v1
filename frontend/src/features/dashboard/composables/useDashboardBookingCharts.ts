import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type { BookingMonthlyStats, MonthlyCount } from '../types'
import { dashboardApi } from '../services/dashboardApi'

export function useDashboardBookingCharts() {
  const bookingsByMonth = ref<BookingMonthlyStats[]>([])
  const bookingsCompletedByMonth = ref<MonthlyCount[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchBookingChartStats(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await dashboardApi.getBookingChartStats()
      bookingsByMonth.value = response.data.bookings_by_month
      bookingsCompletedByMonth.value = response.data.bookings_completed_by_month
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.code === 'ERR_NETWORK' || err.code === 'ECONNABORTED') {
          error.value = 'Impossible de se connecter au serveur.'
        } else {
          error.value = err.response?.data?.error?.message || 'Une erreur est survenue'
        }
      } else {
        error.value = 'Une erreur inattendue est survenue'
      }
    } finally {
      isLoading.value = false
    }
  }

  return {
    bookingsByMonth,
    bookingsCompletedByMonth,
    isLoading,
    error,
    fetchBookingChartStats,
    retry: fetchBookingChartStats,
  }
}
