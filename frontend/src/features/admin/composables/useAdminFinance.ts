import { ref } from 'vue'
import { adminFinanceApi, type FinanceOverview, type WithdrawalEntry } from '../services/adminFinanceApi'

export function useAdminFinance() {
  const overview = ref<FinanceOverview | null>(null)
  const withdrawals = ref<WithdrawalEntry[]>([])
  const isLoadingOverview = ref(false)
  const isLoadingWithdrawals = ref(false)
  const error = ref<string | null>(null)

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

  return {
    overview,
    withdrawals,
    isLoadingOverview,
    isLoadingWithdrawals,
    error,
    fetchOverview,
    fetchWithdrawals,
  }
}
