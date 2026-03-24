<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { AlertCircle } from 'lucide-vue-next'
import {
  WalletBalance,
  WalletTransactionList,
  WalletWithdrawForm,
  useWallet,
} from '@/features/wallet'
import type { WithdrawPayload } from '@/features/wallet/services/walletApi'

const {
  balance,
  pendingEscrow,
  withdrawalMode,
  transactions,
  isLoading,
  error,
  hasMore,
  fetchWallet,
  loadMore,
  isWithdrawing,
  withdrawError,
  withdraw,
} = useWallet()

const showWithdrawForm = ref(false)

async function handleWithdraw(payload: WithdrawPayload): Promise<void> {
  const success = await withdraw(payload)
  if (success) {
    showWithdrawForm.value = false
  }
}

onMounted(() => {
  fetchWallet()
})
</script>

<template>
  <div data-testid="face-wallet-page">
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900">Portefeuille</h1>
      <p class="text-sm text-gray-500 mt-1">Consultez votre solde et l'historique de vos transactions.</p>
    </div>

    <div
      v-if="error"
      class="mb-6 flex items-center gap-3 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700"
      data-testid="wallet-error"
    >
      <AlertCircle class="h-4 w-4 flex-shrink-0" />
      <span>{{ error }}</span>
      <button
        class="ml-auto text-xs font-medium underline hover:no-underline"
        @click="fetchWallet()"
      >
        Réessayer
      </button>
    </div>

    <WalletBalance
      :balance="balance"
      :pending-escrow="pendingEscrow"
      @withdraw="showWithdrawForm = true"
    />

    <WalletWithdrawForm
      v-if="showWithdrawForm"
      :balance="balance"
      :withdrawal-mode="withdrawalMode"
      :is-withdrawing="isWithdrawing"
      :error="withdrawError"
      @submit="handleWithdraw"
      @cancel="showWithdrawForm = false"
    />

    <WalletTransactionList
      :transactions="transactions"
      :is-loading="isLoading"
      :has-more="hasMore"
      @load-more="loadMore"
    />
  </div>
</template>
