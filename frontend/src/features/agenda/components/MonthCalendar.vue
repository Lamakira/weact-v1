<script setup lang="ts">
import { computed } from 'vue'
import type { DayState } from '../types'
import { WEEKDAY_INITIALS, formatDayLong, monthGrid } from '../utils/agendaDates'

/**
 * Grille d'un mois (lundi au dimanche), partagée par l'agenda de la Face et la disponibilité vue
 * par un Producteur. Elle ne connaît que des chaînes AAAA-MM-JJ et des états par jour ; l'appelant
 * garde la sélection et réagit à `select-day`.
 *
 * Priorité quand un jour cumule plusieurs états : booking > mission > demande en attente >
 * booking terminé > bloqué (le panneau du jour liste tout). Les couleurs sont portées par un
 * élément interne : le CSS hors @layer de main.css bat les utilities posées sur le bouton.
 */
const props = withDefaults(
  defineProps<{
    /** Un jour du mois affiché (AAAA-MM-JJ). */
    month: string
    /** Jour métier courant, fourni par le serveur ; null tant qu'il n'est pas connu. */
    today: string | null
    states?: Record<string, DayState[]>
    /** Mode « Bloquer des jours » : seuls les jours futurs, ni bloqués ni engagés sont touchables. */
    selectionMode?: boolean
    selected?: string[]
    /** Dernier jour touchable en mode sélection (horizon de blocage). */
    horizonEnd?: string | null
    /** false = lecture seule (cases sans bouton), pour la vue Producteur. */
    interactive?: boolean
    /** Libre = bordure fine, jours passés sans état en gris clair (vue Producteur). */
    readonlyStyle?: boolean
  }>(),
  {
    states: () => ({}),
    selectionMode: false,
    selected: () => [],
    horizonEnd: null,
    interactive: true,
    readonlyStyle: false,
  },
)

const emit = defineEmits<{ 'select-day': [day: string] }>()

const PRIORITY: DayState[] = ['booking', 'mission', 'pending', 'done', 'blocked']
const STATE_LABELS: Record<DayState, string> = {
  booking: 'booking',
  mission: 'mission',
  pending: 'demande en attente',
  done: 'booking terminé',
  blocked: 'indisponible',
}

const weeks = computed(() => {
  const days = monthGrid(props.month)
  return Array.from({ length: days.length / 7 }, (_, i) => days.slice(i * 7, i * 7 + 7))
})

function primaryState(iso: string): DayState | null {
  const list = props.states[iso] ?? []
  return PRIORITY.find((state) => list.includes(state)) ?? null
}

function isPast(iso: string): boolean {
  return props.today !== null && iso < props.today
}

/**
 * Touchable en mode sélection : aujourd'hui ou futur, dans l'horizon, ni bloqué ni engagé. Une demande
 * en attente n'engage rien (le serveur accepte le blocage), le jour reste donc touchable.
 */
function isTouchable(iso: string): boolean {
  if (props.today === null || iso < props.today) return false
  if (props.horizonEnd && iso > props.horizonEnd) return false
  return (props.states[iso] ?? []).every((state) => state === 'pending')
}

function label(iso: string): string {
  const state = primaryState(iso)
  const base = formatDayLong(iso)
  if (props.selectionMode) {
    if (props.selected.includes(iso)) return `${base}, sélectionné`
    return isTouchable(iso) ? base : `${base}, non sélectionnable`
  }
  if (state) return `${base}, ${STATE_LABELS[state]}`
  return props.readonlyStyle ? `${base}, libre` : base
}

const STATE_CLASSES: Record<DayState, string> = {
  booking: 'bg-teal-600 text-white',
  mission: 'bg-violet-700 text-white',
  pending: 'border-[1.5px] border-amber-500 text-amber-800',
  done: 'bg-teal-100 text-teal-800',
  blocked: 'bg-slate-200 text-slate-500 line-through',
}

function cellClasses(iso: string): string[] {
  const classes: string[] = []
  if (props.selectionMode) {
    if (props.selected.includes(iso)) classes.push('bg-amber-100 border-[1.5px] border-amber-600 text-amber-900')
    else if (!isTouchable(iso)) classes.push('bg-slate-200 text-slate-400')
    else classes.push(props.readonlyStyle ? 'border border-slate-200 text-slate-800' : 'text-slate-800')
  } else {
    const state = primaryState(iso)
    if (state === 'blocked' && props.readonlyStyle) classes.push('bg-slate-200 text-slate-500')
    else if (state) classes.push(STATE_CLASSES[state])
    else if (props.readonlyStyle) classes.push(isPast(iso) ? 'text-slate-300' : 'border border-slate-200 text-slate-800')
    else classes.push('text-slate-800')
  }
  if (iso === props.today) classes.push('ring-[1.5px] ring-slate-900')
  return classes
}

function onClick(iso: string): void {
  if (props.selectionMode && !isTouchable(iso)) return
  emit('select-day', iso)
}
</script>

<template>
  <div role="grid" class="select-none" data-testid="month-calendar">
    <div role="row" class="mb-1 grid grid-cols-7">
      <div
        v-for="(initial, i) in WEEKDAY_INITIALS"
        :key="i"
        role="columnheader"
        class="py-1 text-center text-[11.5px] font-medium text-slate-500"
      >
        {{ initial }}
      </div>
    </div>

    <div v-for="(week, w) in weeks" :key="w" role="row" class="grid grid-cols-7">
      <div v-for="day in week" :key="day.iso" role="gridcell" class="p-0.5">
        <span
          v-if="!day.inMonth"
          class="flex min-h-10 items-center justify-center text-[13.5px] text-slate-300"
          aria-hidden="true"
          :data-testid="`day-outside-${day.iso}`"
        >
          {{ Number(day.iso.slice(8, 10)) }}
        </span>

        <button
          v-else-if="interactive"
          type="button"
          class="block min-h-10 w-full rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-1 disabled:cursor-not-allowed"
          :aria-label="label(day.iso)"
          :aria-pressed="selectionMode ? selected.includes(day.iso) : undefined"
          :disabled="selectionMode && !isTouchable(day.iso)"
          :data-testid="`day-${day.iso}`"
          @click="onClick(day.iso)"
        >
          <span
            class="flex min-h-10 items-center justify-center rounded-lg text-[13.5px] font-medium"
            :class="cellClasses(day.iso)"
            :data-state="primaryState(day.iso) ?? 'free'"
          >
            {{ Number(day.iso.slice(8, 10)) }}
          </span>
        </button>

        <div v-else :aria-label="label(day.iso)" :data-testid="`day-${day.iso}`">
          <span
            class="flex min-h-10 items-center justify-center rounded-lg text-[13.5px] font-medium"
            :class="cellClasses(day.iso)"
            :data-state="primaryState(day.iso) ?? 'free'"
          >
            {{ Number(day.iso.slice(8, 10)) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
