<script setup lang="ts">
import { computed } from 'vue'
import { BookingStatusLabel, type BookingStatusType } from '../types'

const props = defineProps<{
  status: BookingStatusType
}>()

const statusColorClasses: Record<BookingStatusType, string> = {
  pending: 'bg-amber-100 text-amber-800',
  accepted: 'bg-sky-100 text-sky-800',
  refused: 'bg-red-100 text-red-800',
  paid: 'bg-blue-100 text-blue-800',
  in_progress: 'bg-teal-100 text-teal-800',
  confirmed_by_face: 'bg-emerald-100 text-emerald-800',
  confirmed_by_producer: 'bg-emerald-100 text-emerald-800',
  completed: 'bg-emerald-100 text-emerald-800',
  cancelled_by_producer: 'bg-red-100 text-red-800',
  cancelled_by_face: 'bg-red-100 text-red-800',
}

const label = computed(() => BookingStatusLabel[props.status] || props.status)
const colorClass = computed(() => statusColorClasses[props.status] || 'bg-gray-100 text-gray-800')
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="colorClass"
  >
    {{ label }}
  </span>
</template>
