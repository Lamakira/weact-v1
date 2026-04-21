import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { dashboardApi } from '../services/dashboardApi'
import { formatApiError } from '@/services/errorFormatter'

/**
 * Composable for fetching and managing available missions count
 * Used for the quick access card on Face dashboard (FR54)
 */
export function useMissionsCount() {
  const count = ref<number>(0)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  /**
   * Fetch available missions count from API
   */
  async function fetchMissionsCount(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await dashboardApi.getAvailableMissionsCount()
      count.value = response.data.count
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.response?.status === 401) {
          error.value = 'Veuillez vous connecter'
        } else if (err.response?.status === 403) {
          error.value = 'Accès réservé aux Faces'
        } else if (err.code === 'ERR_NETWORK' || err.code === 'ECONNABORTED') {
          error.value = 'Impossible de se connecter au serveur'
        } else {
          error.value = formatApiError(err, 'Une erreur est survenue')
        }
      } else {
        error.value = 'Une erreur inattendue est survenue'
      }
      // On error, keep count at 0 (fail gracefully)
      count.value = 0
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Retry fetching missions count (convenience method for error state)
   */
  async function retry(): Promise<void> {
    await fetchMissionsCount()
  }

  /**
   * Clear error state
   */
  function clearError(): void {
    error.value = null
  }

  return {
    // State
    count,
    isLoading,
    error,
    // Actions
    fetchMissionsCount,
    retry,
    clearError,
  }
}
