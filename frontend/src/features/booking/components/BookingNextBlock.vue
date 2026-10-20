<script setup lang="ts">
import { computed } from 'vue'
import { Clock } from 'lucide-vue-next'
import BookingSwap from './BookingSwap.vue'
import { formatRemaining, type BookingNext } from '../utils/bookingPresentation'

const props = defineProps<{
  next: BookingNext
  /** Horloge de la page (ms) : fait avancer le décompte. */
  now: number
}>()

const remaining = computed(() => (props.next.deadlineAt ? formatRemaining(props.next.deadlineAt, props.now) : null))
</script>

<template>
  <section
    class="rounded-[10px] border border-weact bg-weact-50 p-4"
    aria-label="Et ensuite ?"
    data-testid="booking-next"
  >
    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-weact">
      <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
      Et ensuite ?
    </span>
    <BookingSwap>
      <div :key="next.headline">
        <h2 class="mt-2 text-base font-bold text-balance text-gray-900" data-testid="booking-next-headline">
          {{ next.headline }}
        </h2>
        <p v-if="next.text" class="mt-1.5 text-sm text-gray-800" data-testid="booking-next-text">{{ next.text }}</p>
        <p
          v-if="remaining"
          class="mt-2.5 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-sm font-bold text-amber-800 tabular-nums"
          data-testid="booking-next-countdown"
        >
          <Clock class="h-3.5 w-3.5" aria-hidden="true" />
          {{ remaining }}
        </p>
        <p v-if="next.who" class="mt-2 text-xs text-gray-500" data-testid="booking-next-who">{{ next.who }}</p>
      </div>
    </BookingSwap>
  </section>
</template>
