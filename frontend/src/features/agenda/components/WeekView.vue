<script setup lang="ts">
import { computed } from 'vue'
import type { AgendaEvent, AgendaFilters } from '../types'
import { useDaySelection } from '../composables/useDaySelection'
import {
  buildDayStates,
  eventsOnDay,
  filterEvents,
  formatDayLong,
  isDayTouchable,
  weekDays,
} from '../utils/agendaDates'
import { eventKindLabel } from '../utils/agendaEvents'
import AgendaEventChip from './AgendaEventChip.vue'

/**
 * Vue Semaine ordinateur : 7 colonnes, étiquettes plus grandes avec l'heure de début d'un booking et
 * la contrepartie dessous. Pas de grille horaire. Même sélection à la souris que la vue Mois.
 */
const props = defineProps<{
  /** Un jour de la semaine affichée. */
  day: string
  today: string | null
  events: AgendaEvent[]
  unavailableDays: string[]
  filters: AgendaFilters
  selectionMode?: boolean
  selected?: string[]
  activeDay?: string | null
  horizonEnd?: string | null
}>()

const emit = defineEmits<{
  'select-day': [day: string]
  'select-range': [days: string[], extend: boolean]
}>()

const WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']

const days = computed(() => weekDays(props.day))
const states = computed(() => buildDayStates(props.unavailableDays, props.events, days.value[0]!, days.value[6]!))
const visibleEvents = computed(() => filterEvents(props.events, props.filters))
const blockedSet = computed(() => new Set(props.unavailableDays))
const selectedSet = computed(() => new Set(props.selected ?? []))

function isTouchable(iso: string): boolean {
  return isDayTouchable(states.value[iso], iso, props.today, props.horizonEnd ?? null)
}

const selection = useDaySelection({
  isTouchable,
  selectionMode: () => props.selectionMode ?? false,
  selected: () => props.selected ?? [],
  onRange: (range, extend) => emit('select-range', range, extend),
  onDay: (iso) => emit('select-day', iso),
})

function dayEvents(iso: string): AgendaEvent[] {
  return eventsOnDay(visibleEvents.value, iso)
}

function isBlockedVisible(iso: string): boolean {
  return props.filters.blocked && blockedSet.value.has(iso)
}

function isSelected(iso: string): boolean {
  return selectedSet.value.has(iso) || selection.dragDays().includes(iso)
}

function label(iso: string): string {
  const base = formatDayLong(iso)
  if (props.selectionMode) {
    if (selectedSet.value.has(iso)) return `${base}, sélectionné`
    return isTouchable(iso) ? base : `${base}, non sélectionnable`
  }
  const parts = dayEvents(iso).map((e) => `${eventKindLabel(e)} ${e.title}`)
  if (isBlockedVisible(iso)) parts.push('indisponible')
  return parts.length > 0 ? `${base}, ${parts.join(', ')}` : base
}

function cellClasses(iso: string): string[] {
  const classes: string[] = []
  if (isSelected(iso)) classes.push('bg-amber-100 ring-2 ring-amber-600')
  else if (props.selectionMode && !isTouchable(iso)) classes.push('bg-slate-100')
  else if (isBlockedVisible(iso)) classes.push('bg-slate-100')
  if (props.activeDay === iso && !props.selectionMode) classes.push('ring-2 ring-slate-900')
  return classes
}
</script>

<template>
  <div class="grid grid-cols-7 gap-1" data-testid="week-view">
    <div v-for="(iso, i) in days" :key="iso" class="min-w-0">
      <button
        type="button"
        class="block w-full rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-1 disabled:cursor-not-allowed"
        :aria-label="label(iso)"
        :aria-pressed="selectionMode ? selectedSet.has(iso) : undefined"
        :disabled="selectionMode && !isTouchable(iso)"
        :data-testid="`week-day-${iso}`"
        @mousedown="selection.onMouseDown(iso, $event)"
        @mouseenter="selection.onMouseEnter(iso)"
        @click="selection.onClick(iso, $event)"
      >
        <span class="block min-h-[320px] rounded-lg border border-slate-200 p-2" :class="cellClasses(iso)">
          <span class="mb-2 flex items-center gap-1.5 text-[12px] font-medium text-slate-500">
            {{ WEEKDAYS[i] }}
            <span
              class="grid h-6 min-w-6 place-items-center rounded-full px-1 text-[13px]"
              :class="iso === today ? 'bg-slate-900 text-white' : 'text-slate-800'"
            >
              {{ Number(iso.slice(8, 10)) }}
            </span>
          </span>

          <span v-if="isBlockedVisible(iso) && !selectionMode" class="mb-1 block text-[12px] font-medium text-slate-500">
            Indisponible
          </span>

          <span class="grid gap-1.5">
            <span v-for="event in dayEvents(iso)" :key="`${event.kind}-${event.uuid}`" class="block">
              <AgendaEventChip :event="event" :with-text="true" large />
              <span class="mt-0.5 block truncate px-0.5 text-[11.5px] text-slate-500">{{ event.counterpart }}</span>
            </span>
          </span>
        </span>
      </button>
    </div>
  </div>
</template>
