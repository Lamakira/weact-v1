<script setup lang="ts">
/**
 * RStatusDot — point d'état de 7 px + libellé. La couleur ne porte jamais seule
 * l'information : le libellé est toujours présent (ou en sr-only si `hideLabel`).
 */
import { computed } from 'vue'
import type { StatusTone } from './statusTone'

interface Props {
  tone?: StatusTone
  label?: string
  /** Libellé réservé aux lecteurs d'écran (point seul visuellement) */
  hideLabel?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  tone: 'neutral',
  label: undefined,
  hideLabel: false,
})

const toneClass: Record<StatusTone, string> = {
  pending: 'bg-state-pending',
  progress: 'bg-state-progress',
  success: 'bg-state-success',
  done: 'bg-state-done',
  danger: 'bg-state-danger',
  neutral: 'bg-state-neutral',
}

const dotClass = computed(() => toneClass[props.tone])
</script>

<template>
  <span class="inline-flex items-center gap-1.5" :data-tone="tone" data-testid="r-status-dot">
    <span
      class="inline-block size-[7px] shrink-0 rounded-full"
      :class="dotClass"
      aria-hidden="true"
      data-testid="r-status-dot-mark"
    />
    <span :class="hideLabel ? 'sr-only' : ''"><slot>{{ label }}</slot></span>
  </span>
</template>
