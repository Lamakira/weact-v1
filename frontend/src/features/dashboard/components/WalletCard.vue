<script setup lang="ts">
/**
 * WalletCard Component
 * Displays a placeholder state for the wallet feature (FR52).
 * UI present but functionality inactive for MVP.
 * Pattern matched to existing KpiCard component styles.
 */
import { Wallet } from 'lucide-vue-next'

/**
 * Format number for French locale display (no currency suffix)
 * XOF suffix is added separately in template for styling flexibility
 * Establishes pattern for V2 when real balance is available
 */
const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

// Static balance for MVP - always 0
const balance = 0
const formattedBalance = formatCurrency(balance)
</script>

<template>
  <div
    class="relative bg-white border border-gray-100 rounded-2xl p-4 shadow-sm opacity-75 cursor-default select-none overflow-hidden"
    aria-label="Portefeuille: 0 XOF (Bientôt disponible)"
    role="region"
    data-testid="wallet-card"
  >
    <div class="flex flex-col gap-3">
      <div class="flex items-center justify-between">
        <!-- Icon Container -->
        <div
          class="h-11 w-11 rounded-xl bg-gray-50 flex items-center justify-center border border-gray-100"
          data-testid="wallet-card-icon"
        >
          <Wallet class="h-5 w-5 text-gray-400" />
        </div>

        <!-- Status Badge -->
        <span
          class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500 uppercase tracking-wider"
          data-testid="wallet-card-badge"
        >
          Bientôt disponible
        </span>
      </div>

      <div class="space-y-1">
        <!-- Label -->
        <p class="text-sm text-gray-500" data-testid="wallet-card-label">
          Solde portefeuille
        </p>

        <!-- Value -->
        <div class="flex items-baseline gap-1">
          <span
            class="text-xl font-bold text-gray-900"
            data-testid="wallet-card-value"
          >
            {{ formattedBalance }}
          </span>
          <span class="text-sm font-semibold text-gray-400 uppercase">XOF</span>
        </div>
      </div>
    </div>

    <!-- Subdued Decorative Element -->
    <div class="absolute -bottom-2 -right-2 opacity-5 pointer-events-none">
      <Wallet class="h-16 w-16 text-gray-900" />
    </div>
  </div>
</template>
