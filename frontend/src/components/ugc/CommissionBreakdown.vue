<script setup lang="ts">
import { computed } from 'vue'
import { Receipt } from 'lucide-vue-next'
import { computeUgcCommission } from './ugc'

const props = withDefaults(
  defineProps<{
    productValue: number
    payAmount?: number
  }>(),
  {
    payAmount: 0,
  },
)

const commission = computed(() => computeUgcCommission(props.productValue))
const showPay = computed(() => !!props.payAmount && props.payAmount > 0)

function formatFcfa(amount: number): string {
  return `${(amount || 0).toLocaleString('fr-FR')} FCFA`
}
</script>

<template>
  <div class="rounded-lg border border-gray-200 bg-white p-4" data-testid="commission-breakdown">
    <div class="mb-3 flex items-center gap-2">
      <Receipt :size="14" class="text-weact" />
      <div class="text-xs font-semibold uppercase tracking-wider text-gray-900">Récapitulatif</div>
    </div>

    <div class="space-y-2">
      <!-- Valeur produit -->
      <div class="flex items-center justify-between text-xs">
        <span class="text-gray-500">Valeur déclarée du produit</span>
        <span class="text-gray-700">{{ formatFcfa(productValue) }}</span>
      </div>

      <!-- Rémunération de la Face (hybride uniquement) -->
      <div v-if="showPay" class="flex items-center justify-between text-xs">
        <span class="text-gray-500">Rémunération de la Face</span>
        <span class="text-gray-700">{{ formatFcfa(payAmount) }}</span>
      </div>

      <!-- Commission WeAct -->
      <div class="flex items-center justify-between text-xs">
        <span class="flex items-center gap-1.5 font-medium text-weact">
          Commission WeAct
          <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500">
            10% min. 2 500
          </span>
        </span>
        <span class="font-semibold text-weact">{{ formatFcfa(commission) }}</span>
      </div>

      <!-- À payer maintenant -->
      <div class="mt-2 border-t border-gray-100 pt-2">
        <div class="flex items-center justify-between text-xs">
          <span class="font-semibold text-gray-900">À payer maintenant</span>
          <span class="text-base font-bold text-gray-900">{{ formatFcfa(commission) }}</span>
        </div>
        <p class="mt-1 text-[10px] text-gray-400">
          Le produit et la rémunération sont gérés directement par vous. WeAct ne facture que sa commission.
        </p>
      </div>
    </div>
  </div>
</template>
