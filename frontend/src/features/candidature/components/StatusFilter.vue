<script setup lang="ts">
import { computed } from 'vue'
import type { CandidatureFilterStatus } from '../types'
import { CANDIDATURE_TO_PROCESS_FILTER, CandidatureStatus, CandidatureStatusLabel } from '../types'

/**
 * Props
 */
const props = defineProps<{
  modelValue: CandidatureFilterStatus
  /** Pastille « À traiter » (lot D7) : liste « Mes candidatures » de la Face uniquement. */
  withToProcess?: boolean
}>()

/**
 * Emits
 */
const emit = defineEmits<{
  (e: 'update:modelValue', value: CandidatureFilterStatus): void
}>()

/**
 * Filter options
 */
const filterOptions = computed<{ value: CandidatureFilterStatus; label: string }[]>(() => [
  { value: '', label: 'Tous' },
  ...(props.withToProcess ? [{ value: CANDIDATURE_TO_PROCESS_FILTER as CandidatureFilterStatus, label: 'À traiter' }] : []),
  { value: CandidatureStatus.PENDING, label: CandidatureStatusLabel[CandidatureStatus.PENDING] },
  { value: CandidatureStatus.ACCEPTED, label: CandidatureStatusLabel[CandidatureStatus.ACCEPTED] },
  { value: CandidatureStatus.CONFIRMED, label: CandidatureStatusLabel[CandidatureStatus.CONFIRMED] },
  { value: CandidatureStatus.IN_PROGRESS, label: CandidatureStatusLabel[CandidatureStatus.IN_PROGRESS] },
  { value: CandidatureStatus.COMPLETED, label: CandidatureStatusLabel[CandidatureStatus.COMPLETED] },
  { value: CandidatureStatus.REJECTED, label: CandidatureStatusLabel[CandidatureStatus.REJECTED] },
  { value: CandidatureStatus.CANCELLED, label: CandidatureStatusLabel[CandidatureStatus.CANCELLED] },
  { value: CandidatureStatus.EXPIRED, label: CandidatureStatusLabel[CandidatureStatus.EXPIRED] },
])

/**
 * Handle filter selection
 */
function selectFilter(value: CandidatureFilterStatus): void {
  emit('update:modelValue', value)
}

/**
 * Check if option is active
 */
function isActive(value: CandidatureFilterStatus): boolean {
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
