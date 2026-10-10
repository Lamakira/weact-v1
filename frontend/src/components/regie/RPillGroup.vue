<script setup lang="ts">
/**
 * RPillGroup — groupe de pilules de filtre à sélection unique (v-model).
 * Chaque pilule est un bouton `aria-pressed` ; le groupe porte `role="group"`.
 */
import RPill from './RPill.vue'

export interface RPillOption {
  value: string
  label: string
  count?: number
}

interface Props {
  options: RPillOption[]
  groupLabel: string
}

defineProps<Props>()

const model = defineModel<string>({ required: true })
</script>

<template>
  <div
    role="group"
    :aria-label="groupLabel"
    class="flex flex-wrap items-center gap-2"
    data-testid="r-pill-group"
  >
    <RPill
      v-for="option in options"
      :key="option.value"
      :pressed="model === option.value"
      :count="option.count"
      @click="model = option.value"
    >
      {{ option.label }}
    </RPill>
  </div>
</template>
