<script setup lang="ts">
import { computed } from 'vue'
import type { BookingFilterStatus } from '../types'
import { BookingFilterLabel } from '../types'
import { useActionCountsStore } from '@/stores/actionCounts'
import ListFilterBar, { type FilterOption } from '@/components/ListFilterBar.vue'

defineProps<{
  modelValue: BookingFilterStatus
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: BookingFilterStatus): void
}>()

const actionCounts = useActionCountsStore()

/** Pastilles visibles (lot F4) ; le nombre « À traiter » vient du store actionCounts. */
const primary = computed<FilterOption<BookingFilterStatus>[]>(() => [
  { value: '', label: BookingFilterLabel[''] },
  { value: 'to_process', label: BookingFilterLabel['to_process'], count: actionCounts.counts.bookings },
  { value: 'active', label: BookingFilterLabel['active'] },
  { value: 'completed', label: BookingFilterLabel['completed'] },
])

/** Statuts rares, rangés dans le menu « Plus ». */
const more: FilterOption<BookingFilterStatus>[] = [
  { value: 'pending', label: BookingFilterLabel['pending'] },
  { value: 'cancelled', label: BookingFilterLabel['cancelled'] },
]
</script>

<template>
  <ListFilterBar
    :model-value="modelValue"
    :primary="primary"
    :more="more"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
