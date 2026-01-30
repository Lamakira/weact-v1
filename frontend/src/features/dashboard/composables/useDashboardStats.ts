import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import type { DashboardStats } from '../types'
import { dashboardApi } from '../services/dashboardApi'

/**
 * Composable for fetching and managing Face dashboard statistics
 */
export function useDashboardStats() {
  const stats = ref<DashboardStats | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Computed: check if we have data
  const hasStats = computed(() => stats.value !== null)

  // Computed: total candidatures (excluding rejected)
  const totalCandidatures = computed(() => {
    if (!stats.value) return 0
    return stats.value.pending + stats.value.accepted + stats.value.in_progress + stats.value.completed
  })

  /**
   * Fetch dashboard statistics from API
   */
  async function fetchStats(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await dashboardApi.getStats()
      stats.value = response.data
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
    totalCandidatures,
    // Actions
    fetchStats,
    retry,
    clearError,
  }
}
