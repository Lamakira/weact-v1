import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type {
  AdminDashboardTrends,
  RecentActivityEvent,
} from '../services/adminDashboardApi'
import { adminDashboardApi } from '../services/adminDashboardApi'

/**
 * Composable for fetching dashboard trends and recent activity
 */
export function useAdminDashboardTrends() {
  const trends = ref<AdminDashboardTrends | null>(null)
  const recentActivity = ref<RecentActivityEvent[]>([])
  const isTrendsLoading = ref(false)
  const isActivityLoading = ref(false)
  const trendsError = ref<string | null>(null)
  const activityError = ref<string | null>(null)

  function handleError(err: unknown): string {
    if (isAxiosError(err)) {
      if (err.response?.status === 401) {
        return 'Veuillez vous connecter pour voir les statistiques'
      }
      if (err.response?.status === 403) {
        return 'Accès réservé aux administrateurs'
      }
      if (err.code === 'ERR_NETWORK' || err.code === 'ECONNABORTED') {
        return 'Impossible de se connecter au serveur. Vérifiez votre connexion.'
      }
      return err.response?.data?.message || 'Une erreur est survenue'
    }
    return 'Une erreur inattendue est survenue'
  }

  async function fetchTrends(): Promise<void> {
    isTrendsLoading.value = true
    trendsError.value = null

    try {
      const response = await adminDashboardApi.getTrends()
      trends.value = response.data
    } catch (err: unknown) {
      trendsError.value = handleError(err)
    } finally {
      isTrendsLoading.value = false
    }
  }

  async function fetchRecentActivity(): Promise<void> {
    isActivityLoading.value = true
    activityError.value = null

    try {
      const response = await adminDashboardApi.getRecentActivity()
      recentActivity.value = response.data
    } catch (err: unknown) {
      activityError.value = handleError(err)
    } finally {
      isActivityLoading.value = false
    }
  }

  async function retryTrends(): Promise<void> {
    await fetchTrends()
  }

  async function retryActivity(): Promise<void> {
    await fetchRecentActivity()
  }

  return {
    trends,
    recentActivity,
    isTrendsLoading,
    isActivityLoading,
    trendsError,
    activityError,
    fetchTrends,
    fetchRecentActivity,
    retryTrends,
    retryActivity,
  }
}
