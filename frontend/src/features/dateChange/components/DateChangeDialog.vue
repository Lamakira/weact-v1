<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Calendar, CalendarDays, Check, Loader2 } from 'lucide-vue-next'
import { FloatingField, FloatingSelect } from '@/components/ui/form'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'
import type { Booking } from '@/features/booking/types'
import type { Mission } from '@/features/mission/types'
import { useDateChangeActions } from '../composables/useDateChangeActions'
import { DATE_CHANGE_REASONS, type DateChangeReason } from '../types'
import {
  RESPONSE_WINDOW_HOURS,
  formatDayLong,
  formatDayWithYear,
  formatLimit,
  minNewDateIso,
  responseLimitPreview,
} from '../utils/dateChangeFormat'

/**
 * Fenêtre commune aux deux parcours : le Producteur déplace une mission payée (« mission »),
 * l'une des parties propose une autre date pour un booking (« booking »). La fenêtre fait
 * l'appel et affiche les erreurs 422 du serveur ; la page reçoit le résultat par émission.
 */
const props = withDefaults(
  defineProps<{
    isOpen: boolean
    kind: 'mission' | 'booking'
    /** uuid de la mission ou du booking. */
    targetId: string
    /** Date actuelle du tournage (AAAA-MM-JJ ou ISO). */
    currentDate: string | null
    /** Demandes déjà faites, retirées comprises. */
    used: number
    max: number
    /** Dernière nouvelle date acceptée (AAAA-MM-JJ), fournie par le serveur. */
    maxDate?: string | null
    /** Mission : Faces engagées qui doivent confirmer. */
    facesToConfirm?: number
    /** Booking : nom de l'autre partie. */
    otherPartyName?: string
  }>(),
  { maxDate: null, facesToConfirm: 0, otherPartyName: 'L’autre partie' },
)

const emit = defineEmits<{
  missionChanged: [mission: Mission, message?: string]
  bookingProposed: [booking: Booking, message?: string]
  cancel: []
}>()

const { isSubmitting, error, clearError, proposeMission, proposeBooking } = useDateChangeActions()

const newDate = ref('')
const reason = ref<DateChangeReason | ''>('')
const now = ref(new Date())

const isMission = computed(() => props.kind === 'mission')
const currentDay = computed(() => (props.currentDate ?? '').slice(0, 10))
const minDate = computed(() => minNewDateIso(now.value))
const reasonOptions = DATE_CHANGE_REASONS.map((option) => ({ ...option }))

const dateError = computed<string | undefined>(() => {
  if (!newDate.value) return undefined
  if (newDate.value === currentDay.value) return 'Choisissez une date différente de la date actuelle.'
  if (props.maxDate && newDate.value > props.maxDate) {
    return 'La nouvelle date ne peut pas dépasser de plus de 30 jours la date actuelle.'
  }
  if (newDate.value < minDate.value) {
    return `Choisissez une date à partir du ${formatDayLong(minDate.value)} : le tournage doit commencer 12 h au moins après votre demande.`
  }
  return undefined
})

const canSubmit = computed(
  () => newDate.value !== '' && reason.value !== '' && dateError.value === undefined && !isSubmitting.value,
)

const limit = computed(() =>
  newDate.value && !dateError.value
    ? formatLimit(responseLimitPreview(props.kind, { now: now.value, newDate: newDate.value, oldDate: currentDay.value }))
    : '',
)

const counter = computed(() =>
  `${isMission.value ? 'Changement' : 'Proposition'} ${Math.min(props.used + 1, props.max)} sur ${props.max}`,
)

const title = computed(() => (isMission.value ? 'Changer la date du tournage' : 'Proposer une autre date'))
const subtitle = computed(() =>
  isMission.value
    ? 'La nouvelle date s’applique tout de suite.'
    : `La date change seulement si ${props.otherPartyName} accepte.`,
)
const submitLabel = computed(() => (isMission.value ? 'Déplacer le tournage' : 'Envoyer la proposition'))

const facesLine = computed(() => {
  const n = props.facesToConfirm
  return n === 1 ? '1 Face engagée a' : `${n} Faces engagées ont`
})

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      newDate.value = ''
      reason.value = ''
      now.value = new Date()
      clearError()
    }
  },
)

useDismissOnDeactivate(() => props.isOpen, () => emit('cancel'))

async function handleSubmit(): Promise<void> {
  if (!canSubmit.value || reason.value === '') return
  const payload = { new_date: newDate.value, reason: reason.value }
  if (isMission.value) {
    const result = await proposeMission(props.targetId, payload)
    if (result) emit('missionChanged', result.data, result.message)
  } else {
    const result = await proposeBooking(props.targetId, payload)
    if (result) emit('bookingProposed', result.data, result.message)
  }
}

