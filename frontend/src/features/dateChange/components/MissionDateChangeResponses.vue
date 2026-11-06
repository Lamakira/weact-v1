<script setup lang="ts">
import { computed } from 'vue'
import { Clock } from 'lucide-vue-next'
import type { ProducerDateChangeLatest, ProducerDateChangeResponse } from '../types'
import { formatDayCompact, formatInstantShort, formatTimeLeft, initialsOf } from '../utils/dateChangeFormat'

/**
 * Bloc « Réponses à la nouvelle date » de la page mission Producteur : une ligne par Face engagée
 * (pastille, décompte, résumé) et le lien « Remplacer » vers l'action de place libérée de la page.
 */
const props = defineProps<{
  latest: ProducerDateChangeLatest
  /** Une place libérée peut être proposée à un autre candidat (`mission.can_replace_freed_place`). */
  canReplace: boolean
  now: Date
}>()

const emit = defineEmits<{
  replace: []
}>()

function isFreed(response: ProducerDateChangeResponse): boolean {
  return response.status === 'withdrawn' || response.status === 'expired'
}

const total = computed(() => props.latest.responses.length)
const pendingRows = computed(() => props.latest.responses.filter((r) => r.status === 'pending'))
const freedRows = computed(() => props.latest.responses.filter(isFreed))
const received = computed(() => total.value - pendingRows.value.length)
const timeLeft = computed(() => formatTimeLeft(props.latest.respond_by, props.now))

const headline = computed(() => {
  if (pendingRows.value.length === 0) return 'Toutes les réponses sont reçues'
  return `${received.value} ${received.value > 1 ? 'réponses reçues' : 'réponse reçue'} sur ${total.value}`
})

const summaryText = computed(() => {
  const names = freedRows.value.map((r) => r.face_name)
  const parts: string[] = []
  if (names.length === 1) parts.push(`${names[0]} se retire.`)
  else if (names.length > 1) parts.push(`${names.join(', ')} se retirent.`)
  if (names.length > 0) {
    parts.push(
      props.canReplace
        ? 'Choisissez un autre candidat, sinon le budget de la place vous revient.'
        : 'Le budget de la place vous revient.',
    )
  } else if (pendingRows.value.length === 0) {
    parts.push('Toutes les Faces ont confirmé la nouvelle date.')
  } else {
    parts.push('Les Faces qui ne répondent pas avant la limite quittent la mission, sans pénalité.')
  }
  return parts.join(' ')
})

const countdown = computed(() => {
  if (!timeLeft.value || pendingRows.value.length === 0) return null
  if (pendingRows.value.length === 1) return `${pendingRows.value[0]!.face_name} a encore ${timeLeft.value}`
  return `${pendingRows.value.length} Faces ont encore ${timeLeft.value}`
})

function subline(response: ProducerDateChangeResponse): string {
  if (response.status === 'confirmed') {
    const at = formatInstantShort(response.responded_at)
    return at ? `Confirmé le ${at}` : 'A confirmé la nouvelle date'
  }
  if (response.status === 'pending') {
    const until = formatInstantShort(props.latest.respond_by)
    return until ? `Répond jusqu’au ${until}` : 'Réponse attendue'
  }
  return 'Place libérée'
}
</script>

<template>
  <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_320px]" data-testid="date-change-responses">
    <section class="min-w-0 rounded-[10px] border border-border bg-card" aria-label="Réponses à la nouvelle date">
      <div class="border-b border-border px-3.5 py-2.5">
        <h2 class="text-sm font-semibold text-foreground">Réponses à la nouvelle date</h2>
        <p class="mt-0.5 text-xs text-muted-foreground" data-testid="date-change-responses-dates">
          Le tournage passe du {{ formatDayCompact(latest.old_date) }} au {{ formatDayCompact(latest.new_date) }}.
          Motif : {{ latest.reason_label.charAt(0).toLowerCase() + latest.reason_label.slice(1) }}.
        </p>
      </div>
      <ul class="m-0 list-none p-0">
        <li
          v-for="response in latest.responses"
          :key="response.candidature_uuid"
          class="flex min-w-0 items-center gap-2.5 border-t border-border px-3.5 py-2.5 first:border-t-0"
          :data-testid="`date-change-row-${response.candidature_uuid}`"
          :data-status="response.status"
        >
          <span class="grid h-[34px] w-[34px] shrink-0 place-items-center rounded-full bg-weact-50 text-[13px] font-bold text-weact" aria-hidden="true">
            {{ initialsOf(response.face_name) }}
          </span>
          <div class="min-w-0 flex-1">
            <b class="block truncate text-sm font-semibold text-foreground">{{ response.face_name }}</b>
            <small class="block text-xs text-muted-foreground">{{ subline(response) }}</small>
          </div>
          <span
            v-if="response.status === 'confirmed'"
            class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />Confirmée
          </span>
          <span
            v-else-if="response.status === 'pending'"
            class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />En attente
          </span>
          <button
            v-else-if="canReplace"
            type="button"
            class="shrink-0 rounded-[9px] border border-border bg-card px-3 py-1.5 text-[13px] font-semibold text-foreground transition-colors hover:bg-muted"
            data-testid="date-change-replace"
            @click="emit('replace')"
          >
            Remplacer
          </button>
          <span
            v-else
            class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-semibold text-muted-foreground"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />Retirée
          </span>
        </li>
      </ul>
    </section>

    <section
      class="min-w-0 rounded-[10px] border border-weact bg-weact-50 p-4"
      aria-label="Résumé des réponses"
      data-testid="date-change-summary"
    >
      <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-weact">
        <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
        Et ensuite ?
      </span>
      <h2 class="mt-2 text-base font-bold text-balance text-foreground" data-testid="date-change-summary-headline">{{ headline }}</h2>
      <p class="mt-1.5 text-sm text-foreground" data-testid="date-change-summary-text">{{ summaryText }}</p>
      <p
        v-if="countdown"
        class="mt-2.5 inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-[13px] font-bold text-amber-800"
        data-testid="date-change-summary-countdown"
      >
        <Clock class="h-3.5 w-3.5" aria-hidden="true" />{{ countdown }}
      </p>
    </section>
  </div>
</template>
