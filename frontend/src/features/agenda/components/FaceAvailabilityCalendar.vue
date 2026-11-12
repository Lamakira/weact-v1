<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { businessTodayIso } from '@/lib/dates'
import { useFaceAvailability } from '../composables/useFaceAvailability'
import type { DayState } from '../types'
import { addMonths, formatMonthTitle, monthEnd, monthStart } from '../utils/agendaDates'
import MonthCalendar from './MonthCalendar.vue'

/**
 * Disponibilités d'une Face vues par un Producteur : « Libre » ou « Indisponible », sans motif ni
 * distinction entre jour bloqué et jour engagé. Lecture seule.
 */
const props = defineProps<{
  /** uuid de la Face. */
  faceId: string
}>()

const availability = useFaceAvailability(toRef(props, 'faceId'))
const month = ref(monthStart(businessTodayIso()))

const today = computed(() => availability.today.value ?? businessTodayIso())

const states = computed<Record<string, DayState[]>>(() => {
  const result: Record<string, DayState[]> = {}
  const from = monthStart(month.value)
  const to = monthEnd(month.value)
  availability.unavailableInMonth(month.value).forEach((day) => {
    if (day >= from && day <= to) result[day] = ['blocked']
  })
  return result
})

const failed = computed(() => availability.hasFailed(month.value))

watch(
  [() => props.faceId, month],
  () => {
    void availability.loadMonth(month.value)
  },
  { immediate: true },
)
</script>

<template>
  <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5" data-testid="face-availability-calendar">
    <h2 class="text-lg font-semibold text-gray-900">Disponibilités</h2>

    <div class="mb-2 mt-3 flex items-center justify-between">
      <button
        type="button"
        class="grid size-10 place-items-center rounded-full text-gray-600 hover:bg-gray-100"
        aria-label="Mois précédent"
        data-testid="availability-prev"
        @click="month = addMonths(month, -1)"
      >
        <ChevronLeft class="size-5" aria-hidden="true" />
      </button>
      <h3 class="text-[15px] font-semibold text-gray-900" aria-live="polite" data-testid="availability-month-title">
        {{ formatMonthTitle(month) }}
      </h3>
      <button
        type="button"
        class="grid size-10 place-items-center rounded-full text-gray-600 hover:bg-gray-100"
        aria-label="Mois suivant"
        data-testid="availability-next"
        @click="month = addMonths(month, 1)"
      >
        <ChevronRight class="size-5" aria-hidden="true" />
      </button>
    </div>

    <MonthCalendar
      :month="month"
      :today="today"
      :states="states"
      :interactive="false"
      readonly-style
    />

    <p v-if="failed" class="mt-2 text-[12.5px] text-gray-500" data-testid="availability-error">
      Impossible de charger les disponibilités de ce mois.
    </p>

    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-[12px] text-slate-600" data-testid="availability-legend">
      <li class="flex items-center gap-1.5">
        <span class="size-3 rounded border border-slate-200" aria-hidden="true" />Libre
      </li>
      <li class="flex items-center gap-1.5">
        <span class="size-3 rounded bg-slate-200" aria-hidden="true" />Indisponible
      </li>
    </ul>
    <p class="mt-2 text-[12.5px] text-gray-500">Les jours indisponibles ne peuvent pas être réservés.</p>
  </section>
</template>
