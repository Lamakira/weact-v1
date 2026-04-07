<script setup lang="ts">
import { computed } from 'vue'
import { Check, Clock, Circle } from 'lucide-vue-next'
import { BookingStatus, type BookingStatusType } from '../types'

const props = defineProps<{
  status: BookingStatusType
  cancellationReason?: string | null
}>()

interface TimelineStep {
  label: string
  key: string
}

const baseSteps: TimelineStep[] = [
  { label: 'Demande envoyée', key: 'pending' },
  { label: 'Acceptation', key: 'accepted' },
  { label: 'Paiement', key: 'paid' },
  { label: 'Confirmation Face', key: 'confirmed_by_face' },
  { label: 'Confirmation Producteur', key: 'confirmed_by_producer' },
  { label: 'Terminé', key: 'completed' },
]

const steps = computed<TimelineStep[]>(() => {
  if (props.status === BookingStatus.NO_SHOW) {
    return [
      baseSteps[0],
      baseSteps[1],
      baseSteps[2],
      { label: 'Absence signalée', key: 'no_show' },
    ]
  }

  return baseSteps
})

// Map booking status to the step index it corresponds to
const statusToStepIndex: Record<string, number> = {
  [BookingStatus.PENDING]: 0,
  [BookingStatus.ACCEPTED]: 1,
  [BookingStatus.PAID]: 2,
  [BookingStatus.IN_PROGRESS]: 2,
  [BookingStatus.CONFIRMED_BY_FACE]: 3,
  [BookingStatus.CONFIRMED_BY_PRODUCER]: 4,
  [BookingStatus.COMPLETED]: 5,
  [BookingStatus.NO_SHOW]: 3,
}

const terminalNegativeStatuses: BookingStatusType[] = [
  BookingStatus.REFUSED,
  BookingStatus.EXPIRED,
  BookingStatus.CANCELLED_BY_FACE,
  BookingStatus.CANCELLED_BY_PRODUCER,
  BookingStatus.NO_SHOW,
]

const isTerminalNegative = computed(() => {
  return terminalNegativeStatuses.includes(props.status)
})

const currentStepIndex = computed(() => {
  return statusToStepIndex[props.status] ?? -1
})

function getStepState(index: number): 'completed' | 'current' | 'future' {
  if (isTerminalNegative.value) {
    const lastReachedIndex = props.status === BookingStatus.REFUSED ? 0 : currentStepIndex.value
    if (index <= lastReachedIndex) return 'completed'
    return 'future'
  }

  if (index < currentStepIndex.value) return 'completed'
  if (index === currentStepIndex.value) return 'current'
  return 'future'
}
</script>

<template>
  <div class="space-y-0">
    <div v-for="(step, index) in steps" :key="step.key" class="relative flex items-start gap-3">
      <!-- Vertical line connector -->
      <div v-if="index < steps.length - 1" class="absolute left-3.5 top-7 w-0.5 h-full -ml-px"
        :class="{
          'bg-emerald-400': getStepState(index) === 'completed',
          'bg-gray-200': getStepState(index) !== 'completed',
        }"
      />

      <!-- Step icon -->
      <div
        class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
        :class="{
          'bg-emerald-500 text-white': getStepState(index) === 'completed',
          'bg-weact text-white ring-4 ring-weact/20': getStepState(index) === 'current' && !isTerminalNegative,
          'bg-red-500 text-white ring-4 ring-red-100': getStepState(index) === 'current' && isTerminalNegative,
          'bg-gray-100 text-gray-400': getStepState(index) === 'future',
        }"
      >
        <Check v-if="getStepState(index) === 'completed'" class="h-4 w-4" />
        <Clock v-else-if="getStepState(index) === 'current'" class="h-4 w-4" />
        <Circle v-else class="h-3 w-3" />
      </div>

      <!-- Step label -->
      <div class="min-h-[2.5rem] flex items-center pb-2">
        <span
          class="text-sm font-medium"
          :class="{
            'text-emerald-700': getStepState(index) === 'completed',
            'text-gray-900': getStepState(index) === 'current',
            'text-gray-400': getStepState(index) === 'future',
          }"
        >
          {{ step.label }}
        </span>
      </div>
    </div>

    <!-- Terminal negative state indicator -->
    <div v-if="isTerminalNegative" class="flex items-center gap-3 mt-2 pt-2 border-t border-red-100">
      <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-500 text-white">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </div>
      <span class="text-sm font-medium text-red-700">
        {{ status === BookingStatus.REFUSED ? 'Refusé' : status === BookingStatus.EXPIRED ? 'Expiré' : status === BookingStatus.NO_SHOW ? 'Absence signalée' : 'Annulé' }}
      </span>
    </div>
  </div>
</template>
