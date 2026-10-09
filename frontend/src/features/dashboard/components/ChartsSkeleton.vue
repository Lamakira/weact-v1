<script setup lang="ts">
/**
 * Placeholder affiché pendant le chargement du chunk des graphiques (chart.js).
 * Reprend les dimensions de ActivityChart / BookingActivityChart (titre + 2 cartes
 * min-h-[400px]) pour qu'aucun décalage de mise en page n'ait lieu à l'arrivée du chunk.
 */
import { Skeleton } from '@/components/ui/skeleton'
</script>

<template>
  <div data-testid="charts-skeleton" aria-hidden="true">
    <div class="h-7 mb-4" />
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full">
      <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm min-h-[400px] flex flex-col">
        <Skeleton class="h-full w-full flex-1 rounded-xl" />
      </div>
      <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm min-h-[400px] flex flex-col">
        <Skeleton class="h-full w-full flex-1 rounded-xl" />
      </div>
    </div>
  </div>
</template>
