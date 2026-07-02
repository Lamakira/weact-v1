<script setup lang="ts">
import { useNavigationProgress } from '@/composables/useNavigationProgress'

/**
 * Barre de chargement fine en haut de page (façon YouTube / NProgress).
 * Se déclenche à chaque changement de route via les hooks du router
 * (cf. `router/index.ts`). Montée une seule fois dans `App.vue`.
 */
const { percent, visible } = useNavigationProgress()
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-0 top-0 z-[9999] h-[3px] transition-opacity duration-300"
    :class="visible ? 'opacity-100' : 'opacity-0'"
    role="progressbar"
    aria-label="Chargement de la page"
    :aria-hidden="!visible"
    :aria-valuenow="Math.round(percent)"
    aria-valuemin="0"
    aria-valuemax="100"
  >
    <div
      class="h-full bg-weact shadow-[0_0_8px_rgba(25,132,150,0.7)] transition-[width] duration-200 ease-out"
      :style="{ width: `${percent}%` }"
    />
  </div>
</template>
