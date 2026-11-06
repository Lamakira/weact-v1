<script setup lang="ts">
import { computed } from 'vue'
import { ArrowRight, Clock } from 'lucide-vue-next'
import type { BookingDateChangeLatest, BookingDateDecision } from '../types'
import { formatDayCompact, formatDayLong, formatDayMonth, formatTimeLeft } from '../utils/dateChangeFormat'

/**
 * Bloc « changement de date » de la page booking, au-dessus de « Et ensuite ? » (les deux rôles).
 * Destinataire : accepter ou garder l'ancienne date. Auteur : attente, décompte, retrait.
 * Résultats : acceptée, ancienne date gardée (le Producteur peut alors annuler, si la page le permet).
 */
const props = defineProps<{
  latest: BookingDateChangeLatest
  isFace: boolean
  /** Nom de l'autre partie. */
  otherPartyName: string
  /** Horloge de la page (ms). */
  now: number
  busy?: boolean
  /** Le Producteur peut annuler ce booking (action d'annulation existante de la page). */
  canCancel?: boolean
  /** Booking payé : l'annulation rembourse 90 % du montant. */
  isPaid?: boolean
}>()

// Élision devant une voyelle : « d’Élise », « de Koffi ».
const ofOtherParty = computed(() =>
  /^[aàâeéèêiîoôuûyh]/i.test(props.otherPartyName) ? `d’${props.otherPartyName}` : `de ${props.otherPartyName}`,
)

const emit = defineEmits<{
  respond: [decision: BookingDateDecision]
  withdraw: []
  cancelBooking: []
}>()

type View = 'waiting' | 'incoming' | 'late' | 'accepted' | 'kept' | 'hidden'

const view = computed<View>(() => {
  const l = props.latest
  if (l.status === 'pending') {
    if (l.proposed_by_me) return 'waiting'
    return l.can_respond ? 'incoming' : 'late'
  }
  if (l.status === 'accepted') return 'accepted'
  if (l.status === 'refused' || l.status === 'expired') return 'kept'
  return 'hidden'
})

const timeLeft = computed(() => formatTimeLeft(props.latest.respond_by, new Date(props.now)))
const oldLong = computed(() => formatDayLong(props.latest.old_date))
const newLong = computed(() => formatDayLong(props.latest.new_date))
const reasonText = computed(() => {
  const label = props.latest.reason_label
  return label ? label.charAt(0).toLowerCase() + label.slice(1) : ''
})
const proposerLabel = computed(() => (props.isFace ? 'Le Producteur' : 'La Face'))
const notifiedLabel = computed(() => (props.isFace ? 'Le Producteur est prévenu' : 'La Face est prévenue'))
const showCancel = computed(() => view.value === 'kept' && !props.isFace && props.canCancel === true)
</script>

