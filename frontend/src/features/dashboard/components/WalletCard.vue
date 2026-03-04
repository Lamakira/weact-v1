<script setup lang="ts">
/**
 * WalletCard Component
 * Displays the Face's wallet balance as a quick-access card on the dashboard.
 * Links to /face/wallet for the full wallet page.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Wallet } from 'lucide-vue-next'

interface Props {
  balance?: number
}

const props = withDefaults(defineProps<Props>(), { balance: 0 })

const formattedBalance = computed(() =>
  new Intl.NumberFormat('fr-FR', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(props.balance),
)
</script>

<template>
  <RouterLink
    :to="{ name: 'face-wallet' }"
    class="group relative bg-white border border-gray-100 rounded-2xl p-4 shadow-sm overflow-hidden hover:border-[#198496] hover:shadow-md transition-all duration-200"
    :aria-label="`Portefeuille: ${formattedBalance} XOF`"
    data-testid="wallet-card"
  >
    <div class="flex flex-col gap-3">
      <div class="flex items-center justify-between">
        <!-- Icon Container -->
        <div
          class="h-11 w-11 rounded-xl bg-gray-50 flex items-center justify-center border border-gray-100 group-hover:bg-[#198496]/10 group-hover:border-[#198496]/20 transition-colors"
          data-testid="wallet-card-icon"
        >
          <Wallet class="h-5 w-5 text-gray-400 group-hover:text-[#198496] transition-colors" />
        </div>
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

    <!-- Decorative element -->
    <div class="absolute -bottom-2 -right-2 opacity-5 pointer-events-none">
      <Wallet class="h-16 w-16 text-gray-900" />
    </div>
  </RouterLink>
</template>
