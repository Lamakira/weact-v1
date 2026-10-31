<script setup lang="ts">
import { formatMoney } from '@/utils/money'
import { Gift } from 'lucide-vue-next'

/**
 * Récap d'un booking UGC vu par la Face : ce qu'elle reçoit, jamais la commission
 * WeAct ni les frais de service (ils concernent le Producteur uniquement).
 */
defineProps<{
  nomProduit?: string | null
  valeurProduit: number
  /** `'hybrid'` = produit + cachet net ; tout autre valeur = produit seul. */
  typeCompensation?: string | null
  montantFaceRecoit?: number | null
}>()
</script>

<template>
  <div class="rounded-lg border border-gray-200 bg-white p-4" data-testid="ugc-face-reward-summary">
    <div class="mb-3 flex items-center gap-2">
      <Gift :size="14" class="text-weact" />
      <div class="text-xs font-semibold uppercase tracking-wider text-gray-900">Ce que vous recevez</div>
    </div>

    <div class="space-y-2">
      <div v-if="nomProduit" class="flex items-center justify-between gap-3 text-xs">
        <span class="text-gray-500">Produit offert</span>
        <span class="text-right text-gray-700">{{ nomProduit }}</span>
      </div>

      <div class="flex items-center justify-between text-xs">
        <span class="text-gray-500">Valeur déclarée</span>
        <span class="text-gray-700">{{ formatMoney(valeurProduit) }}</span>
      </div>

      <div v-if="typeCompensation === 'hybrid'" class="flex items-start justify-between gap-3 text-xs">
        <span>
          <span class="block font-semibold text-gray-900">Rémunération nette</span>
          <span class="block text-[10px] text-gray-400">versée après validation de vos vidéos</span>
        </span>
        <span class="text-base font-bold text-weact">{{ formatMoney(montantFaceRecoit ?? 0) }}</span>
      </div>

      <p class="mt-2 border-t border-gray-100 pt-2 text-[10px] text-gray-400">Vous n'avez rien à payer.</p>
    </div>
  </div>
</template>
