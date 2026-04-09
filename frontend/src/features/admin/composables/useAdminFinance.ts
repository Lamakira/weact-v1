import { ref } from 'vue'
import {
  adminFinanceApi,
  type FinanceOverview,
  type PaginationMeta,
  type WithdrawalEntry,
  type WithdrawalRequestEntry,
} from '../services/adminFinanceApi'
import { getApiErrorMessage } from '../services/adminAuthApi'

export type WithdrawalRequestStatusFilter = 'all' | 'pending' | 'approved' | 'rejected'

export function useAdminFinance() {
  const overview = ref<FinanceOverview | null>(null)
  const withdrawals = ref<WithdrawalEntry[]>([])
  const withdrawalRequests = ref<WithdrawalRequestEntry[]>([])
  const withdrawalRequestsPagination = ref<PaginationMeta | null>(null)
  const currentWithdrawalRequestsPage = ref(1)
  const currentWithdrawalRequestsStatus = ref<WithdrawalRequestStatusFilter>('pending')
  const isLoadingOverview = ref(false)
  const isLoadingWithdrawals = ref(false)
  const isLoadingWithdrawalRequests = ref(false)
  const isSubmittingWithdrawalRequest = ref(false)
  const error = ref<string | null>(null)
  const withdrawalRequestError = ref<string | null>(null)
  const withdrawalRequestSuccess = ref<string | null>(null)

  async function fetchOverview(): Promise<void> {
    isLoadingOverview.value = true
    error.value = null
    try {
      const res = await adminFinanceApi.getOverview()
      overview.value = res.data
    } catch {
      error.value = 'Impossible de charger les données financières.'
    } finally {
      isLoadingOverview.value = false
    }
  }

  async function fetchWithdrawals(): Promise<void> {
    isLoadingWithdrawals.value = true
    try {
      const res = await adminFinanceApi.getRecentWithdrawals()
      withdrawals.value = res.data
    } catch {
      // non-fatal
    } finally {
      isLoadingWithdrawals.value = false
    }
  }

  async function fetchWithdrawalRequests(
    status: WithdrawalRequestStatusFilter = currentWithdrawalRequestsStatus.value,
    page = 1,
  ): Promise<void> {
    isLoadingWithdrawalRequests.value = true
    withdrawalRequestError.value = null

    try {
      const response = await adminFinanceApi.getWithdrawalRequests({
        status: status === 'all' ? undefined : status,
        page,
      })

      withdrawalRequests.value = response.data
      withdrawalRequestsPagination.value = response.meta
      currentWithdrawalRequestsStatus.value = status
      currentWithdrawalRequestsPage.value = page
    } catch (err) {
      withdrawalRequestError.value = getApiErrorMessage(err) ?? 'Impossible de charger les demandes de retrait.'
      withdrawalRequests.value = []
      withdrawalRequestsPagination.value = null
    } finally {
      isLoadingWithdrawalRequests.value = false
    }
  }

  async function approveWithdrawalRequest(id: string): Promise<boolean> {
    isSubmittingWithdrawalRequest.value = true
    withdrawalRequestError.value = null
    withdrawalRequestSuccess.value = null

    try {
      const response = await adminFinanceApi.approveWithdrawalRequest(id)
      withdrawalRequestSuccess.value = response.message
      await Promise.all([
        fetchOverview(),
        fetchWithdrawals(),
        fetchWithdrawalRequests(currentWithdrawalRequestsStatus.value, currentWithdrawalRequestsPage.value),
      ])
      return true
    } catch (err) {
      withdrawalRequestError.value = getApiErrorMessage(err) ?? 'Impossible d’approuver la demande.'
      return false
    } finally {
      isSubmittingWithdrawalRequest.value = false
    }
  }

  async function rejectWithdrawalRequest(id: string, notes: string): Promise<boolean> {
    isSubmittingWithdrawalRequest.value = true
    withdrawalRequestError.value = null
    withdrawalRequestSuccess.value = null

    try {
      const response = await adminFinanceApi.rejectWithdrawalRequest(id, notes)
      withdrawalRequestSuccess.value = response.message
      await Promise.all([
        fetchOverview(),
        fetchWithdrawalRequests(currentWithdrawalRequestsStatus.value, currentWithdrawalRequestsPage.value),
      ])
      return true
    } catch (err) {
      withdrawalRequestError.value = getApiErrorMessage(err) ?? 'Impossible de rejeter la demande.'
      return false
    } finally {
      isSubmittingWithdrawalRequest.value = false
    }
  }

  return {
    overview,
    withdrawals,
    withdrawalRequests,
    withdrawalRequestsPagination,
    currentWithdrawalRequestsPage,
    currentWithdrawalRequestsStatus,
    isLoadingOverview,
    isLoadingWithdrawals,
    isLoadingWithdrawalRequests,
    isSubmittingWithdrawalRequest,
    error,
    withdrawalRequestError,
    withdrawalRequestSuccess,
    fetchOverview,
    fetchWithdrawals,
    fetchWithdrawalRequests,
    approveWithdrawalRequest,
    rejectWithdrawalRequest,
  }
}

export type { PaginationMeta }
