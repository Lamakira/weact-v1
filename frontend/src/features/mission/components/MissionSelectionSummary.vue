<script setup lang="ts">
import { Loader2, CreditCard, Users } from 'lucide-vue-next'
import type { MissionPricingPreview } from '../composables/useMissionPayment'

const props = defineProps<{
  pricing: MissionPricingPreview
  isConfirming: boolean
}>()

const emit = defineEmits<{
  confirm: []
}>()

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR').format(amount) + ' XOF'
}
</script>

<template>
  <div class="rounded-xl border border-primary/20 bg-primary/5 p-5 shadow-md">
    <div class="mb-4 flex items-center gap-2 text-sm font-semibold text-primary">
      <Users class="h-4 w-4" />
      Résumé de la sélection
    </div>

    <div class="space-y-2 text-sm">
      <div class="flex justify-between">
        <span class="text-muted-foreground">Faces sélectionnées</span>
        <span class="font-medium text-foreground">{{ pricing.nombreFaces }}</span>
      </div>
      <div class="flex justify-between">
        <span class="text-muted-foreground">Budget par Face</span>
        <span class="font-medium text-foreground">{{ formatCurrency(pricing.budgetParFace) }}</span>
      </div>
      <div class="flex justify-between">
        <span class="text-muted-foreground">
          Sous-total ({{ pricing.nombreFaces }} × {{ formatCurrency(pricing.budgetParFace) }})
        </span>
        <span class="font-medium text-foreground">{{ formatCurrency(pricing.sousTotal) }}</span>
      </div>
      <div class="flex justify-between">
        <span class="text-muted-foreground">Frais de service</span>
        <span class="font-medium text-foreground">{{ formatCurrency(pricing.commissionProducteur) }}</span>
      </div>
      <div class="my-2 border-t border-border/60" />
      <div class="flex justify-between text-base font-bold">
        <span class="text-foreground">Total à payer</span>
        <span class="text-primary">{{ formatCurrency(pricing.montantTotal) }}</span>
      </div>
    </div>

    <button
      type="button"
      class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
      :disabled="isConfirming"
      @click="emit('confirm')"
    >
      <Loader2 v-if="isConfirming" class="h-4 w-4 animate-spin" />
      <CreditCard v-else class="h-4 w-4" />
      {{ isConfirming ? 'Traitement en cours...' : 'Confirmer et payer →' }}
    </button>
  </div>
</template>
