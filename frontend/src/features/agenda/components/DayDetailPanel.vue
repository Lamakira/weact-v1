<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import RPanel from '@/components/regie/RPanel.vue'
import { formatMoney } from '@/utils/money'
import type { AgendaEvent } from '../types'
import { formatDayLong, formatEventWhen, formatPeriod } from '../utils/agendaDates'
import { canActOnDay, eventDotClass, eventRoute, eventStateLabel } from '../utils/agendaEvents'
import AgendaDayActions from './AgendaDayActions.vue'

/**
 * Colonne droite de l'agenda ordinateur : le jour sélectionné (événements, action bloquer / libérer)
 * puis « À venir ». Remplace le panneau du bas du téléphone ; l'action vient du même composant.
 */
const props = defineProps<{
  day: string
  today: string | null
  horizonEnd: string | null
  /** Événements qui couvrent ce jour. */
  events: AgendaEvent[]
  blocked: boolean
  /** Prochains événements (filtres appliqués), déjà limités. */
  upcoming: AgendaEvent[]
  busy?: boolean
}>()

const emit = defineEmits<{
  block: [day: string]
  unblock: [day: string]
}>()

const canAct = computed(() => canActOnDay(props.day, props.today, props.horizonEnd))
const hasEngagement = computed(() => props.events.some((e) => e.state === 'engaged'))

function hours(event: AgendaEvent): string | null {
  return event.kind === 'booking' && event.start_time && event.end_time ? `${event.start_time} à ${event.end_time}` : null
}

function amountOf(event: AgendaEvent): string | null {
  return event.secured && event.amount_face ? formatMoney(event.amount_face) : null
}
</script>

<template>
  <div class="space-y-4" data-testid="agenda-day-detail">
    <RPanel>
      <div class="p-4">
        <h3 class="mb-3 text-[15px] font-semibold text-ink" data-testid="detail-title">{{ formatDayLong(day) }}</h3>

        <ul v-if="events.length > 0" class="mb-4 grid gap-3" data-testid="detail-events">
          <li v-for="event in events" :key="`${event.kind}-${event.uuid}`" class="flex gap-2.5">
            <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="eventDotClass(event)" aria-hidden="true" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-[14px] font-semibold text-ink">{{ event.title }}</p>
              <p class="text-[13px] text-ink-2">{{ eventStateLabel(event) }}</p>
              <p class="text-[13px] text-ink-3">
                {{ formatPeriod(event.start, event.end) }}<template v-if="hours(event)"> · {{ hours(event) }}</template>
              </p>
              <p class="text-[13px] text-ink-3">avec {{ event.counterpart }}</p>
              <p v-if="amountOf(event)" class="text-[13px] font-medium text-ink" data-testid="detail-amount">
                {{ amountOf(event) }} nets, en séquestre
              </p>
              <RouterLink
                :to="eventRoute(event)"
                class="mt-1 inline-flex min-h-8 items-center text-[13px] font-medium text-weact underline-offset-2 hover:underline"
                data-testid="detail-event-link"
              >
                {{ event.kind === 'mission' ? 'Voir la mission' : 'Voir le booking' }}
              </RouterLink>
            </div>
          </li>
        </ul>

        <p v-if="events.length === 0 && blocked" class="mb-4 text-[13.5px] text-ink-2">
          Vous avez marqué ce jour comme indisponible.
        </p>
        <p v-else-if="events.length === 0" class="mb-4 text-[13.5px] text-ink-2">Aucun événement ce jour.</p>

        <AgendaDayActions
          :day="day"
          :blocked="blocked"
          :can-act="canAct"
          :has-engagement="hasEngagement"
          :busy="busy"
          testid-prefix="agenda-detail"
          @block="emit('block', $event)"
          @unblock="emit('unblock', $event)"
        />
      </div>
    </RPanel>

    <RPanel title="À venir" heading-tag="h3" data-testid="detail-upcoming">
      <p v-if="upcoming.length === 0" class="p-4 text-[13.5px] text-ink-3">Rien de prévu ce mois-ci.</p>
      <ul v-else class="divide-y divide-line">
        <li v-for="event in upcoming" :key="`${event.kind}-${event.uuid}`">
          <RouterLink
            :to="eventRoute(event)"
            class="flex min-h-12 items-center gap-2.5 px-4 py-2 hover:bg-sidebar"
            data-testid="detail-upcoming-item"
          >
            <span class="size-2 shrink-0 rounded-full" :class="eventDotClass(event)" aria-hidden="true" />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-[13.5px] font-semibold text-ink">{{ event.title }}</span>
              <span class="block text-[12.5px] text-ink-3">{{ formatEventWhen(event) }}</span>
            </span>
          </RouterLink>
        </li>
      </ul>
    </RPanel>
  </div>
</template>
