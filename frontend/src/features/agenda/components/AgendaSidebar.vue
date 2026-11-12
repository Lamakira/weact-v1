<script setup lang="ts">
import { computed } from 'vue'
import { CalendarOff } from 'lucide-vue-next'
import RPanel from '@/components/regie/RPanel.vue'
import type { AgendaEarnings as AgendaEarningsData, AgendaEvent, AgendaFilterKey, AgendaFilters } from '../types'
import { eventFilterKey, monthEnd, monthStart, monthStats } from '../utils/agendaDates'
import AgendaEarnings from './AgendaEarnings.vue'

/** Colonne gauche de l'agenda ordinateur : bouton, filtres « Afficher », « Ce mois-ci », « Gains à venir ». */
const props = defineProps<{
  month: string
  today: string | null
  events: AgendaEvent[]
  unavailableDays: string[]
  filters: AgendaFilters
  earnings: AgendaEarningsData | null
  selectionMode?: boolean
}>()

const emit = defineEmits<{
  toggle: [key: AgendaFilterKey]
  'block-days': []
}>()

const FILTER_LABELS: { key: AgendaFilterKey; label: string }[] = [
  { key: 'bookings', label: 'Bookings' },
  { key: 'missions', label: 'Missions' },
  { key: 'pending', label: 'En attente' },
  { key: 'blocked', label: 'Indisponible (jours)' },
  { key: 'done', label: 'Terminés' },
]

const monthEvents = computed(() => {
  const first = monthStart(props.month)
  const last = monthEnd(props.month)
  return props.events.filter((event) => event.end >= first && event.start <= last)
})

const counts = computed<Record<AgendaFilterKey, number>>(() => {
  const result: Record<AgendaFilterKey, number> = { bookings: 0, missions: 0, pending: 0, blocked: 0, done: 0 }
  monthEvents.value.forEach((event) => result[eventFilterKey(event)]++)
  result.blocked = stats.value.blocked
  return result
})

const stats = computed(() => monthStats(props.month, props.today, props.unavailableDays, props.events))
</script>

<template>
  <aside class="space-y-4" data-testid="agenda-sidebar">
    <div>
      <button
        type="button"
        class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-weact px-4 text-sm font-semibold text-white hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="selectionMode"
        data-testid="agenda-block-days-lg"
        @click="emit('block-days')"
      >
        <CalendarOff class="size-4" aria-hidden="true" />
        Bloquer des jours
      </button>
      <p class="mt-1.5 text-center text-[12px] text-ink-3">ou glissez sur le calendrier</p>
    </div>

    <RPanel title="Afficher" heading-tag="h3">
      <ul class="space-y-1 p-3" data-testid="sidebar-filters">
        <li v-for="item in FILTER_LABELS" :key="item.key">
          <label class="flex min-h-9 cursor-pointer items-center gap-2.5 rounded-md px-1.5 text-[13.5px] text-ink hover:bg-sidebar">
            <input
              type="checkbox"
              class="size-4 rounded border-slate-300 accent-teal-600"
              :checked="filters[item.key]"
              :data-testid="`filter-${item.key}`"
              @change="emit('toggle', item.key)"
            />
            <span class="flex-1">{{ item.label }}</span>
            <span class="text-[12.5px] text-ink-3" :data-testid="`count-${item.key}`">{{ counts[item.key] }}</span>
          </label>
        </li>
      </ul>
    </RPanel>

    <RPanel title="Ce mois-ci" heading-tag="h3">
      <dl class="space-y-1.5 p-4 text-[13.5px]" data-testid="sidebar-stats">
        <div class="flex justify-between">
          <dt class="text-ink-2">Jours bookés</dt>
          <dd class="font-semibold text-ink" data-testid="stat-booked">{{ stats.booked }}</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-ink-2">Jours bloqués</dt>
          <dd class="font-semibold text-ink" data-testid="stat-blocked">{{ stats.blocked }}</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-ink-2">Jours libres</dt>
          <dd class="font-semibold text-ink" data-testid="stat-free">{{ stats.free }}</dd>
        </div>
      </dl>
    </RPanel>

    <AgendaEarnings :earnings="earnings" />
  </aside>
</template>
