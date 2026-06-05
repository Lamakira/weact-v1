<script setup lang="ts">
import type { UgcCompensationType } from './ugc'

defineProps<{
  modelValue: UgcCompensationType
}>()

const emit = defineEmits<{
  'update:modelValue': [value: UgcCompensationType]
}>()

const options: Array<{ value: UgcCompensationType; label: string; sub: string }> = [
  { value: 'product', label: 'Produit seul', sub: '2 vidéos fixes' },
  { value: 'hybrid', label: 'Produit + argent', sub: 'Nb vidéos libre' },
]
</script>

<template>
  <div class="inline-flex w-full gap-1 rounded-lg bg-gray-100 p-1" role="group" aria-label="Type de compensation">
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      :aria-pressed="modelValue === option.value"
      :data-testid="`compensation-${option.value}`"
      class="flex flex-1 flex-col items-start gap-0.5 rounded-md px-4 py-2.5 text-left transition-all outline-none focus-visible:ring-2 focus-visible:ring-weact/40"
      :class="
        modelValue === option.value
          ? 'bg-white shadow-sm'
          : 'hover:bg-white/40'
      "
      @click="emit('update:modelValue', option.value)"
    >
      <span
        class="text-sm font-semibold"
        :class="modelValue === option.value ? 'text-gray-900' : 'text-gray-600'"
      >
        {{ option.label }}
      </span>
      <span class="text-[10px] font-medium uppercase tracking-wider text-gray-400">
        {{ option.sub }}
      </span>
    </button>
  </div>
</template>
