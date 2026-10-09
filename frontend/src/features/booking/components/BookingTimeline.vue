<script setup lang="ts">
import { computed } from 'vue'
import { Check, Clock, Circle, X } from 'lucide-vue-next'
import { BookingStatus, type BookingStatusType } from '../types'

// `cancellationReason` reste dans l'API du composant (passée par la page).
const props = defineProps<{
  status: BookingStatusType
  cancellationReason?: string | null
}>()

type StepState = 'completed' | 'current' | 'future' | 'failed'

interface TimelineStep {
  label: string
  key: string
  state: StepState
}

const REQUEST = { label: 'Demande envoyée', key: 'pending' }
const ACCEPTANCE = { label: 'Acceptation', key: 'accepted' }
const PAYMENT = { label: 'Paiement', key: 'paid' }
const FACE_CONFIRMATION = { label: 'Confirmation Face', key: 'confirmed_by_face' }
const PRODUCER_CONFIRMATION = { label: 'Confirmation Producteur', key: 'confirmed_by_producer' }
const DONE = { label: 'Terminé', key: 'completed' }

const cashFlow = [REQUEST, ACCEPTANCE, PAYMENT, FACE_CONFIRMATION, PRODUCER_CONFIRMATION, DONE]

/**
 * Cash flow (statuts positifs) : une étape dont le jalon est atteint est « completed » ;
 * « current » = la/les prochaine(s) action(s) encore attendue(s). Les deux confirmations
 * sont indépendantes.
 */
const positiveProgress: Record<string, { done: string[]; current: string[] }> = {
  [BookingStatus.PENDING]: { done: ['pending'], current: ['accepted'] },
  [BookingStatus.ACCEPTED]: { done: ['pending', 'accepted'], current: ['paid'] },
  [BookingStatus.PAID]: {
    done: ['pending', 'accepted', 'paid'],
    current: ['confirmed_by_face', 'confirmed_by_producer'],
  },
  // UGC commission réglée : analogue de `paid`.
  [BookingStatus.COMMISSION_PAID]: {
    done: ['pending', 'accepted', 'paid'],
    current: ['confirmed_by_face', 'confirmed_by_producer'],
  },
  [BookingStatus.IN_PROGRESS]: {
    done: ['pending', 'accepted', 'paid'],
    current: ['confirmed_by_face', 'confirmed_by_producer'],
  },
  [BookingStatus.CONFIRMED_BY_FACE]: {
    done: ['pending', 'accepted', 'paid', 'confirmed_by_face'],
    current: ['confirmed_by_producer'],
  },
  [BookingStatus.CONFIRMED_BY_PRODUCER]: {
    done: ['pending', 'accepted', 'paid', 'confirmed_by_producer'],
    current: ['confirmed_by_face'],
  },
  [BookingStatus.COMPLETED]: {
    done: ['pending', 'accepted', 'paid', 'confirmed_by_face', 'confirmed_by_producer', 'completed'],
    current: [],
  },
}

/** Statuts négatifs : jalons réellement atteints + étape finale rouge. */
const negativeProgress: Record<string, { done: TimelineStep['key'][]; label: string }> = {
  [BookingStatus.REFUSED]: { done: ['pending'], label: 'Refusée' },
  [BookingStatus.EXPIRED]: { done: ['pending'], label: 'Expirée' },
  [BookingStatus.CANCELLED_BY_FACE]: { done: ['pending'], label: 'Annulée par la Face' },
  [BookingStatus.CANCELLED_BY_PRODUCER]: { done: ['pending'], label: 'Annulée par le Producteur' },
  [BookingStatus.NO_SHOW]: { done: ['pending', 'accepted', 'paid'], label: 'Absence signalée' },
}

const steps = computed<TimelineStep[]>(() => {
  const negative = negativeProgress[props.status]
  if (negative) {
    const reached = cashFlow
      .filter((s) => negative.done.includes(s.key))
      .map((s): TimelineStep => ({ ...s, state: 'completed' }))
    return [...reached, { label: negative.label, key: props.status, state: 'failed' }]
  }

  const progress = positiveProgress[props.status]
  return cashFlow.map((s): TimelineStep => ({
    ...s,
    state: progress?.done.includes(s.key)
      ? 'completed'
      : progress?.current.includes(s.key)
        ? 'current'
        : 'future',
  }))
})
</script>

<template>
  <div class="space-y-0">
    <div
      v-for="(step, index) in steps"
      :key="step.key"
      class="relative flex items-start gap-3"
      data-testid="timeline-step"
      :data-state="step.state"
    >
      <!-- Vertical line connector -->
      <div v-if="index < steps.length - 1" class="absolute left-3.5 top-7 w-0.5 h-full -ml-px"
        :class="{
          'bg-emerald-400': step.state === 'completed',
          'bg-gray-200': step.state !== 'completed',
        }"
      />

      <!-- Step icon -->
      <div
        class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
        :class="{
          'bg-emerald-500 text-white': step.state === 'completed',
          'bg-weact text-white ring-4 ring-weact/20': step.state === 'current',
          'bg-red-500 text-white ring-4 ring-red-100': step.state === 'failed',
          'bg-gray-100 text-gray-400': step.state === 'future',
        }"
      >
        <Check v-if="step.state === 'completed'" class="h-4 w-4" />
        <Clock v-else-if="step.state === 'current'" class="h-4 w-4" />
        <X v-else-if="step.state === 'failed'" class="h-4 w-4" />
        <Circle v-else class="h-3 w-3" />
      </div>

      <!-- Step label -->
      <div class="min-h-[2.5rem] flex items-center pb-2">
        <span
          class="text-sm font-medium"
          :class="{
            'text-emerald-700': step.state === 'completed',
            'text-gray-900': step.state === 'current',
            'text-red-700': step.state === 'failed',
            'text-gray-400': step.state === 'future',
          }"
        >
          {{ step.label }}
        </span>
      </div>
    </div>
  </div>
</template>
