import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type { BookingStats } from '../types'
import { dashboardApi } from '../services/dashboardApi'

/**
 * Composable for fetching and managing Face booking statistics
 */
export function useBookingStats() {
  const bookingStats = ref<BookingStats | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchBookingStats(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await dashboardApi.getBookingStats()
      bookingStats.value = response.data
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.response?.status === 401) {
          error.value = 'Veuillez vous connecter pour voir vos statistiques'
        } else if (err.response?.status === 403) {
          error.value = 'Accès réservé aux Faces'
        } else if (err.code === 'ERR_NETWORK' || err.code === 'ECONNABORTED') {
          error.value = 'Impossible de se connecter au serveur. Vérifiez votre connexion.'
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
    bookingStats,
    isLoading,
    error,
    fetchBookingStats,
  }
}
