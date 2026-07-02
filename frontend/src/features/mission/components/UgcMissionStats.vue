<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  valeurProduit: number | null
  montantRemuneration: number | null
  nombreVideos: number | null
}>()

// Hybride ⇔ montant_remuneration non null (même heuristique que UgcMissionCard 2.2)
const hasCash = computed(() => props.montantRemuneration != null)

function formatFcfa(amount: number | null): string {
  return (amount ?? 0).toLocaleString('fr-FR')
}
</script>

<template>
  <div class="grid gap-2" :class="hasCash ? 'grid-cols-3' : 'grid-cols-2'" data-testid="ugc-mission-stats">
    <div class="rounded-md border border-primary/15 bg-primary/5 p-2 text-center">
      <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Produit</p>
      <p class="mt-1 text-xs font-bold text-gray-900">
        {{ formatFcfa(valeurProduit) }}<span class="text-[9px] font-medium text-gray-500"> FCFA</span>
      </p>
    </div>
    <div
      v-if="hasCash"
      class="rounded-md border border-primary/15 bg-primary/5 p-2 text-center"
      data-testid="ugc-stat-cash"
    >
      <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Cash</p>
      <p class="mt-1 text-xs font-bold text-gray-900">
        {{ formatFcfa(montantRemuneration) }}<span class="text-[9px] font-medium text-gray-500"> FCFA</span>
      </p>
    </div>
    <div class="rounded-md bg-primary p-2 text-center">
      <p class="text-[9px] font-bold uppercase tracking-widest text-white/70">Vidéos</p>
      <p class="mt-1 text-xs font-bold text-white">{{ nombreVideos ?? '—' }}</p>
    </div>
  </div>
</template>
