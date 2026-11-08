<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import type { AgendaView } from '../types'

/** Barre d'outils de l'agenda ordinateur : titre de période, navigation, « Aujourd'hui », sélecteur de vue. */
defineProps<{ title: string; view: AgendaView }>()

const emit = defineEmits<{
  previous: []
  next: []
  today: []
  'update:view': [view: AgendaView]
}>()

const VIEWS: { value: AgendaView; label: string }[] = [
  { value: 'month', label: 'Mois' },
  { value: 'week', label: 'Semaine' },
  { value: 'list', label: 'Liste' },
]
</script>

<template>
  <div class="flex flex-wrap items-center gap-2" data-testid="agenda-toolbar">
    <h2 class="mr-2 min-w-0 text-[16px] font-semibold text-ink" aria-live="polite" data-testid="agenda-toolbar-title">
      {{ title }}
    </h2>
    <button
      type="button"
      class="grid size-9 place-items-center rounded-full text-ink-2 hover:bg-sidebar"
      aria-label="Période précédente"
      data-testid="agenda-toolbar-prev"
      @click="emit('previous')"
    >
      <ChevronLeft class="size-5" aria-hidden="true" />
    </button>
    <button
      type="button"
      class="grid size-9 place-items-center rounded-full text-ink-2 hover:bg-sidebar"
      aria-label="Période suivante"
      data-testid="agenda-toolbar-next"
      @click="emit('next')"
    >
      <ChevronRight class="size-5" aria-hidden="true" />
    </button>
    <button
      type="button"
      class="min-h-9 rounded-lg border border-line px-3 text-[13px] font-medium text-ink hover:bg-sidebar"
      data-testid="agenda-toolbar-today"
      @click="emit('today')"
    >
      Aujourd'hui
    </button>

    <div class="ml-auto inline-flex rounded-lg bg-sidebar p-0.5" role="group" aria-label="Affichage">
      <button
        v-for="item in VIEWS"
        :key="item.value"
        type="button"
        class="min-h-8 rounded-md px-3 text-[13px] font-medium"
        :class="view === item.value ? 'bg-white text-ink shadow-sm' : 'text-ink-2 hover:text-ink'"
        :aria-pressed="view === item.value"
        :data-testid="`agenda-view-${item.value}`"
        @click="emit('update:view', item.value)"
      >
        {{ item.label }}
      </button>
    </div>
  </div>
</template>