function handleClose(): void {
  if (isSubmitting.value) return
  emit('cancel')
}

function handleKeydown(e: KeyboardEvent): void {
  // Échap referme d'abord la liste de motifs ouverte, pas la fenêtre.
  if (document.querySelector('[data-slot="select-content"]')) return
  if (e.key === 'Escape' && props.isOpen) handleClose()
}

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
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        data-testid="date-change-dialog"
        @click.self="handleClose"
      >
        <div
          class="max-h-[calc(100dvh-2rem)] w-full max-w-md overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 shadow-xl sm:p-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="date-change-title"
        >
          <div class="mb-4 flex items-start gap-3">
            <div class="rounded-full bg-weact-50 p-2 text-weact">
              <CalendarDays class="h-5 w-5" aria-hidden="true" />
            </div>
            <div class="min-w-0">
              <h3 id="date-change-title" class="text-lg font-semibold text-gray-900">{{ title }}</h3>
              <p class="mt-1 text-sm text-gray-500" data-testid="date-change-subtitle">{{ subtitle }}</p>
            </div>
          </div>

          <div class="mb-4 grid gap-4">
            <div>
              <span class="mb-1 block text-[12.5px] font-medium text-gray-600">Date actuelle</span>
              <div
                class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-600"
                data-testid="date-change-current"
              >
                {{ formatDayWithYear(currentDay) }}
              </div>
            </div>

            <FloatingField
              id="date-change-new-date"
              v-model="newDate"
              type="date"
              label="Nouvelle date"
              :min="minDate"
              :max="maxDate ?? undefined"
              :icon="Calendar"
              :error="dateError"
              required
              data-testid="date-change-new-date"
            />

            <FloatingSelect
              id="date-change-reason"
              :model-value="reason"
              label="Motif"
              :options="reasonOptions"
              required
              data-testid="date-change-reason"
              @update:model-value="reason = $event as DateChangeReason | ''"
            />
          </div>

          <ul class="mb-4 grid gap-2 text-[13.5px] text-gray-800" data-testid="date-change-effects">
            <template v-if="isMission">
              <li v-if="facesToConfirm > 0" class="flex gap-2" data-testid="date-change-effect-confirm">
                <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-weact" aria-hidden="true" />
                <span v-if="limit">{{ facesLine }} <b>jusqu’au {{ limit }}</b> pour confirmer.</span>
                <span v-else>{{ facesLine }} {{ RESPONSE_WINDOW_HOURS }} h au plus pour confirmer.</span>
              </li>
              <li class="flex gap-2">
                <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-weact" aria-hidden="true" />
                <span>Une Face qui refuse ou ne répond pas quitte la mission, sans pénalité.</span>
              </li>
              <li class="flex gap-2" data-testid="date-change-effect-refund">
                <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-weact" aria-hidden="true" />
                <span>Vous pourrez donner sa place à un autre candidat. Sinon, le budget de la place vous revient.</span>
              </li>
            </template>
            <template v-else>
              <li class="flex gap-2" data-testid="date-change-effect-confirm">
                <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-weact" aria-hidden="true" />
                <span v-if="limit">{{ otherPartyName }} a <b>jusqu’au {{ limit }}</b> pour répondre.</span>
                <span v-else>{{ otherPartyName }} a {{ RESPONSE_WINDOW_HOURS }} h au plus pour répondre.</span>
              </li>
              <li class="flex gap-2">
                <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-weact" aria-hidden="true" />
                <span>Sans accord de sa part, le tournage reste le {{ formatDayLong(currentDay) }}.</span>
              </li>
              <li class="flex gap-2">
                <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-weact" aria-hidden="true" />
                <span>Le montant du booking ne change pas.</span>
              </li>
            </template>
          </ul>

          <div
            v-if="isMission"
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[13.5px] text-amber-800"
            data-testid="date-change-fees-warning"
          >
            <b class="block font-semibold text-amber-900">Frais de service non remboursés</b>
            WeAct garde ses 10 % sur la place remboursée.
          </div>

          <p class="mb-3 text-[12.5px] text-gray-500" data-testid="date-change-counter">{{ counter }}</p>

          <p v-if="error" class="mb-3 text-sm text-red-600" role="alert" data-testid="date-change-error">{{ error }}</p>

          <div class="flex flex-wrap items-center justify-end gap-3">
            <button
              type="button"
              class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isSubmitting"
              data-testid="date-change-back"
              @click="handleClose"
            >
              Retour
            </button>
            <button
              type="button"
              class="flex-1 rounded-lg bg-weact px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="!canSubmit"
              data-testid="date-change-submit"
              @click="handleSubmit"
            >
              <Loader2 v-if="isSubmitting" class="mr-1 inline h-4 w-4 animate-spin" aria-hidden="true" />
              {{ submitLabel }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
