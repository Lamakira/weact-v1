<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { Loader2, X } from 'lucide-vue-next'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'
import type { AgendaEvent } from '../types'
import { formatDayLong, formatPeriod } from '../utils/agendaDates'

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

/** Le jour est modifiable : aujourd'hui ou futur, dans l'horizon. */
const canAct = computed(() => {
  if (!props.day || !props.today) return false
  if (props.day < props.today) return false
  return !props.horizonEnd || props.day <= props.horizonEnd
})
const hasEngagement = computed(() => props.events.some((e) => e.state === 'engaged'))

const dialogRef = ref<HTMLElement | null>(null)
let previouslyFocused: HTMLElement | null = null

function stateLabel(event: AgendaEvent): string {
  if (event.kind === 'mission') return 'mission'
  if (event.state === 'pending') return 'demande en attente'
  if (event.state === 'done') return 'booking terminé'
  return 'booking accepté'
}

function barClass(event: AgendaEvent): string {
  if (event.state === 'pending') return 'bg-amber-500'
  if (event.state === 'done') return 'bg-teal-300'
  return event.kind === 'mission' ? 'bg-violet-700' : 'bg-teal-600'
}

function detailRoute(event: AgendaEvent) {
  return event.kind === 'mission'
    ? { name: 'face-mission-detail', params: { id: event.uuid } }
    : { name: 'face-booking-detail', params: { id: event.uuid } }
}

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
              <span class="w-1 shrink-0 rounded-full" :class="barClass(event)" aria-hidden="true" />
              <div class="min-w-0 flex-1">
                <p class="truncate text-[14px] font-semibold text-gray-900">{{ event.title }}</p>
                <p class="text-[13px] text-gray-600">{{ stateLabel(event) }}</p>
                <p class="text-[13px] text-gray-500">
                  {{ formatPeriod(event.start, event.end) }} · avec {{ event.counterpart }}
                </p>
                <RouterLink
                  :to="detailRoute(event)"
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

          <template v-if="canAct">
            <button
              v-if="blocked"
              type="button"
              class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="busy"
              data-testid="agenda-day-unblock"
              @click="emit('unblock', day)"
            >
              <Loader2 v-if="busy" class="size-4 animate-spin" aria-hidden="true" />
              Rendre disponible
            </button>
            <button
              v-else-if="!hasEngagement"
              type="button"
              class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-weact px-4 text-sm font-semibold text-white hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="busy"
              data-testid="agenda-day-block"
              @click="emit('block', day)"
            >
              <Loader2 v-if="busy" class="size-4 animate-spin" aria-hidden="true" />
              Marquer indisponible
            </button>
            <p v-else class="text-[12.5px] text-gray-500">
              Un jour engagé ne peut pas être bloqué : passez par l'annulation ou le changement de date.
            </p>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
