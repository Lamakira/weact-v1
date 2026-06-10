<script setup lang="ts">
import { computed } from 'vue'
import type { StatusPillKind } from './ugc'

const props = withDefaults(defineProps<{ kind?: StatusPillKind }>(), {
  kind: 'pending',
})

// Map couleur/fond reprise strictement de shared.jsx:170-190 (design handoff)
const STYLES: Record<StatusPillKind, { color: string; background: string }> = {
  pending: { color: '#0F1419', background: '#F3F4F6' },
  paid: { color: '#198496', background: 'rgba(25,132,150,0.10)' },
  accepted: { color: '#198496', background: 'rgba(25,132,150,0.10)' },
  shipped: { color: '#1D4ED8', background: 'rgba(29,78,216,0.08)' },
  received: { color: '#7C3AED', background: 'rgba(124,58,237,0.08)' },
  delivered: { color: '#059669', background: 'rgba(5,150,105,0.10)' },
  completed: { color: '#059669', background: 'rgba(5,150,105,0.10)' },
  overdue: { color: '#DC2626', background: 'rgba(220,38,38,0.10)' },
  suspended: { color: '#DC2626', background: 'rgba(220,38,38,0.10)' },
}

const pillStyle = computed(() => ({
  color: STYLES[props.kind].color,
  backgroundColor: STYLES[props.kind].background,
}))
const dotStyle = computed(() => ({ backgroundColor: STYLES[props.kind].color }))
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium"
    :style="pillStyle"
    data-testid="status-pill"
  >
    <span
      class="h-1.5 w-1.5 rounded-full"
      :style="dotStyle"
      aria-hidden="true"
      data-testid="status-pill-dot"
    />
    <slot />
  </span>
</template>
