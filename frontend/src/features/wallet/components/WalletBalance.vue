<script setup lang="ts">
import { computed } from 'vue'
import { Wallet, Info, AlertCircle } from 'lucide-vue-next'

interface Props {
  balance: number
  pendingEscrow: number
}

const props = defineProps<Props>()

const formatCurrency = (value: number) =>
  new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)

const hasNoFunds = computed(() => props.balance === 0 && props.pendingEscrow === 0)
</script>

<template>
  <div class="bg-white border border-gray-200 rounded-md p-6 mb-8 transition-colors">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
      <div class="space-y-1">
        <div class="flex items-center gap-2 text-gray-500 text-xs font-medium uppercase tracking-wider">
          <Wallet class="w-3.5 h-3.5" />
          <span>Solde disponible</span>
        </div>
        <div v-if="hasNoFunds" class="flex items-center gap-2 mt-2">
          <span class="text-3xl font-bold text-gray-900">0 XOF</span>
          <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
            Vide
          </span>
        </div>
        <h2 v-else class="text-3xl font-bold text-gray-900 leading-tight">
          {{ formatCurrency(props.balance) }}
        </h2>
      </div>

      <div class="flex flex-col md:items-end space-y-1.5">
        <div class="group relative flex items-center gap-1.5 text-sm text-gray-500">
          <span>Fonds en attente</span>
          <div class="cursor-help text-gray-400 hover:text-[#198496] transition-colors">
            <Info class="w-4 h-4" />
            <div
              class="absolute bottom-full right-0 mb-2 hidden group-hover:block w-48 p-2 bg-gray-900 text-white text-[10px] leading-relaxed rounded shadow-lg z-10"
            >
              Ces fonds sont bloqués en séquestre jusqu'à la validation finale de vos prestations terminées.
            </div>
          </div>
        </div>
        <p :class="['font-medium', props.pendingEscrow > 0 ? 'text-gray-700' : 'text-gray-400']">
          {{ formatCurrency(props.pendingEscrow) }}
        </p>
      </div>
    </div>

    <div v-if="hasNoFunds" class="mt-6 pt-6 border-t border-gray-100 flex items-start gap-3">
      <div class="mt-0.5 p-1.5 bg-gray-50 rounded-full text-gray-400">
        <AlertCircle class="w-4 h-4" />
      </div>
      <div>
        <p class="text-sm text-gray-700 font-medium">Votre portefeuille est vide</p>
        <p class="text-sm text-gray-500 mt-0.5">
          Vos revenus apparaîtront ici après votre premier booking.
        </p>
      </div>
    </div>
  </div>
</template>
