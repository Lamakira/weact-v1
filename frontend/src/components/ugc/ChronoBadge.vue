<script setup lang="ts">
import { computed } from 'vue'
import { Timer } from 'lucide-vue-next'
import { useChrono } from '@/composables/useChrono'

// Badge chrono linéaire (UX-DR2) : icône timer + label, escalade couleur
// identique à ChronoRing. Self-contained (timestamps → useChrono) pour un
// usage en liste live (D-4.4.e).
const props = withDefaults(
  defineProps<{
    startAt?: string | null
    deadlineAt?: string | null
    size?: 'sm' | 'lg'
  }>(),
  { startAt: null, deadlineAt: null, size: 'sm' },
)

const { progress, remainingLabel } = useChrono(
  () => props.startAt,
  () => props.deadlineAt,
)

const clamped = computed(() =>
  Number.isFinite(progress.value) ? Math.min(1, Math.max(0, progress.value)) : 0,
)

// Escalade teal → ambre → orange → rouge (calque ChronoRing.vue:29-34).
const color = computed(() => {
  if (clamped.value >= 0.85) return '#DC2626'
  if (clamped.value >= 0.6) return '#EA580C'
  if (clamped.value >= 0.4) return '#F59E0B'
  return '#198496'
})
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-full font-medium"
    :class="size === 'lg' ? 'px-2.5 py-1 text-xs' : 'px-2 py-0.5 text-[11px]'"
    :style="{ color, backgroundColor: `${color}1A` }"
    :aria-label="`SLA : ${remainingLabel} restant`"
    data-testid="chrono-badge"
  >
    <Timer :class="size === 'lg' ? 'h-3.5 w-3.5' : 'h-3 w-3'" />
    {{ remainingLabel }}
  </span>
</template>
