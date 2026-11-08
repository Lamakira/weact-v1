<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { CalendarOff, ChevronLeft, ChevronRight, Loader2, X } from 'lucide-vue-next'
import RPanel from '@/components/regie/RPanel.vue'
import { useToast } from '@/composables/useToast'
import { businessTodayIso } from '@/lib/dates'
import AgendaDaySheet from '@/features/agenda/components/AgendaDaySheet.vue'
import AgendaListView from '@/features/agenda/components/AgendaListView.vue'
import AgendaSidebar from '@/features/agenda/components/AgendaSidebar.vue'
import AgendaToolbar from '@/features/agenda/components/AgendaToolbar.vue'
import DayDetailPanel from '@/features/agenda/components/DayDetailPanel.vue'
import MonthCalendar from '@/features/agenda/components/MonthCalendar.vue'
import MonthGridLarge from '@/features/agenda/components/MonthGridLarge.vue'
import WeekView from '@/features/agenda/components/WeekView.vue'
import { useAgendaFilters } from '@/features/agenda/composables/useAgendaFilters'
import { useFaceAgenda } from '@/features/agenda/composables/useFaceAgenda'
import type { AgendaEvent, AgendaView } from '@/features/agenda/types'
import {
  addDays,
  eventsOnDay,
  filterEvents,
  formatDayList,
  formatEventWhen,
  formatMonthTitle,
  formatWeekTitle,
  monthEnd,
  monthStart,
} from '@/features/agenda/utils/agendaDates'
import { eventDotClass, eventRoute } from '@/features/agenda/utils/agendaEvents'

/**
 * « Mon agenda » (Face) : jours bloqués, bookings, missions et demandes en attente du mois.
 * « Aujourd'hui » vient du serveur ; toutes les dates sont des chaînes AAAA-MM-JJ.
 */
const toast = useToast()
const {
  month,
  today,
  unavailableDays,
  events,
  earnings,
  weekAnchor,
  dayStates,
  isLoading,
  isSaving,
  error,
  load,
  previousMonth,
  nextMonth,
  showMonth,
  showWeek,
  updateUnavailable,
} = useFaceAgenda()
const { filters, view, toggle: toggleFilter } = useAgendaFilters()

const BLOCK_HORIZON_DAYS = 365
const horizonEnd = computed(() => (today.value ? addDays(today.value, BLOCK_HORIZON_DAYS) : null))

const selectionMode = ref(false)
const selected = ref<string[]>([])
const sheetDay = ref<string | null>(null)

const upcoming = computed<AgendaEvent[]>(() => {
  const now = today.value
  return now ? events.value.filter((event) => event.end >= now) : []
})

const sheetEvents = computed(() => (sheetDay.value ? eventsOnDay(events.value, sheetDay.value) : []))
const sheetBlocked = computed(() => (sheetDay.value ? unavailableDays.value.includes(sheetDay.value) : false))

const selectedLabel = computed(() => {
  const n = selected.value.length
  return `${n} jour${n > 1 ? 's' : ''} sélectionné${n > 1 ? 's' : ''}`
})

function enterSelection(): void {
  sheetDay.value = null
  selected.value = []
  selectionMode.value = true
}

function leaveSelection(): void {
  selectionMode.value = false
  selected.value = []
}

function onSelectDay(day: string): void {
  if (selectionMode.value) {
    selected.value = selected.value.includes(day)
      ? selected.value.filter((d) => d !== day)
      : [...selected.value, day].sort()
    return
  }
  sheetDay.value = day
}

/** Succès : toast, retour au mode normal ; échec (ex. 422) : le premier message, sélection conservée. */
async function save(payload: { add?: string[]; remove?: string[] }): Promise<boolean> {
  const failure = await updateUnavailable(payload)
  if (failure) {
    toast.error(failure)
    return false
  }
  toast.success('Agenda mis à jour')
  return true
}

async function submitSelection(): Promise<void> {
  if (selected.value.length === 0 || isSaving.value) return
  if (await save({ add: selected.value })) leaveSelection()
}

async function blockDay(day: string): Promise<void> {
  if (await save({ add: [day] })) sheetDay.value = null
}

async function unblockDay(day: string): Promise<void> {
  if (await save({ remove: [day] })) sheetDay.value = null
}

