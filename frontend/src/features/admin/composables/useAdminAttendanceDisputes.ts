import { ref } from 'vue'
import {
  adminAttendanceDisputesApi,
  type AdminDispute,
  type DisputeOutcome,
} from '../services/adminAttendanceDisputesApi'
import type { PaginationMeta } from '../services/adminFinanceApi'
import { getApiErrorMessage } from '../services/adminAuthApi'

interface FetchDisputesOptions {
  clearResolveSuccess?: boolean
}

export function useAdminAttendanceDisputes() {
  const disputes = ref<AdminDispute[]>([])
  const pagination = ref<PaginationMeta | null>(null)
  const currentPage = ref(1)
  const isLoading = ref(false)
  const isResolving = ref(false)
  const error = ref<string | null>(null)
  const resolveError = ref<string | null>(null)
  const resolveSuccess = ref<string | null>(null)

  async function fetchDisputes(page = 1, options: FetchDisputesOptions = {}): Promise<boolean> {
    const { clearResolveSuccess = true } = options
    isLoading.value = true
    error.value = null
    if (clearResolveSuccess) {
      resolveSuccess.value = null
    }

    try {
      const response = await adminAttendanceDisputesApi.getDisputes({ page })
      disputes.value = response.data
      pagination.value = response.meta
      currentPage.value = page
      return true
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Impossible de charger les litiges.'
      disputes.value = []
      pagination.value = null
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function resolveDispute(
    id: number,
    outcome: DisputeOutcome,
    notes: string,
  ): Promise<boolean> {
    isResolving.value = true
    resolveError.value = null
    resolveSuccess.value = null

    try {
      const response = await adminAttendanceDisputesApi.resolveDispute(id, outcome, notes)
      resolveSuccess.value = response.message

      const refetched = await fetchDisputes(currentPage.value, { clearResolveSuccess: false })

      if (
        refetched
        && disputes.value.length === 0
        && currentPage.value > 1
      ) {
        await fetchDisputes(currentPage.value - 1, { clearResolveSuccess: false })
      }

      if (!refetched) {
        resolveError.value = `Litige résolu, mais la liste n'a pas pu être rafraîchie : ${error.value}. Cliquez sur Actualiser pour réessayer.`
        return true
      }

      return true
    } catch (err) {
      resolveError.value = getApiErrorMessage(err) ?? 'Impossible de résoudre le litige.'
      await fetchDisputes(currentPage.value, { clearResolveSuccess: false })
      return false
    } finally {
      isResolving.value = false
    }
  }

  return {
    disputes,
    pagination,
    currentPage,
    isLoading,
    isResolving,
    error,
    resolveError,
    resolveSuccess,
    fetchDisputes,
    resolveDispute,
  }
}
