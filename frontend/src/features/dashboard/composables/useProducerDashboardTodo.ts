import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type { ProducerTodoItem } from '../types'
import { dashboardApi } from '../services/dashboardApi'
import { formatApiError } from '@/services/errorFormatter'
import { useTodoAutoRefresh } from './useTodoAutoRefresh'

/**
 * Composable for the Producer dashboard « À faire » queue.
 */
export function useProducerDashboardTodo() {
  const items = ref<ProducerTodoItem[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchTodo(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await dashboardApi.getProducerTodo()
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

  // Recharge au retour sur l'onglet et à chaque notification temps réel
  useTodoAutoRefresh(fetchTodo)

  return { items, isLoading, error, fetchTodo, retry: fetchTodo }
}