// --- Version ordinateur (à partir de lg) -------------------------------------------------------

const MAX_UPCOMING_DESKTOP = 5

/** Jour affiché dans la colonne de droite ; null = aujourd'hui. */
const selectedDay = ref<string | null>(null)
const effectiveToday = computed(() => today.value ?? businessTodayIso())
const detailDay = computed(() => selectedDay.value ?? effectiveToday.value)
const detailEvents = computed(() => eventsOnDay(events.value, detailDay.value))
const detailBlocked = computed(() => unavailableDays.value.includes(detailDay.value))
const desktopUpcoming = computed(() => filterEvents(upcoming.value, filters.value).slice(0, MAX_UPCOMING_DESKTOP))

const weekDay = computed(() => weekAnchor.value ?? monthStart(month.value))
const toolbarTitle = computed(() => (view.value === 'week' ? formatWeekTitle(weekDay.value) : formatMonthTitle(month.value)))

function visibleRange(): [string, string] {
  return view.value === 'week'
    ? [weekDay.value, addDays(weekDay.value, 6)]
    : [monthStart(month.value), monthEnd(month.value)]
}

/** Garde le jour du panneau de droite dans la période affichée (sinon aujourd'hui, sinon le premier jour). */
function syncDetailDay(): void {
  const [first, last] = visibleRange()
  if (detailDay.value >= first && detailDay.value <= last) return
  const now = effectiveToday.value
  selectedDay.value = now >= first && now <= last ? now : first
}

async function navigate(delta: number): Promise<void> {
  if (view.value === 'week') await showWeek(addDays(weekDay.value, 7 * delta))
  else if (delta < 0) await previousMonth()
  else await nextMonth()
  syncDetailDay()
}

async function goToToday(): Promise<void> {
  selectedDay.value = effectiveToday.value
  if (view.value === 'week') await showWeek(effectiveToday.value)
  else await showMonth(effectiveToday.value)
}

async function changeView(next: AgendaView): Promise<void> {
  if (next === view.value) return
  const anchor = detailDay.value
  view.value = next
  if (next === 'week') await showWeek(anchor)
  else if (weekAnchor.value !== null) await showMonth(anchor)
  syncDetailDay()
}

function onSelectDayDesktop(day: string): void {
  if (selectionMode.value) onSelectDay(day)
  else selectedDay.value = day
}

/** Glisser ou Maj + clic : entre en mode « Bloquer des jours » avec cette plage. */
function onSelectRange(days: string[], extend: boolean): void {
  if (days.length === 0) return
  sheetDay.value = null
  selectionMode.value = true
  selected.value = extend ? [...new Set([...selected.value, ...days])].sort() : [...days].sort()
}

async function blockDetailDay(day: string): Promise<void> {
  await save({ add: [day] })
}

async function unblockDetailDay(day: string): Promise<void> {
  await save({ remove: [day] })
}

function isDesktop(): boolean {
  return typeof window.matchMedia !== 'function' || window.matchMedia('(min-width: 1024px)').matches
}

