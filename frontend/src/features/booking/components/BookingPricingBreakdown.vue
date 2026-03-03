<script setup lang="ts">
import { computed } from 'vue'
import { calculatePricingPreview } from '@/features/booking/types'

const props = defineProps<{
  tarifBase: number
  role: 'face' | 'producer'
  /** Actual server-stored amount Face receives. When provided, overrides client-side calculation. */
  montantFaceRecoit?: number
  /** Actual server-stored total Producer pays. When provided, overrides client-side calculation. */
  montantTotalProducteur?: number
}>()

const preview = computed(() => calculatePricingPreview(props.tarifBase))

const faceCommission = computed(() =>
  props.montantFaceRecoit != null
    ? props.tarifBase - props.montantFaceRecoit
    : preview.value.faceCommission,
)

const faceReceives = computed(() => props.montantFaceRecoit ?? preview.value.faceReceives)

const producerCommission = computed(() =>
  props.montantTotalProducteur != null
    ? props.montantTotalProducteur - props.tarifBase
    : preview.value.producerCommission,
)

const totalProducerPays = computed(() => props.montantTotalProducteur ?? preview.value.totalProducerPays)

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  }).format(amount)
}
</script>

<template>
  <div class="space-y-2">
    <div class="flex justify-between items-center text-sm">
      <span class="text-gray-500">Tarif de base</span>
      <span class="font-medium text-gray-900">{{ formatCurrency(tarifBase) }}</span>
    </div>

    <!-- Face view: commission deducted -->
    <template v-if="role === 'face'">
      <div class="flex justify-between items-center text-sm">
        <span class="text-gray-500">Commission plateforme (-15%)</span>
        <span class="text-red-500">-{{ formatCurrency(faceCommission) }}</span>
      </div>
      <div class="border-t border-gray-100 pt-2 mt-2">
        <div class="flex justify-between items-center">
          <span class="text-sm font-semibold text-gray-700">Vous recevrez</span>
          <span class="text-lg font-bold text-emerald-600">{{ formatCurrency(faceReceives) }}</span>
        </div>
      </div>
    </template>

    <!-- Producer view: commission added -->
    <template v-else>
      <div class="flex justify-between items-center text-sm">
        <span class="text-gray-500">Commission plateforme (+15%)</span>
        <span class="text-gray-500">+{{ formatCurrency(producerCommission) }}</span>
      </div>
      <div class="border-t border-gray-100 pt-2 mt-2">
        <div class="flex justify-between items-center">
          <span class="text-sm font-semibold text-gray-700">Total à payer</span>
          <span class="text-lg font-bold text-blue-600">{{ formatCurrency(totalProducerPays) }}</span>
        </div>
      </div>
    </template>
  </div>
</template>
