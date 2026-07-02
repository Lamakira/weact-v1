<script setup lang="ts">
import { computed } from 'vue'
import { ChronoRing } from '@/components/ugc'

const props = defineProps<{
  nombreVideos: number | null
}>()

const extraVideos = computed(() => Math.max(0, (props.nombreVideos ?? 0) - 2))
</script>

<template>
  <div class="rounded-lg border border-primary/15 bg-primary/[0.03] p-3" data-testid="ugc-deliverables-preview">
    <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-primary">
      Livrables — chronos déclenchés à la réception du produit
    </p>
    <div class="flex flex-col gap-2 sm:flex-row">
      <div class="flex flex-1 items-center gap-2.5 rounded-md border border-gray-200 bg-white p-2.5">
        <ChronoRing :progress="0" :size="36" :stroke="4" label="7" aria-label="Chrono Unboxing : 7 jours, non démarré" />
        <p class="text-xs leading-tight text-gray-700">
          <span class="font-semibold">1. Vidéo Unboxing</span><br />
          <span class="text-gray-500">à livrer sous 7 jours</span>
        </p>
      </div>
      <div class="flex flex-1 items-center gap-2.5 rounded-md border border-gray-200 bg-white p-2.5">
        <ChronoRing :progress="0" :size="36" :stroke="4" label="14" aria-label="Chrono Avis : 14 jours, non démarré" />
        <p class="text-xs leading-tight text-gray-700">
          <span class="font-semibold">2. Vidéo Avis</span><br />
          <span class="text-gray-500">à livrer sous 14 jours</span>
        </p>
      </div>
    </div>
    <p v-if="extraVideos > 0" class="mt-2 text-xs text-gray-600" data-testid="ugc-extra-videos">
      + {{ extraVideos }} vidéo{{ extraVideos > 1 ? 's' : '' }} supplémentaire{{ extraVideos > 1 ? 's' : '' }} — détails dans le brief du producteur
    </p>
  </div>
</template>
