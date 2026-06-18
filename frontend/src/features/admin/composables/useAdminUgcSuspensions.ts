import { ref } from 'vue'
import { adminUgcSuspensionsApi, type AdminUgcSuspension } from '../services/adminUgcSuspensionsApi'
import type { PaginationMeta } from '../services/adminFinanceApi'
import { getApiErrorMessage } from '../services/adminAuthApi'

interface FetchOptions {
  clearActionSuccess?: boolean
}

/**
 * Composable admin de la file des appels de suspension UGC (story 5.4). Calque
 * useAdminAttendanceDisputes : refetch-après-action + recule d'une page si la
 * page courante se vide après la dernière ligne. reactivate/rejectAppeal
 * partagent runAction (les endpoints 5.3 ne prennent aucun body — D-5.4.e).
 */
export function useAdminUgcSuspensions() {
  const suspensions = ref<AdminUgcSuspension[]>([])
  const pagination = ref<PaginationMeta | null>(null)
  const currentPage = ref(1)
  const isLoading = ref(false)
  const isActing = ref(false)
  const error = ref<string | null>(null)
  const actionError = ref<string | null>(null)
  const actionSuccess = ref<string | null>(null)

  async function fetchSuspensions(page = 1, options: FetchOptions = {}): Promise<boolean> {
    const { clearActionSuccess = true } = options
    isLoading.value = true
    error.value = null
    if (clearActionSuccess) {
      actionSuccess.value = null
    }

    try {
      const response = await adminUgcSuspensionsApi.getSuspensions({ page })
      suspensions.value = response.data
      pagination.value = response.meta
      currentPage.value = page
      return true
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Impossible de charger les appels.'
      suspensions.value = []
      pagination.value = null
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function runAction(
    fn: () => Promise<{ message: string }>,
    fallbackError: string,
  ): Promise<boolean> {
    isActing.value = true
    actionError.value = null
    actionSuccess.value = null

    try {
      const response = await fn()
      actionSuccess.value = response.message

      const refetched = await fetchSuspensions(currentPage.value, { clearActionSuccess: false })

      if (refetched && suspensions.value.length === 0 && currentPage.value > 1) {
        await fetchSuspensions(currentPage.value - 1, { clearActionSuccess: false })
      }

      if (!refetched) {
        actionError.value = `Action effectuée, mais la liste n'a pas pu être rafraîchie : ${error.value}. Cliquez sur Actualiser.`
      }

      return true
    } catch (err) {
      actionError.value = getApiErrorMessage(err) ?? fallbackError
      await fetchSuspensions(currentPage.value, { clearActionSuccess: false })
      return false
    } finally {
      isActing.value = false
    }
  }

  function reactivate(uuid: string): Promise<boolean> {
    return runAction(() => adminUgcSuspensionsApi.reactivate(uuid), 'Impossible de réactiver le compte.')
  }

  function rejectAppeal(uuid: string): Promise<boolean> {
    return runAction(() => adminUgcSuspensionsApi.rejectAppeal(uuid), "Impossible de rejeter l'appel.")
  }

  return {
    suspensions,
    pagination,
    currentPage,
    isLoading,
    isActing,
    error,
    actionError,
    actionSuccess,
    fetchSuspensions,
    reactivate,
    rejectAppeal,
  }
}