/** Flèches = période précédente / suivante, T = aujourd'hui ; ignoré dans un champ de saisie. */
function onKeydown(event: KeyboardEvent): void {
  if (event.ctrlKey || event.metaKey || event.altKey) return
  const target = event.target as HTMLElement | null
  if (target && (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable)) return
  if (!isDesktop()) return
  if (event.key === 'ArrowLeft') void navigate(-1)
  else if (event.key === 'ArrowRight') void navigate(1)
  else if (event.key === 't' || event.key === 'T') void goToToday()
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  if (view.value === 'week') void showWeek(effectiveToday.value)
  else void load()
})
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="mx-auto max-w-xl space-y-4 lg:max-w-none" data-testid="face-agenda-page">
    <!-- Mode sélection : titre, consigne et rappel des jours non bloquables -->
    <RPanel v-if="selectionMode" data-testid="agenda-selection-header">
      <div class="p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
          <h2 class="text-[16px] font-semibold text-ink">Bloquer des jours</h2>
          <button
            type="button"
            class="-mr-1.5 -mt-1 grid size-9 place-items-center rounded-full text-ink-3 hover:bg-sidebar hover:text-ink"
            aria-label="Quitter le mode sélection"
            data-testid="agenda-selection-exit"
            @click="leaveSelection"
          >
            <X class="size-5" aria-hidden="true" />
          </button>
        </div>
        <p class="mt-1 text-dash text-ink-2">Touchez les jours où vous n'êtes pas disponible.</p>
        <p class="mt-3 rounded-lg bg-slate-100 px-3 py-2.5 text-[12.5px] text-slate-600">
          Les jours déjà engagés (booking, mission) ne peuvent pas être bloqués : passez par l'annulation ou le
          changement de date.
        </p>
      </div>
    </RPanel>

    <!-- Version téléphone -->
    <div class="space-y-4 lg:hidden">
      <RPanel>
        <div class="p-3 sm:p-4">
          <div class="mb-2 flex items-center justify-between">
            <button
              type="button"
              class="grid size-10 place-items-center rounded-full text-ink-2 hover:bg-sidebar"
              aria-label="Mois précédent"
              data-testid="agenda-prev"
              @click="previousMonth"
            >
              <ChevronLeft class="size-5" aria-hidden="true" />
            </button>
            <h2 class="text-[15px] font-semibold text-ink" aria-live="polite" data-testid="agenda-month-title">
              {{ formatMonthTitle(month) }}
            </h2>
            <button
              type="button"
              class="grid size-10 place-items-center rounded-full text-ink-2 hover:bg-sidebar"
              aria-label="Mois suivant"
              data-testid="agenda-next"
              @click="nextMonth"
            >
              <ChevronRight class="size-5" aria-hidden="true" />
            </button>
          </div>

          <div
            v-if="error"
            class="mb-3 flex items-center justify-between gap-3 rounded-lg bg-red-50 px-3 py-2 text-[13px] text-red-700"
            role="alert"
            data-testid="agenda-error"
          >
            <span>{{ error }}</span>
            <button type="button" class="shrink-0 font-semibold underline" @click="load">Réessayer</button>
          </div>

          <div :class="{ 'opacity-60': isLoading }" :aria-busy="isLoading">
            <MonthCalendar
              :month="month"
              :today="today"
              :states="dayStates"
              :selection-mode="selectionMode"
              :selected="selected"
              :horizon-end="horizonEnd"
              @select-day="onSelectDay"
            />
          </div>

          <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-[12px] text-slate-600" data-testid="agenda-legend">
            <li class="flex items-center gap-1.5">
              <span class="size-3 rounded bg-slate-200" aria-hidden="true" />Indisponible
            </li>
            <li class="flex items-center gap-1.5">
              <span class="size-3 rounded bg-teal-600" aria-hidden="true" />Booking
            </li>
            <li class="flex items-center gap-1.5">
              <span class="size-3 rounded bg-violet-700" aria-hidden="true" />Mission
            </li>
            <li class="flex items-center gap-1.5">
              <span class="size-3 rounded border-[1.5px] border-amber-500" aria-hidden="true" />Demande en attente
            </li>
          </ul>
        </div>
      </RPanel>

      <template v-if="!selectionMode">
        <button
          type="button"
          class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-weact px-4 text-sm font-semibold text-white hover:bg-weact/90"
          data-testid="agenda-block-days"
          @click="enterSelection"
        >
          <CalendarOff class="size-4" aria-hidden="true" />
          Bloquer des jours
        </button>

        <RPanel title="À venir" data-testid="agenda-upcoming">
          <p v-if="upcoming.length === 0" class="p-4 text-dash text-ink-3 sm:p-5">Rien de prévu ce mois-ci.</p>
          <ul v-else class="divide-y divide-line">
            <li v-for="event in upcoming" :key="`${event.kind}-${event.uuid}`">
              <RouterLink
                :to="eventRoute(event)"
                class="flex min-h-14 items-center gap-3 px-4 py-2.5 hover:bg-sidebar sm:px-5"
                data-testid="agenda-upcoming-item"
              >
                <span class="size-2 shrink-0 rounded-full" :class="eventDotClass(event)" aria-hidden="true" />
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-[14px] font-semibold text-ink">{{ event.title }}</span>
                  <span class="block text-[12.5px] text-ink-3">{{ formatEventWhen(event) }}</span>
                </span>
                <ChevronRight class="size-4 shrink-0 text-ink-3" aria-hidden="true" />
              </RouterLink>
            </li>
          </ul>
        </RPanel>
      </template>
    </div>

    <!-- Version ordinateur : colonnes gauche (240 px), centre, droite (300 px à partir de xl) -->
    <div
      class="hidden lg:grid lg:grid-cols-[220px_minmax(0,1fr)] lg:items-start lg:gap-4 xl:grid-cols-[220px_minmax(0,1fr)_280px]"
      data-testid="agenda-desktop"
    >
      <AgendaSidebar
        class="lg:col-start-1 lg:row-span-2 lg:row-start-1 xl:row-span-1"
        :month="month"
        :today="today"
        :events="events"
        :unavailable-days="unavailableDays"
        :filters="filters"
        :earnings="earnings"
        :selection-mode="selectionMode"
        @toggle="toggleFilter"
        @block-days="enterSelection"
      />

      <RPanel class="min-w-0 lg:col-start-2 lg:row-start-1">
        <div class="p-4">
          <AgendaToolbar
            class="mb-3"
            :title="toolbarTitle"
            :view="view"
            @previous="navigate(-1)"
            @next="navigate(1)"
            @today="goToToday"
            @update:view="changeView"
          />

          <div
            v-if="error"
            class="mb-3 flex items-center justify-between gap-3 rounded-lg bg-red-50 px-3 py-2 text-[13px] text-red-700"
            role="alert"
            data-testid="agenda-error-lg"
          >
            <span>{{ error }}</span>
            <button type="button" class="shrink-0 font-semibold underline" @click="load">Réessayer</button>
          </div>

          <div :class="{ 'opacity-60': isLoading }" :aria-busy="isLoading">
            <MonthGridLarge
              v-if="view === 'month'"
              :month="month"
              :today="today"
              :events="events"
              :unavailable-days="unavailableDays"
              :filters="filters"
              :selection-mode="selectionMode"
              :selected="selected"
              :active-day="detailDay"
              :horizon-end="horizonEnd"
              @select-day="onSelectDayDesktop"
              @select-range="onSelectRange"
            />
            <WeekView
              v-else-if="view === 'week'"
              :day="weekDay"
              :today="today"
              :events="events"
              :unavailable-days="unavailableDays"
              :filters="filters"
              :selection-mode="selectionMode"
              :selected="selected"
              :active-day="detailDay"
              :horizon-end="horizonEnd"
              @select-day="onSelectDayDesktop"
              @select-range="onSelectRange"
            />
            <AgendaListView
              v-else
              :month="month"
              :events="events"
              :unavailable-days="unavailableDays"
              :filters="filters"
            />
          </div>
        </div>
      </RPanel>

      <DayDetailPanel
        class="min-w-0 lg:col-start-2 lg:row-start-2 xl:col-start-3 xl:row-start-1"
        :day="detailDay"
        :today="today"
        :horizon-end="horizonEnd"
        :events="detailEvents"
        :blocked="detailBlocked"
        :upcoming="desktopUpcoming"
        :busy="isSaving"
        @block="blockDetailDay"
        @unblock="unblockDetailDay"
      />
    </div>

    <!-- Barre basse du mode sélection -->
    <div
      v-if="selectionMode"
      class="sticky bottom-0 z-10 -mx-1 rounded-t-xl bg-white p-3 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] ring-1 ring-line"
      data-testid="agenda-selection-bar"
    >
      <p class="text-[14px] font-semibold text-ink" data-testid="agenda-selected-count">{{ selectedLabel }}</p>
      <p v-if="selected.length > 0" class="mb-2 text-[12.5px] text-ink-3" data-testid="agenda-selected-list">
        {{ formatDayList(selected) }}
      </p>
      <button
        type="button"
        class="mt-2 flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-weact px-4 text-sm font-semibold text-white hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="selected.length === 0 || isSaving"
        data-testid="agenda-selection-submit"
        @click="submitSelection"
      >
        <Loader2 v-if="isSaving" class="size-4 animate-spin" aria-hidden="true" />
        Marquer indisponible
      </button>
    </div>

    <AgendaDaySheet
      :day="sheetDay"
      :today="today"
      :horizon-end="horizonEnd"
      :events="sheetEvents"
      :blocked="sheetBlocked"
      :busy="isSaving"
      @close="sheetDay = null"
      @block="blockDay"
      @unblock="unblockDay"
    />
  </div>
</template>
