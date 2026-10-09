<script setup lang="ts">
/**
 * RMiniBars — mini-barres inline en SVG pur. La dernière valeur (mois en cours)
 * est en teal, l'historique en gris. Aucune lib de graphiques.
 */
import { computed } from 'vue'

interface Props {
  values: number[]
  /** Libellé accessible (ex. « Candidatures par mois ») */
  label: string
  height?: number
}

const props = withDefaults(defineProps<Props>(), {
  height: 36,
})

const BAR_WIDTH = 16
const GAP = 6
const MIN_HEIGHT = 2

const width = computed(() => Math.max(props.values.length, 1) * (BAR_WIDTH + GAP) - GAP)

const bars = computed(() => {
  const max = Math.max(...props.values, 1)
  return props.values.map((value, index) => {
    const h = Math.max(MIN_HEIGHT, Math.round((Math.max(value, 0) / max) * props.height))
    return {
      x: index * (BAR_WIDTH + GAP),
      y: props.height - h,
      height: h,
      current: index === props.values.length - 1,
    }
  })
})
</script>

<template>
  <svg
    :width="width"
    :height="height"
    :viewBox="`0 0 ${width} ${height}`"
    role="img"
    :aria-label="label"
    data-testid="r-mini-bars"
  >
    <rect
      v-for="(bar, index) in bars"
      :key="index"
      :x="bar.x"
      :y="bar.y"
      :width="BAR_WIDTH"
      :height="bar.height"
      rx="3"
      :class="bar.current ? 'fill-weact-600' : 'fill-state-neutral'"
      :data-current="bar.current ? 'true' : undefined"
    />
  </svg>
</template>
