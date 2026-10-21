<script setup lang="ts">
import { computed } from 'vue'
import type { MissionFilterStatus } from '../types'
import { MISSION_TO_PROCESS_FILTER, MissionStatus, MissionStatusLabel } from '../types'
import { useActionCountsStore } from '@/stores/actionCounts'

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

const actionCounts = useActionCountsStore()

/** Nombre d'éléments « À traiter » (store actionCounts), pour la seule pastille « À traiter ». */
function countFor(value: string): number {
  return value === MISSION_TO_PROCESS_FILTER ? actionCounts.counts.missions : 0
}

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
      <span
        v-if="countFor(option.value) > 0"
        class="ml-1.5 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full px-1 text-[11px] font-semibold"
        :class="isActive(option.value) ? 'bg-white/25 text-white' : 'bg-primary text-white'"
        data-testid="to-process-count"
      >
        {{ countFor(option.value) }}
      </span>
    </button>
  </div>
</template>

<style scoped>
.bg-primary {
  background-color: #198496;
}
</style>
