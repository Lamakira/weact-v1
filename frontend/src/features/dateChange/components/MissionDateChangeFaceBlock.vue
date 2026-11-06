<script setup lang="ts">
import { computed, ref } from 'vue'
import { ArrowRight, Clock } from 'lucide-vue-next'
import type { FaceMissionDateChange, MissionDateDecision } from '../types'
import {
  formatDayCompact,
  formatDayLong,
  formatDayMonth,
  formatLimit,
  formatTimeLeft,
} from '../utils/dateChangeFormat'
import DateChangeWithdrawDialog from './DateChangeWithdrawDialog.vue'

/**
 * Bloc « Le tournage change de date » de la page mission Face, juste au-dessus de la barre d'étapes.
 * Quatre états : réponse attendue (avec le cas « date déjà prise »), confirmée, retirée.
 * Présentationnel : la page porte l'appel réseau et reçoit la décision par émission.
 */
const props = defineProps<{
  dateChange: FaceMissionDateChange
  /** Montant déjà connu de la page, affiché dans la fenêtre de retrait. */
  amount: number | null
  /** Horloge de la page (tic par minute). */
  now: Date
  busy?: boolean
}>()

const emit = defineEmits<{
  respond: [decision: MissionDateDecision]
}>()

const withdrawOpen = ref(false)

const state = computed<'waiting' | 'late' | 'confirmed' | 'left'>(() => {
  if (props.dateChange.status === 'confirmed') return 'confirmed'
  if (props.dateChange.status === 'withdrawn' || props.dateChange.status === 'expired') return 'left'
  return props.dateChange.can_respond ? 'waiting' : 'late'
})

const dayMonth = computed(() => formatDayMonth(props.dateChange.new_date))
const timeLeft = computed(() => formatTimeLeft(props.dateChange.respond_by, props.now))
const limit = computed(() => formatLimit(props.dateChange.respond_by))
const reasonText = computed(() => {
  const label = props.dateChange.reason_label
  return label ? label.charAt(0).toLowerCase() + label.slice(1) : ''
})

function confirm(): void {
  if (props.dateChange.conflict || props.busy) return
  emit('respond', 'confirm')
}

function confirmWithdraw(): void {
  withdrawOpen.value = false
  emit('respond', 'withdraw')
}
</script>

<template>
  <section
    class="mb-4 grid min-w-0 gap-2.5 rounded-[10px] border border-weact bg-weact-50 p-4"
    aria-label="Changement de date"
    data-testid="date-change-face-block"
    :data-state="state === 'waiting' && dateChange.conflict ? 'conflict' : state"
  >
    <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-weact">
      <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
      Et ensuite ?
    </span>

    <!-- Confirmée -->
    <template v-if="state === 'confirmed'">
      <h2 class="text-base font-bold text-balance text-gray-900" data-testid="date-change-face-title">Nouvelle date confirmée</h2>
      <p class="text-sm text-gray-800">
        Rendez-vous le {{ formatDayLong(dateChange.new_date) }}. Vous recevrez un rappel la veille.
      </p>
    </template>

    <!-- Retirée (par la Face ou faute de réponse) -->
    <template v-else-if="state === 'left'">
      <h2 class="text-base font-bold text-balance text-gray-900" data-testid="date-change-face-title">Vous avez quitté cette mission</h2>
      <p class="text-sm text-gray-800">Le Producteur en est informé. Aucune pénalité n’a été appliquée.</p>
    </template>

    <!-- Réponse attendue, ou délai écoulé -->
    <template v-else>
      <h2 class="text-base font-bold text-balance text-gray-900" data-testid="date-change-face-title">
        <template v-if="dateChange.conflict">Le {{ dayMonth }} est déjà pris dans votre agenda</template>
        <template v-else>Le tournage change de date</template>
      </h2>

      <div
        v-if="!dateChange.conflict"
        class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2.5 rounded-lg bg-white px-3 py-2.5"
        data-testid="date-change-compare"
      >
        <div class="min-w-0">
          <small class="block text-[11.5px] text-gray-500">Avant</small>
          <s class="text-sm text-gray-500">{{ formatDayCompact(dateChange.old_date) }}</s>
        </div>
        <ArrowRight class="h-3.5 w-3.5 text-gray-400" aria-hidden="true" />
        <div class="min-w-0">
          <small class="block text-[11.5px] text-gray-500">Nouvelle date</small>
          <b class="text-[15px] text-gray-900">{{ formatDayCompact(dateChange.new_date) }}</b>
        </div>
      </div>

      <div
        v-if="dateChange.conflict"
        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[13.5px] text-amber-800"
        data-testid="date-change-conflict"
      >
        Vous ne pouvez pas confirmer cette date tant que cet engagement est actif.
      </div>
      <p v-else-if="reasonText" class="text-sm text-gray-800">Motif indiqué par le Producteur : {{ reasonText }}.</p>

      <p
        v-if="state === 'waiting' && timeLeft"
        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-[13.5px] font-bold text-amber-800"
        data-testid="date-change-countdown"
      >
        <Clock class="h-3.5 w-3.5" aria-hidden="true" />
        Il vous reste {{ timeLeft }}
      </p>

      <div v-if="state === 'waiting'" class="flex flex-wrap gap-2" data-testid="date-change-actions">
        <button
          type="button"
          class="flex-1 rounded-[9px] border border-weact bg-weact px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
          :disabled="dateChange.conflict || busy"
          :aria-disabled="dateChange.conflict ? 'true' : undefined"
          data-testid="date-change-confirm"
          @click="confirm"
        >
          Je confirme le {{ dayMonth }}
        </button>
        <button
          type="button"
          class="flex-1 rounded-[9px] border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
          :disabled="busy"
          data-testid="date-change-withdraw"
          @click="withdrawOpen = true"
        >
          Je ne peux pas
        </button>
      </div>

      <small v-if="state === 'waiting' && !dateChange.conflict && limit" class="text-xs text-gray-500" data-testid="date-change-silence">
        Sans réponse avant le {{ limit }}, vous quittez la mission, sans pénalité.
      </small>
      <small v-else-if="state === 'late'" class="text-xs text-gray-500" data-testid="date-change-late">
        Le délai de réponse est écoulé : sans réponse, vous quittez la mission, sans pénalité.
      </small>
    </template>

    <DateChangeWithdrawDialog
      :is-open="withdrawOpen"
      :new-date="dateChange.new_date"
      :amount="amount"
      :is-submitting="busy"
      @confirm="confirmWithdraw"
      @cancel="withdrawOpen = false"
    />
  </section>
</template>
