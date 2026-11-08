<script setup lang="ts">
import { computed } from 'vue'
import type { AgendaEvent, AgendaFilters } from '../types'
import { useDaySelection } from '../composables/useDaySelection'
import {
  WEEKDAY_INITIALS,
  buildDayStates,
  eventDayLabels,
  filterEvents,
  formatDayLong,
  isDayTouchable,
  monthGrid,
} from '../utils/agendaDates'
import { eventKindLabel } from '../utils/agendaEvents'
import AgendaEventChip from './AgendaEventChip.vue'

/**
 * Vue Mois ordinateur : cases hautes avec étiquettes d'événement empilées (3 au plus puis « +N »).
 * La touchabilité d'un jour se juge sur TOUS les événements : un type masqué par un filtre engage
 * quand même le jour. Glisser de jour touchable à jour touchable émet `select-range`.
 */
const props = defineProps<{
  month: string
  today: string | null
  events: AgendaEvent[]
  unavailableDays: string[]
  filters: AgendaFilters
  selectionMode?: boolean
  selected?: string[]
  /** Jour affiché dans le panneau de droite. */
  activeDay?: string | null
  horizonEnd?: string | null
}>()

const emit = defineEmits<{
  'select-day': [day: string]
  'select-range': [days: string[], extend: boolean]
}>()

const MAX_CHIPS = 3

const days = computed(() => monthGrid(props.month))
const weeks = computed(() => Array.from({ length: days.value.length / 7 }, (_, i) => days.value.slice(i * 7, i * 7 + 7)))
const gridFrom = computed(() => days.value[0]!.iso)
const gridTo = computed(() => days.value[days.value.length - 1]!.iso)

const states = computed(() => buildDayStates(props.unavailableDays, props.events, gridFrom.value, gridTo.value))
const visibleEvents = computed(() => filterEvents(props.events, props.filters))
const blockedSet = computed(() => new Set(props.unavailableDays))

interface Chip {
  event: AgendaEvent
  withText: boolean
}

/** Étiquettes par jour, dans l'ordre des événements. */
const chipsByDay = computed(() => {
  const map = new Map<string, Chip[]>()
  for (const event of visibleEvents.value) {
    for (const { iso, withText } of eventDayLabels(event, gridFrom.value, gridTo.value)) {
      const list = map.get(iso) ?? []
      list.push({ event, withText })
      map.set(iso, list)
    }
  }
  return map
})

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
  const parts = (chipsByDay.value.get(iso) ?? []).map((c) => `${eventKindLabel(c.event)} ${c.event.title}`)
  if (isBlockedVisible(iso)) parts.push('indisponible')
  return parts.length > 0 ? `${base}, ${parts.join(', ')}` : base
}

function cellClasses(iso: string): string[] {
  const classes: string[] = []
  if (props.selectionMode) {
    if (isSelected(iso)) classes.push('bg-amber-100 ring-2 ring-amber-600')
    else if (!isTouchable(iso)) classes.push('bg-slate-100')
  } else if (isSelected(iso)) {
    classes.push('bg-amber-100 ring-2 ring-amber-600')
  } else if (isBlockedVisible(iso)) {
    classes.push('bg-slate-100')
  }
  if (props.activeDay === iso && !props.selectionMode) classes.push('ring-2 ring-slate-900')
  return classes
}
</script>

<template>
  <div role="grid" class="select-none" data-testid="month-grid-large">
    <div role="row" class="mb-1 grid grid-cols-7">
      <div
        v-for="(initial, i) in WEEKDAY_INITIALS"
        :key="i"
        role="columnheader"
        class="py-1 text-center text-[12px] font-medium text-slate-500"
      >
        {{ ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'][i] }}
      </div>
    </div>

    <div v-for="(week, w) in weeks" :key="w" role="row" class="grid grid-cols-7">
      <div v-for="day in week" :key="day.iso" role="gridcell" class="p-0.5">
        <span
          v-if="!day.inMonth"
          class="block min-h-[104px] p-1.5 text-[12.5px] text-slate-300"
          aria-hidden="true"
          :data-testid="`cell-outside-${day.iso}`"
        >
          {{ Number(day.iso.slice(8, 10)) }}
        </span>

        <button
          v-else
          type="button"
          class="block w-full rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-1 disabled:cursor-not-allowed"
          :aria-label="label(day.iso)"
          :aria-pressed="selectionMode ? selectedSet.has(day.iso) : undefined"
          :disabled="selectionMode && !isTouchable(day.iso)"
          :data-testid="`cell-${day.iso}`"
          @mousedown="selection.onMouseDown(day.iso, $event)"
          @mouseenter="selection.onMouseEnter(day.iso)"
          @click="selection.onClick(day.iso, $event)"
        >
          <span
            class="block min-h-[104px] rounded-lg border border-slate-200 p-1.5"
            :class="cellClasses(day.iso)"
          >
            <span class="mb-1 flex items-center justify-between">
              <span
                class="grid h-6 min-w-6 place-items-center rounded-full px-1 text-[12.5px] font-medium"
                :class="day.iso === today ? 'bg-slate-900 text-white' : 'text-slate-700'"
                :data-testid="day.iso === today ? 'cell-today' : undefined"
              >
                {{ Number(day.iso.slice(8, 10)) }}
              </span>
            </span>

            <span
              v-if="isBlockedVisible(day.iso) && !selectionMode"
              class="block text-[11.5px] font-medium text-slate-500"
              data-testid="cell-blocked-text"
            >
              Indisponible
            </span>

            <span class="grid gap-0.5">
              <AgendaEventChip
                v-for="chip in (chipsByDay.get(day.iso) ?? []).slice(0, MAX_CHIPS)"
                :key="`${chip.event.kind}-${chip.event.uuid}`"
                :event="chip.event"
                :with-text="chip.withText"
              />
            </span>

            <span
              v-if="(chipsByDay.get(day.iso)?.length ?? 0) > MAX_CHIPS"
              class="mt-0.5 block text-[11.5px] font-medium text-slate-500"
              data-testid="cell-more"
            >
              +{{ (chipsByDay.get(day.iso)?.length ?? 0) - MAX_CHIPS }}
            </span>
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
