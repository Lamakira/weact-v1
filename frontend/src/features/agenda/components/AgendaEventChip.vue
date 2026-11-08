<script setup lang="ts">
import type { AgendaEvent } from '../types'
import { eventChipClass } from '../utils/agendaEvents'

/**
 * Étiquette d'événement d'une grille ordinateur. Sans texte (`withText` false) : barre pleine, pour
 * l'effet de plage continue sur plusieurs jours. Les couleurs sont portées par ce span interne.
 */
defineProps<{
  event: AgendaEvent
  withText: boolean
  /** Libellé affiché (défaut : titre). */
  text?: string
  large?: boolean
}>()
</script>

<template>
  <span
    class="block truncate rounded px-1.5 font-medium"
    :class="[eventChipClass(event), large ? 'min-h-6 py-1 text-[12.5px]' : 'min-h-5 py-0.5 text-[11.5px]']"
    :title="withText ? undefined : event.title"
    data-testid="agenda-chip"
    :data-with-text="withText ? 'true' : 'false'"
    aria-hidden="true"
  >
    <template v-if="withText">{{ text ?? event.title }}</template>
    <template v-else>&nbsp;</template>
  </span>
</template>
