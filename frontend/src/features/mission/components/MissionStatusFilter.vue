<script setup lang="ts">
import { computed } from 'vue'
import type { MissionStatusType } from '../types'
import { MissionStatus, MissionStatusLabel } from '../types'

/**
 * Props
 */
const props = defineProps<{
  modelValue: MissionStatusType | ''
}>()

/**
 * Emits
 */
const emit = defineEmits<{
  (e: 'update:modelValue', value: MissionStatusType | ''): void
}>()

/**
 * Filter options - exclude draft since producers typically manage published+ missions
 */
const filterOptions = computed(() => [
  { value: '' as const, label: 'Tous' },
  { value: MissionStatus.PUBLISHED, label: MissionStatusLabel[MissionStatus.PUBLISHED] },
  { value: MissionStatus.CLOSED, label: MissionStatusLabel[MissionStatus.CLOSED] },
  { value: MissionStatus.COMPLETED, label: MissionStatusLabel[MissionStatus.COMPLETED] },
])

/**
 * Handle filter selection
 */
function selectFilter(value: MissionStatusType | ''): void {
  emit('update:modelValue', value)
}

/**
 * Check if option is active
 */
function isActive(value: MissionStatusType | ''): boolean {
  return props.modelValue === value
}
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <button
      v-for="option in filterOptions"
      :key="option.value"
      type="button"
      class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
      :class="[
        isActive(option.value)
          ? 'bg-primary text-white'
          : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
      ]"
      @click="selectFilter(option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>

<style scoped>
.bg-primary {
  background-color: #198496;
}
</style>
