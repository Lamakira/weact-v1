<script setup lang="ts">
import { formatMoney } from '@/utils/money'
import { computed } from 'vue'
import { calculatePricingPreview } from '@/features/booking/types'

const props = defineProps<{
  tarifBase: number
  role: 'face' | 'producer'
  /** Actual server-stored amount Face receives. When provided, overrides client-side calculation. */
  montantFaceRecoit?: number
  /** Actual server-stored total Producer pays. When provided, overrides client-side calculation. */
  montantTotalProducteur?: number
  /** When true, shows a "Revenu plateforme" row with both commissions combined. Default: false. */
  showPlatformRevenue?: boolean
  /** Libellé du total ; repli sur « Vous recevrez » (Face) ou « Total à payer » (Producteur). */
  totalLabel?: string
  /** Montant jamais réglé (booking clos) : total en gris au lieu du bleu ou du vert. */
  muted?: boolean
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

const platformRevenue = computed(() => producerCommission.value + faceCommission.value)

</script>

<template>
  <div class="space-y-2">
    <div class="flex justify-between items-center text-sm">
      <span class="text-gray-500">Tarif de base</span>
      <span class="font-medium text-gray-900">{{ formatMoney(tarifBase) }}</span>
    </div>

    <!-- Face view: commission deducted -->
    <template v-if="role === 'face'">
      <div class="flex justify-between items-center text-sm">
        <span class="text-gray-500">Frais de service</span>
        <span class="text-red-500">-{{ formatMoney(faceCommission) }}</span>
      </div>
      <div class="border-t border-gray-100 pt-2 mt-2">
        <div class="flex justify-between items-center">
          <span class="text-sm font-semibold text-gray-700" data-testid="booking-amount-total-label">{{ totalLabel ?? 'Vous recevrez' }}</span>
          <span
            class="text-lg font-bold"
            :class="muted ? 'text-gray-700' : 'text-emerald-600'"
            data-testid="booking-amount-total"
          >{{ formatMoney(faceReceives) }}</span>
        </div>
      </div>
    </template>

    <!-- Producer view: commission added -->
    <template v-else>
      <div class="flex justify-between items-center text-sm">
        <span class="text-gray-500">Frais de service</span>
        <span class="text-gray-500">+{{ formatMoney(producerCommission) }}</span>
      </div>
      <div class="border-t border-gray-100 pt-2 mt-2">
        <div class="flex justify-between items-center">
          <span class="text-sm font-semibold text-gray-700" data-testid="booking-amount-total-label">{{ totalLabel ?? 'Total à payer' }}</span>
          <span
            class="text-lg font-bold"
            :class="muted ? 'text-gray-700' : 'text-blue-600'"
            data-testid="booking-amount-total"
          >{{ formatMoney(totalProducerPays) }}</span>
        </div>
      </div>
    </template>

    <!-- Platform revenue row (optional) -->
    <div v-if="showPlatformRevenue" class="flex justify-between items-center text-sm border-t border-gray-100 pt-2 mt-2">
      <span class="text-gray-500">Revenu plateforme</span>
      <span class="font-medium text-gray-700">{{ formatMoney(platformRevenue) }}</span>
    </div>
  </div>
</template>
