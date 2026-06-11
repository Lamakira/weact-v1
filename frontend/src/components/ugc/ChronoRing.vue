<script setup lang="ts">
import { computed } from 'vue'

// Anneau de compte à rebours (UX-DR1) — transcription fidèle de shared.jsx:123-151.
// progress 0..1 (0 = chrono non démarré, 1 = deadline atteinte). Présentationnel
// pur : le progress vient de l'appelant (constante 0 ici ; useChrono en 3.4).
const props = withDefaults(
  defineProps<{
    progress?: number
    size?: number
    stroke?: number
    label?: string
    sublabel?: string
    danger?: boolean
    ariaLabel?: string
  }>(),
  // danger: undefined est OBLIGATOIRE (sinon Vue caste la prop boolean absente à false)
  { progress: 0, size: 96, stroke: 8, label: undefined, sublabel: undefined, danger: undefined, ariaLabel: undefined },
)

// Number.isFinite : NaN traverserait Math.min/max et casserait le dash silencieusement
const clamped = computed(() => (Number.isFinite(props.progress) ? Math.min(1, Math.max(0, props.progress)) : 0))
const radius = computed(() => Math.max(0, (props.size - props.stroke) / 2))
const circumference = computed(() => 2 * Math.PI * radius.value)
const dashOffset = computed(() => circumference.value * (1 - clamped.value))

// Escalade teal → ambre → orange → rouge (tokens chrono UX-DR21).
// `danger` ne surcharge QUE le seuil rouge (shared.jsx:129).
const ringColor = computed(() => {
  if (props.danger !== undefined ? props.danger : clamped.value >= 0.85) return '#DC2626'
  if (clamped.value >= 0.6) return '#EA580C'
  if (clamped.value >= 0.4) return '#F59E0B'
  return '#198496'
})
</script>

<template>
  <div
    class="relative inline-flex items-center justify-center"
    :style="{ width: `${size}px`, height: `${size}px` }"
    :role="ariaLabel ? 'img' : undefined"
    :aria-label="ariaLabel"
    :aria-hidden="ariaLabel ? undefined : 'true'"
    data-testid="chrono-ring"
  >
    <svg class="chrono-ring-svg" :width="size" :height="size">
      <circle :cx="size / 2" :cy="size / 2" :r="radius" stroke="#E5E7EB" :stroke-width="stroke" fill="none" />
      <circle
        class="chrono-ring-progress"
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        :stroke="ringColor"
        :stroke-width="stroke"
        fill="none"
        stroke-linecap="round"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="dashOffset"
        data-testid="chrono-ring-arc"
      />
    </svg>
    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
      <div v-if="label" class="text-base font-bold leading-none text-[#0F1419]">{{ label }}</div>
      <div v-if="sublabel" class="mt-1 text-[10px] font-medium uppercase tracking-wider text-gray-500">{{ sublabel }}</div>
    </div>
  </div>
</template>

<style scoped>
/* L'arc démarre à midi, pas à 3 h (design : .chrono-svg, index.html:38) */
.chrono-ring-svg {
  transform: rotate(-90deg);
}
.chrono-ring-progress {
  transition:
    stroke-dashoffset 0.6s ease,
    stroke 0.4s ease;
}
@media (prefers-reduced-motion: reduce) {
  .chrono-ring-progress {
    transition: none;
  }
}
</style>
