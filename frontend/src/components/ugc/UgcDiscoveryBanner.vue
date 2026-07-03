<script setup lang="ts">
// RouterLink est résolu globalement (plugin router) — pas d'import runtime ici :
// les specs des pages hôtes mockent 'vue-router' et stubbent RouterLink par nom.
import type { RouteLocationRaw } from 'vue-router'
import { Video, ChevronRight } from 'lucide-vue-next'

// Bandeau « Découvrez les missions UGC » — porte d'entrée du tunnel de conversion UGC.
// Partagé entre la page publique des missions et la page missions côté Face.
// Mode compact sous `sm:` (icône réduite, badge « Voir ») : sur mobile le layout
// complet écrase la colonne texte, d'où le basculement à 640px et non à 376px.
defineProps<{
  to: RouteLocationRaw
  testId: string
}>()
</script>

<template>
  <RouterLink
    :to="to"
    :data-testid="testId"
    class="group flex items-center gap-3 rounded-xl border border-weact-100 bg-weact-50 p-4 transition-[border-color,box-shadow] hover:border-weact-200 hover:shadow-sm sm:gap-4 sm:p-5"
  >
    <div
      class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-weact text-white shadow-sm sm:h-12 sm:w-12"
    >
      <Video class="h-5 w-5 sm:h-6 sm:w-6" aria-hidden="true" />
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-sm font-bold text-slate-800 sm:text-base">
        Découvrez les missions UGC
      </p>
      <p class="mt-0.5 text-xs text-slate-600 sm:text-sm">
        Tournez des vidéos, gagnez des produits + du cash
      </p>
    </div>
    <span
      class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-weact px-3 py-2 text-xs font-semibold text-white transition-opacity group-hover:opacity-90 sm:text-sm"
    >
      <span class="hidden sm:inline">Voir les missions UGC</span>
      <span class="sm:hidden">Voir</span>
      <ChevronRight class="h-4 w-4" aria-hidden="true" />
    </span>
  </RouterLink>
</template>
