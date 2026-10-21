<script setup lang="ts">
import type { BookingFilterStatus } from '../types'
import { BookingFilterLabel } from '../types'
import { useActionCountsStore } from '@/stores/actionCounts'

const props = defineProps<{
  modelValue: BookingFilterStatus
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: BookingFilterStatus): void
}>()

const filterOptions: { value: BookingFilterStatus; label: string }[] = [
  { value: '', label: BookingFilterLabel[''] },
  { value: 'to_process', label: BookingFilterLabel['to_process'] },
  { value: 'pending', label: BookingFilterLabel['pending'] },
  { value: 'active', label: BookingFilterLabel['active'] },
  { value: 'completed', label: BookingFilterLabel['completed'] },
  { value: 'cancelled', label: BookingFilterLabel['cancelled'] },
]

const actionCounts = useActionCountsStore()

/** Nombre d'éléments « À traiter » (store actionCounts), pour la seule pastille « À traiter ». */
function countFor(value: string): number {
  return value === 'to_process' ? actionCounts.counts.bookings : 0
}

function selectFilter(value: BookingFilterStatus): void {
  emit('update:modelValue', value)
}

function isActive(value: BookingFilterStatus): boolean {
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
