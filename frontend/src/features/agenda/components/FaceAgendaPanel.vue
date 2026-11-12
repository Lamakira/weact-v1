<script setup lang="ts">
/**
 * FaceAgendaPanel : panneau « Mon agenda » du tableau de bord Face. Les 3 prochains événements
 * engagés ou en attente des 30 prochains jours. Un seul appel : du jour métier du Bénin à +30 jours.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import RPanel from '@/components/regie/RPanel.vue'
import { Skeleton } from '@/components/ui/skeleton'
import { businessTodayIso } from '@/lib/dates'
import { agendaApi } from '../services/agendaApi'
import type { AgendaEvent } from '../types'
import { addDays, formatEventWhen } from '../utils/agendaDates'
import { eventDotClass, eventRoute } from '../utils/agendaEvents'

const MAX_EVENTS = 3
const HORIZON_DAYS = 30

const events = ref<AgendaEvent[]>([])
const isLoading = ref(true)
const failed = ref(false)

const upcoming = computed(() => events.value.slice(0, MAX_EVENTS))

onMounted(async () => {
  const from = businessTodayIso()
  try {
    const response = await agendaApi.getAgenda(from, addDays(from, HORIZON_DAYS))
    const today = response.data.today
    events.value = response.data.events.filter(
      (event) => (event.state === 'engaged' || event.state === 'pending') && event.end >= today,
    )
  } catch {
    failed.value = true
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <RPanel title="Mon agenda" data-testid="face-agenda-panel">
    <div class="p-4 sm:p-5">
      <Skeleton v-if="isLoading" class="h-12 w-full" data-testid="face-agenda-panel-skeleton" />
      <p v-else-if="failed" class="text-dash text-ink-3" data-testid="face-agenda-panel-error">
        Impossible de charger votre agenda pour le moment.
      </p>
      <p v-else-if="upcoming.length === 0" class="text-dash text-ink-3" data-testid="face-agenda-panel-empty">
        Aucun engagement dans les 30 prochains jours
      </p>
      <ul v-else class="space-y-2.5" data-testid="face-agenda-panel-list">
        <li v-for="event in upcoming" :key="`${event.kind}-${event.uuid}`">
          <RouterLink
            :to="eventRoute(event)"
            class="flex items-center gap-3 hover:opacity-80"
            data-testid="face-agenda-panel-item"
          >
            <span class="size-2 shrink-0 rounded-full" :class="eventDotClass(event)" aria-hidden="true" />
            <span class="min-w-0">
              <span class="block truncate text-dash font-medium text-ink">{{ event.title }}</span>
              <span class="block text-[12.5px] text-ink-3">{{ formatEventWhen(event) }}</span>
            </span>
          </RouterLink>
        </li>
      </ul>

      <RouterLink
        :to="{ name: 'face-agenda' }"
        class="mt-4 inline-block text-dash font-medium text-ink-2 underline-offset-2 hover:text-ink hover:underline"
        data-testid="face-agenda-panel-link"
      >
        Ouvrir mon agenda
      </RouterLink>
    </div>
  </RPanel>
</template>
