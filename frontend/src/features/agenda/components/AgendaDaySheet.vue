<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { X } from 'lucide-vue-next'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'
import type { AgendaEvent } from '../types'
import { formatDayLong, formatPeriod } from '../utils/agendaDates'
import { canActOnDay, eventDotClass, eventRoute, eventStateLabel } from '../utils/agendaEvents'
import AgendaDayActions from './AgendaDayActions.vue'

/**
 * Panneau bas d'un jour de l'agenda : événements du jour, puis l'unique action possible
 * (bloquer ou libérer un jour futur). Un jour passé n'offre aucune action. La page fait les appels ;
 * le panneau émet `block` / `unblock`.
 */
const props = defineProps<{
  /** Jour ouvert (AAAA-MM-JJ) ; null = fermé. */
  day: string | null
  today: string | null
  /** Dernier jour modifiable (aujourd'hui + 365). */
  horizonEnd: string | null
  /** Événements qui couvrent ce jour. */
  events: AgendaEvent[]
  blocked: boolean
  busy?: boolean
}>()

const emit = defineEmits<{
  close: []
  block: [day: string]
  unblock: [day: string]
}>()

const isOpen = computed(() => props.day !== null)

const canAct = computed(() => canActOnDay(props.day, props.today, props.horizonEnd))
const hasEngagement = computed(() => props.events.some((e) => e.state === 'engaged'))

const dialogRef = ref<HTMLElement | null>(null)
let previouslyFocused: HTMLElement | null = null

watch(isOpen, async (open) => {
  if (open) {
    previouslyFocused = document.activeElement as HTMLElement | null
    await nextTick()
    dialogRef.value?.querySelector<HTMLElement>('[data-autofocus]')?.focus()
  } else if (previouslyFocused) {
    previouslyFocused.focus()
    previouslyFocused = null
  }
})

function handleKeydown(e: KeyboardEvent): void {
  if (!isOpen.value) return
  if (e.key === 'Escape') {
    emit('close')
    return
  }
  if (e.key === 'Tab' && dialogRef.value) {
    const focusable = dialogRef.value.querySelectorAll<HTMLElement>(
      'button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])',
    )
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (!first || !last) return
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault()
      first.focus()
    }
  }
}

useDismissOnDeactivate(() => isOpen.value, () => emit('close'))
onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="day"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center"
        data-testid="agenda-day-sheet"
        @click.self="emit('close')"
      >
        <div
          ref="dialogRef"
          class="max-h-[85dvh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-white px-5 pb-6 pt-2 shadow-xl sm:rounded-2xl sm:pt-5"
          role="dialog"
          aria-modal="true"
          aria-labelledby="agenda-day-title"
        >
          <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-slate-300 sm:hidden" aria-hidden="true" />

          <div class="mb-4 flex items-start justify-between gap-3">
            <h3 id="agenda-day-title" class="text-lg font-semibold text-gray-900" data-testid="agenda-day-title">
              {{ formatDayLong(day) }}
            </h3>
            <button
              type="button"
              class="-mr-1.5 grid size-9 shrink-0 place-items-center rounded-full text-gray-500 hover:bg-gray-100"
              aria-label="Fermer"
              data-autofocus
              data-testid="agenda-day-close"
              @click="emit('close')"
            >
              <X class="size-5" aria-hidden="true" />
            </button>
          </div>

          <ul v-if="events.length > 0" class="mb-4 grid gap-3" data-testid="agenda-day-events">
            <li v-for="event in events" :key="`${event.kind}-${event.uuid}`" class="flex gap-3">
              <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="eventDotClass(event)" aria-hidden="true" />
              <div class="min-w-0 flex-1">
                <p class="truncate text-[14px] font-semibold text-gray-900">{{ event.title }}</p>
                <p class="text-[13px] text-gray-600">{{ eventStateLabel(event) }}</p>
                <p class="text-[13px] text-gray-500">
                  {{ formatPeriod(event.start, event.end) }} · avec {{ event.counterpart }}
                </p>
                <RouterLink
                  :to="eventRoute(event)"
                  class="mt-1.5 inline-flex min-h-9 items-center text-[13px] font-medium text-weact underline-offset-2 hover:underline"
                  data-testid="agenda-event-link"
                >
                  {{ event.kind === 'mission' ? 'Voir la mission' : 'Voir le booking' }}
                </RouterLink>
              </div>
            </li>
          </ul>

          <p v-if="events.length === 0 && blocked" class="mb-4 text-[13.5px] text-gray-600">
            Vous avez marqué ce jour comme indisponible.
          </p>
          <p v-else-if="events.length === 0" class="mb-4 text-[13.5px] text-gray-600">Aucun événement ce jour.</p>

          <AgendaDayActions
            :day="day"
            :blocked="blocked"
            :can-act="canAct"
            :has-engagement="hasEngagement"
            :busy="busy"
            @block="emit('block', $event)"
            @unblock="emit('unblock', $event)"
          />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
