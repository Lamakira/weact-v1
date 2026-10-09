<script setup lang="ts">
/**
 * FaceWalletPanel — panneau « Portefeuille » du dashboard Face (direction Régie) :
 * solde, montant en séquestre, date du dernier virement (si connue), bouton Retirer.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import RPanel from '@/components/regie/RPanel.vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { formatXof } from '@/lib/formatCurrency'

interface Props {
  balance: number
  pendingEscrow?: number
  /** ISO du dernier retrait traité (approuvé), null si aucun */
  lastWithdrawalAt?: string | null
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  pendingEscrow: 0,
  lastWithdrawalAt: null,
  isLoading: false,
})

const lastWithdrawalLabel = computed<string | null>(() => {
  if (!props.lastWithdrawalAt) return null
  const date = new Date(props.lastWithdrawalAt)
  if (Number.isNaN(date.getTime())) return null
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(date)
})
</script>

<template>
  <RPanel title="Portefeuille" data-testid="wallet-card">
    <div class="p-4 sm:p-5">
      <Skeleton v-if="isLoading && balance === 0" class="h-8 w-40" data-testid="wallet-skeleton" />
      <p v-else class="text-[26px] font-semibold leading-none tracking-[-0.02em] text-ink" data-testid="wallet-balance">
        {{ formatXof(balance) }}
      </p>

      <dl class="mt-4 space-y-2 border-t border-line pt-3 text-dash">
        <div class="flex items-center justify-between">
          <dt class="text-ink-3">En séquestre</dt>
          <dd class="font-semibold text-ink" data-testid="wallet-escrow">{{ formatXof(pendingEscrow) }}</dd>
        </div>
        <div v-if="lastWithdrawalLabel" class="flex items-center justify-between">
          <dt class="text-ink-3">Dernier virement</dt>
          <dd class="font-semibold text-ink" data-testid="wallet-last-withdrawal">{{ lastWithdrawalLabel }}</dd>
        </div>
      </dl>

      <Button as-child variant="regie-secondary" size="regie" class="mt-4 w-full">
        <RouterLink :to="{ name: 'face-wallet' }" data-testid="wallet-withdraw-link">Retirer</RouterLink>
      </Button>
    </div>
  </RPanel>
</template>
