import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import type { AdminDashboardStats } from '../services/adminDashboardApi'
import { adminDashboardApi } from '../services/adminDashboardApi'
import { formatApiError } from '@/services/errorFormatter'

/**
 * Composable for fetching and managing Admin dashboard statistics
 */
export function useAdminDashboardStats() {
  const stats = ref<AdminDashboardStats | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Computed: check if we have data
  const hasStats = computed(() => stats.value !== null)

  /**
   * Fetch dashboard statistics from API
   */
  async function fetchStats(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminDashboardApi.getStats()
      stats.value = response.data
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.response?.status === 401) {
          error.value = 'Veuillez vous connecter pour voir les statistiques'
        } else if (err.response?.status === 403) {
          error.value = 'Accès réservé aux administrateurs'
        } else if (err.code === 'ERR_NETWORK' || err.code === 'ECONNABORTED') {
          error.value = 'Impossible de se connecter au serveur. Vérifiez votre connexion.'
        } else {
          error.value = formatApiError(err, 'Une erreur est survenue')
        }
      } else {
        error.value = 'Une erreur inattendue est survenue'
      }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Retry fetching statistics (convenience method for error state)
   */
  async function retry(): Promise<void> {
    await fetchStats()
  }

  /**
   * Clear error state
   */
  function clearError(): void {
    error.value = null
  }

  return {
    // State
    stats,
    isLoading,
    error,
    // Computed
    hasStats,
    // Actions
    fetchStats,
    retry,
    clearError,
  }
}