<template>
  <section
    v-if="view !== 'hidden'"
    class="grid min-w-0 gap-2.5 rounded-[10px] border border-weact bg-weact-50 p-4"
    aria-label="Changement de date"
    data-testid="booking-date-change"
    :data-view="view"
  >
    <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-weact">
      <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
      Et ensuite ?
    </span>

    <!-- Résultat : acceptée -->
    <template v-if="view === 'accepted'">
      <template v-if="latest.proposed_by_me">
        <h2 class="text-base font-bold text-balance text-gray-900" data-testid="booking-date-change-title">
          {{ otherPartyName }} accepte le {{ newLong }}
        </h2>
        <p class="text-sm text-gray-800">La date du booking est mise à jour. Les rappels et les délais partent de cette date.</p>
      </template>
      <template v-else>
        <h2 class="text-base font-bold text-balance text-gray-900" data-testid="booking-date-change-title">
          Le tournage passe au {{ newLong }}
        </h2>
        <p class="text-sm text-gray-800">{{ notifiedLabel }}. Vous recevrez un rappel la veille.</p>
      </template>
    </template>

    <!-- Résultat : ancienne date gardée -->
    <template v-else-if="view === 'kept'">
      <h2 class="text-base font-bold text-balance text-gray-900" data-testid="booking-date-change-title">
        Le tournage reste le {{ oldLong }}
      </h2>
      <p v-if="latest.proposed_by_me" class="text-sm text-gray-800" data-testid="booking-date-change-text">
        {{ otherPartyName }} n’a pas accepté la nouvelle date.
        <template v-if="showCancel">
          Si vous ne pouvez vraiment pas le {{ formatDayMonth(latest.old_date) }}, vous pouvez annuler ce booking<template v-if="isPaid"> : 90 % du montant vous sont remboursés</template>.
        </template>
      </p>
      <p v-else class="text-sm text-gray-800" data-testid="booking-date-change-text">
        {{ notifiedLabel }}. Votre booking continue comme prévu.
        <template v-if="showCancel">
          Si vous ne pouvez vraiment pas le {{ formatDayMonth(latest.old_date) }}, vous pouvez annuler ce booking<template v-if="isPaid"> : 90 % du montant vous sont remboursés</template>.
        </template>
      </p>
      <div v-if="showCancel" class="flex flex-wrap gap-2">
        <button
          type="button"
          class="flex-1 rounded-[9px] border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
          :disabled="busy"
          data-testid="booking-date-change-cancel"
          @click="emit('cancelBooking')"
        >
          Annuler le booking
        </button>
      </div>
    </template>

    <!-- En attente : auteur, destinataire, délai écoulé -->
    <template v-else>
      <h2 class="text-base font-bold text-balance text-gray-900" data-testid="booking-date-change-title">
        <template v-if="view === 'waiting'">En attente de la réponse {{ ofOtherParty }}</template>
        <template v-else>{{ proposerLabel }} propose une autre date</template>
      </h2>

      <div
        class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2.5 rounded-lg bg-white px-3 py-2.5"
        data-testid="booking-date-change-compare"
      >
        <div class="min-w-0">
          <small class="block text-[11.5px] text-gray-500">Date actuelle</small>
          <b class="text-[15px] text-gray-900">{{ formatDayCompact(latest.old_date) }}</b>
        </div>
        <ArrowRight class="h-3.5 w-3.5 text-gray-400" aria-hidden="true" />
        <div class="min-w-0">
          <small class="block text-[11.5px] text-gray-500">Date proposée</small>
          <b class="text-[15px] text-gray-900">{{ formatDayCompact(latest.new_date) }}</b>
        </div>
      </div>

      <p v-if="view !== 'waiting' && reasonText" class="text-sm text-gray-800">Motif indiqué : {{ reasonText }}.</p>

      <div
        v-if="view === 'incoming' && latest.conflict"
        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[13.5px] text-amber-800"
        data-testid="booking-date-change-conflict"
      >
        <template v-if="isFace">
          Le {{ formatDayMonth(latest.new_date) }} est déjà pris dans votre agenda. Vous ne pouvez pas accepter cette date tant que cet engagement est actif.
        </template>
        <template v-else>
          Cette date est déjà prise dans l’agenda {{ ofOtherParty }}. Vous ne pouvez pas l’accepter tant que cet engagement est actif.
        </template>
      </div>

      <p
        v-if="timeLeft && view !== 'late'"
        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-[13.5px] font-bold text-amber-800"
        data-testid="booking-date-change-countdown"
      >
        <Clock class="h-3.5 w-3.5" aria-hidden="true" />
        {{ view === 'waiting' ? 'Il lui reste' : 'Il vous reste' }} {{ timeLeft }}
      </p>

      <div v-if="view === 'incoming'" class="flex flex-wrap gap-2">
        <button
          type="button"
          class="flex-1 rounded-[9px] border border-weact bg-weact px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
          :disabled="latest.conflict || busy"
          data-testid="booking-date-change-accept"
          @click="emit('respond', 'accept')"
        >
          J’accepte le {{ formatDayMonth(latest.new_date) }}
        </button>
        <button
          type="button"
          class="flex-1 rounded-[9px] border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
          :disabled="busy"
          data-testid="booking-date-change-refuse"
          @click="emit('respond', 'refuse')"
        >
          Je garde le {{ formatDayMonth(latest.old_date) }}
        </button>
      </div>
      <small v-if="view === 'incoming'" class="text-xs text-gray-500">Sans réponse, le tournage reste le {{ oldLong }}.</small>
      <small v-else-if="view === 'late'" class="text-xs text-gray-500" data-testid="booking-date-change-late">
        Le délai de réponse est écoulé : le tournage reste le {{ oldLong }}.
      </small>

      <div v-if="view === 'waiting' && latest.can_withdraw" class="flex flex-wrap gap-2">
        <button
          type="button"
          class="flex-1 rounded-[9px] border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
          :disabled="busy"
          data-testid="booking-date-change-withdraw"
          @click="emit('withdraw')"
        >
          Retirer ma proposition
        </button>
      </div>
    </template>
  </section>
</template>
