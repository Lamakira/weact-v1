<script setup lang="ts">
import { computed } from 'vue'
import type { MissionFilterStatus } from '../types'
import { MISSION_TO_PROCESS_FILTER, MissionStatus, MissionStatusLabel } from '../types'

/**
 * Props
 */
const props = defineProps<{
  modelValue: MissionFilterStatus
}>()

/**
 * Emits
 */
const emit = defineEmits<{
  (e: 'update:modelValue', value: MissionFilterStatus): void
}>()

/**
 * Filter options - exclude draft since producers typically manage published+ missions
 */
const filterOptions = computed<{ value: MissionFilterStatus; label: string }[]>(() => [
  { value: '', label: 'Tous' },
  { value: MISSION_TO_PROCESS_FILTER, label: 'À traiter' },
  { value: MissionStatus.PUBLISHED, label: MissionStatusLabel[MissionStatus.PUBLISHED] },
  { value: MissionStatus.CLOSED, label: 'Recrutement clos' },
  { value: MissionStatus.COMPLETED, label: MissionStatusLabel[MissionStatus.COMPLETED] },
])

/**
 * Handle filter selection
 */
function selectFilter(value: MissionFilterStatus): void {
  emit('update:modelValue', value)
}

/**
 * Check if option is active
 */
function isActive(value: MissionFilterStatus): boolean {
  return props.modelValue === value
}
</script>

<template>
  <div class="flex flex-nowrap sm:flex-wrap gap-2 overflow-x-auto pb-2 sm:pb-0 -mx-4 px-4 sm:mx-0 sm:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
    <button
      v-for="option in filterOptions"
      :key="option.value"
      type="button"
      class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
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
