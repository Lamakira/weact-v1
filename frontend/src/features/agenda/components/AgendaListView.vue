<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { formatMoney } from '@/utils/money'
import type { AgendaEvent, AgendaFilters } from '../types'
import {
  eachDay,
  filterEvents,
  formatListWhen,
  formatWeekLabel,
  groupByWeek,
  groupConsecutiveDays,
  monthEnd,
  monthStart,
} from '../utils/agendaDates'
import { eventDotClass, eventRoute } from '../utils/agendaEvents'

/**
 * Vue Liste ordinateur : événements du mois affiché groupés par semaine ; les jours bloqués
 * consécutifs forment une seule ligne « Indisponible ». Le montant net n'apparaît que s'il est sécurisé.
 */
const props = defineProps<{
  month: string
  events: AgendaEvent[]
  unavailableDays: string[]
  filters: AgendaFilters
  /** Téléphone : une ligne est un bouton qui émet `select-day` (au lieu d'un lien vers le détail). */
  selectable?: boolean
}>()

const emit = defineEmits<{ 'select-day': [day: string] }>()

type Row =
  | { type: 'event'; start: string; end: string; event: AgendaEvent }
  | { type: 'blocked'; start: string; end: string }

const from = computed(() => monthStart(props.month))
const to = computed(() => monthEnd(props.month))

const groups = computed(() => {
  const rows: Row[] = filterEvents(props.events, props.filters)
    .filter((event) => event.end >= from.value && event.start <= to.value)
    .map((event) => ({ type: 'event', start: event.start, end: event.end, event }))
  if (props.filters.blocked) {
    const inMonth = props.unavailableDays.filter((iso) => iso >= from.value && iso <= to.value)
    groupConsecutiveDays(inMonth).forEach((range) => rows.push({ type: 'blocked', ...range }))
  }
  return groupByWeek(rows, from.value)
})

function dayCount(row: Row): number {
  return eachDay(row.start, row.end).length
}

/** Premier jour de la ligne dans le mois affiché (une plage commencée le mois précédent s'ouvre au 1er). */
function onSelectRow(row: Row): void {
  emit('select-day', row.start < from.value ? from.value : row.start)
}

function amountOf(event: AgendaEvent): string | null {
  return event.secured && event.amount_face ? formatMoney(event.amount_face) : null
}
</script>

<template>
  <div data-testid="agenda-list-view">
    <p v-if="groups.length === 0" class="py-8 text-center text-dash text-ink-3" data-testid="list-empty">
      Rien à afficher ce mois-ci.
    </p>
    <section v-for="group in groups" :key="group.week" class="mb-4" data-testid="list-week">
      <h3 class="mb-1 text-[12px] font-semibold uppercase tracking-wide text-ink-3">
        {{ formatWeekLabel(group.week) }}
      </h3>
      <ul class="divide-y divide-line rounded-lg ring-1 ring-line">
        <li v-for="row in group.items" :key="`${row.type}-${row.start}-${row.type === 'event' ? row.event.uuid : ''}`">
          <component
            :is="selectable ? 'button' : row.type === 'event' ? RouterLink : 'div'"
            v-bind="selectable ? { type: 'button' } : row.type === 'event' ? { to: eventRoute(row.event) } : {}"
            class="flex min-h-12 items-center gap-3 px-3 py-2"
            :class="[selectable ? 'w-full text-left' : '', selectable || row.type === 'event' ? 'hover:bg-sidebar' : '']"
            data-testid="list-row"
            @click="selectable && onSelectRow(row)"
          >
            <span
              class="size-2 shrink-0 rounded-full"
              :class="row.type === 'event' ? eventDotClass(row.event) : 'bg-slate-400'"
              aria-hidden="true"
            />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-[14px] font-medium text-ink">
                <template v-if="row.type === 'event'">{{ row.event.title }} · {{ row.event.counterpart }}</template>
                <template v-else>Indisponible</template>
              </span>
              <span class="block text-[12.5px] text-ink-3">
                <template v-if="row.type === 'event'">{{ formatListWhen(row.event) }}</template>
                <template v-else>
                  {{ formatListWhen({ kind: 'booking', start: row.start, end: row.end }).replace(/ · .*$/, '') }}
                  · {{ dayCount(row) }} jour{{ dayCount(row) > 1 ? 's' : '' }}
                </template>
              </span>
            </span>
            <span
              v-if="row.type === 'event' && amountOf(row.event)"
              class="shrink-0 text-[13px] font-semibold text-ink"
              data-testid="list-amount"
            >
              {{ amountOf(row.event) }}
            </span>
          </component>
        </li>
      </ul>
    </section>
  </div>
</template>
