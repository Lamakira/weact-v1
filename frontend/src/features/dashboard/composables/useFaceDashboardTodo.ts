import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type { FaceTodoItem } from '../types'
import { dashboardApi } from '../services/dashboardApi'
import { formatApiError } from '@/services/errorFormatter'

/**
 * Composable for the Face dashboard « À faire » queue.
 */
export function useFaceDashboardTodo() {
  const items = ref<FaceTodoItem[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchTodo(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await dashboardApi.getFaceTodo()
      items.value = response.data
    } catch (err: unknown) {
      if (isAxiosError(err) && (err.code === 'ERR_NETWORK' || err.code === 'ECONNABORTED')) {
        error.value = 'Impossible de se connecter au serveur. Vérifiez votre connexion.'
      } else {
        error.value = formatApiError(err, 'Impossible de charger vos tâches.')
      }
    } finally {
      isLoading.value = false
    }
  }

  return { items, isLoading, error, fetchTodo, retry: fetchTodo }
}
