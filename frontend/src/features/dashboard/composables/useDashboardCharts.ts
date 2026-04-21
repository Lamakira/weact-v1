import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import type { ChartStats, MonthlyStats, MonthlyCount } from '../types'
import { dashboardApi } from '../services/dashboardApi'
import { formatApiError } from '@/services/errorFormatter'

/**
 * Composable for fetching and managing Face dashboard chart statistics
 * Handles the last 6 months of activity evolution data
 */
export function useDashboardCharts() {
  const chartStats = ref<ChartStats | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Computed: check if we have chart data
  const hasChartData = computed(() => chartStats.value !== null)

  // Computed: candidatures by month data
  const candidaturesByMonth = computed((): MonthlyStats[] => {
    return chartStats.value?.candidatures_by_month ?? []
  })

  // Computed: missions completed by month data
  const missionsCompletedByMonth = computed((): MonthlyCount[] => {
    return chartStats.value?.missions_completed_by_month ?? []
  })

  // Computed: check if there's any activity data (not all zeros)
  const hasActivityData = computed(() => {
    if (!chartStats.value) return false

    // Check if any candidatures exist
    const hasCandidatures = chartStats.value.candidatures_by_month.some(
      (m) =>
        m.pending > 0 ||
        m.accepted > 0 ||
        m.confirmed > 0 ||
        m.in_progress > 0 ||
        m.completed > 0 ||
        m.rejected > 0,
    )

    // Check if any missions were completed
    const hasMissions = chartStats.value.missions_completed_by_month.some((m) => m.count > 0)

    return hasCandidatures || hasMissions
  })

  /**
   * Fetch chart statistics from API
   */
  async function fetchChartStats(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await dashboardApi.getChartStats()
      chartStats.value = response.data
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.response?.status === 401) {
          error.value = 'Veuillez vous connecter pour voir vos statistiques'
        } else if (err.response?.status === 403) {
          error.value = 'Accès réservé aux Faces'
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
   * Retry fetching chart statistics (convenience method for error state)
   */
  async function retry(): Promise<void> {
    await fetchChartStats()
  }

  /**
   * Clear error state
   */
  function clearError(): void {
    error.value = null
  }

  return {
    // State
    chartStats,
    isLoading,
    error,
    // Computed
    hasChartData,
    candidaturesByMonth,
    missionsCompletedByMonth,
    hasActivityData,
    // Actions
    fetchChartStats,
    retry,
    clearError,
  }
}
