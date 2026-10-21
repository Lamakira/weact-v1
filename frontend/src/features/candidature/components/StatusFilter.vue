<script setup lang="ts">
import { computed } from 'vue'
import type { CandidatureFilterStatus } from '../types'
import { CANDIDATURE_TO_PROCESS_FILTER, CandidatureStatus, CandidatureStatusLabel } from '../types'
import { useActionCountsStore } from '@/stores/actionCounts'

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

const actionCounts = useActionCountsStore()

/** Nombre d'éléments « À traiter » (store actionCounts), pour la seule pastille « À traiter ». */
function countFor(value: string): number {
  return value === CANDIDATURE_TO_PROCESS_FILTER ? actionCounts.counts.candidatures : 0
}

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
