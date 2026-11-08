<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'

/**
 * L'unique action possible sur un jour (bloquer ou libérer), partagée par le panneau du bas
 * (téléphone) et le panneau de droite (ordinateur). Un jour passé n'offre aucune action.
 */
withDefaults(
  defineProps<{
    day: string
    blocked: boolean
    canAct: boolean
    hasEngagement: boolean
    busy?: boolean
    /** Préfixe des data-testid : `${prefix}-block` / `-unblock`. */
    testidPrefix?: string
  }>(),
  { busy: false, testidPrefix: 'agenda-day' },
)

const emit = defineEmits<{
  block: [day: string]
  unblock: [day: string]
}>()
</script>

<template>
  <template v-if="canAct">
    <button
      v-if="blocked"
      type="button"
      class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
      :disabled="busy"
      :data-testid="`${testidPrefix}-unblock`"
      @click="emit('unblock', day)"
    >
      <Loader2 v-if="busy" class="size-4 animate-spin" aria-hidden="true" />
      Rendre disponible
    </button>
    <button
      v-else-if="!hasEngagement"
      type="button"
      class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-weact px-4 text-sm font-semibold text-white hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-60"
      :disabled="busy"
      :data-testid="`${testidPrefix}-block`"
      @click="emit('block', day)"
    >
      <Loader2 v-if="busy" class="size-4 animate-spin" aria-hidden="true" />
      Marquer indisponible
    </button>
    <p v-else class="text-[12.5px] text-gray-500">
      Un jour engagé ne peut pas être bloqué : passez par l'annulation ou le changement de date.
    </p>
  </template>
</template>
