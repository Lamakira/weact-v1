import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import { walletApi } from '../services/walletApi'
import type { WalletData, WalletTransaction, WalletTransactionsMeta } from '../types/wallet'
import type { WithdrawPayload } from '../services/walletApi'

export function useWallet() {
  const walletData = ref<WalletData | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const currentPage = ref(1)

  const isWithdrawing = ref(false)
  const withdrawError = ref<string | null>(null)

  const balance = computed<number>(() => walletData.value?.balance ?? 0)
  const pendingEscrow = computed<number>(() => walletData.value?.pending_escrow ?? 0)
  const withdrawalMode = computed<'manual' | 'fedapay'>(() => walletData.value?.withdrawal_mode ?? 'manual')
  const transactions = computed<WalletTransaction[]>(() => walletData.value?.transactions ?? [])
  const meta = computed<WalletTransactionsMeta | null>(() => walletData.value?.transactions_meta ?? null)
  const hasMore = computed<boolean>(
    () => (meta.value?.current_page ?? 0) < (meta.value?.last_page ?? 0),
  )

  async function fetchWallet(page = 1): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const response = await walletApi.getWallet(page)
      if (page === 1) {
        walletData.value = response.data
      } else {
        // Append transactions for subsequent pages
        walletData.value = {
          ...response.data,
          transactions: [
            ...(walletData.value?.transactions ?? []),
            ...response.data.transactions,
          ],
        }
      }
      currentPage.value = page
    } catch {
      error.value = 'Impossible de charger le portefeuille.'
    } finally {
      isLoading.value = false
    }
  }

  async function loadMore(): Promise<void> {
    if (hasMore.value && !isLoading.value) {
      await fetchWallet(currentPage.value + 1)
    }
  }

  async function withdraw(payload: WithdrawPayload): Promise<boolean> {
    isWithdrawing.value = true
    withdrawError.value = null
    try {
      await walletApi.withdraw(payload)
      await fetchWallet(1) // Refresh balance + transaction list
      return true
    } catch (err: unknown) {
      if (isAxiosError(err) && err.response?.data?.errors?.amount) {
        withdrawError.value = err.response.data.errors.amount[0]
      } else if (isAxiosError(err) && err.response?.data?.message) {
        withdrawError.value = err.response.data.message
      } else {
        withdrawError.value = 'Retrait échoué. Veuillez réessayer.'
      }
      return false
    } finally {
      isWithdrawing.value = false
    }
  }

  return {
    isLoading,
    error,
    balance,
    pendingEscrow,
    withdrawalMode,
    transactions,
    meta,
    hasMore,
    fetchWallet,
    loadMore,
    isWithdrawing,
    withdrawError,
    withdraw,
  }
}
