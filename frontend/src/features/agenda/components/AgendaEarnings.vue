<script setup lang="ts">
import { computed } from 'vue'
import RPanel from '@/components/regie/RPanel.vue'
import { formatMoney } from '@/utils/money'
import type { AgendaEarnings as AgendaEarningsData } from '../types'

/** Carte « Gains à venir » : montant net en séquestre, partagée entre la colonne ordinateur et le téléphone. */
const props = defineProps<{ earnings: AgendaEarningsData | null }>()

const amount = computed(() => props.earnings?.amount ?? 0)
const count = computed(() => props.earnings?.count ?? 0)
</script>

<template>
  <RPanel title="Gains à venir" heading-tag="h3">
    <div class="p-4" data-testid="sidebar-earnings">
      <p v-if="amount === 0" class="text-[13.5px] text-ink-2" data-testid="earnings-empty">Aucun gain en attente</p>
      <template v-else>
        <p class="text-[20px] font-semibold text-ink" data-testid="earnings-amount">{{ formatMoney(amount) }}</p>
        <p class="text-[13px] text-ink-2" data-testid="earnings-count">
          {{ count }} engagement{{ count > 1 ? 's' : '' }} en séquestre
        </p>
        <p class="mt-1 text-[12px] text-ink-3">Versés sur votre portefeuille après le tournage.</p>
      </template>
    </div>
  </RPanel>
</template>
