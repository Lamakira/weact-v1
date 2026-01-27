<script setup lang="ts">
import { computed } from 'vue'
import type { CandidatureStatusType } from '../types'
import { CandidatureStatus, CandidatureStatusLabel } from '../types'

/**
 * Props
 */
const props = defineProps<{
  modelValue: CandidatureStatusType | ''
}>()

/**
 * Emits
 */
const emit = defineEmits<{
  (e: 'update:modelValue', value: CandidatureStatusType | ''): void
}>()

/**
 * Filter options
 */
const filterOptions = computed(() => [
  { value: '' as const, label: 'Tous' },
  { value: CandidatureStatus.PENDING, label: CandidatureStatusLabel[CandidatureStatus.PENDING] },
  { value: CandidatureStatus.ACCEPTED, label: CandidatureStatusLabel[CandidatureStatus.ACCEPTED] },
  { value: CandidatureStatus.CONFIRMED, label: CandidatureStatusLabel[CandidatureStatus.CONFIRMED] },
  { value: CandidatureStatus.IN_PROGRESS, label: CandidatureStatusLabel[CandidatureStatus.IN_PROGRESS] },
  { value: CandidatureStatus.COMPLETED, label: CandidatureStatusLabel[CandidatureStatus.COMPLETED] },
  { value: CandidatureStatus.REJECTED, label: CandidatureStatusLabel[CandidatureStatus.REJECTED] },
])

/**
 * Handle filter selection
 */
function selectFilter(value: CandidatureStatusType | ''): void {
  emit('update:modelValue', value)
}

/**
 * Check if option is active
 */
function isActive(value: CandidatureStatusType | ''): boolean {
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
