<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Check, Loader2, MessageCircle, XCircle, Star } from 'lucide-vue-next'
import type { FaceCandidature, MissionRating } from '../types'
import { CandidatureStatusColor } from '../types'
import { formatConfirmationDeadline } from '../utils/formatConfirmationDeadline'
import { candidatureFacts, candidatureFaceStatusLabel } from '../utils/candidaturePresentation'
import { StatusPill, tunnelStatusToPillKind } from '@/components/ugc'
import MissionAbsenceContestBlock from './MissionAbsenceContestBlock.vue'
import MissionRatingDialog from './MissionRatingDialog.vue'

/**
 * Props
 */
const props = defineProps<{
  candidature: FaceCandidature
}>()

/**
 * Emits
 */
const emit = defineEmits<{
  confirm: [candidatureId: string]
  cancel: [candidatureId: string]
  contested: []
  rated: [candidatureId: string, rating: MissionRating]
}>()

/**
 * Local state for confirm/cancel buttons
 */
const isConfirming = ref(false)
const isCancelling = ref(false)

// Notation du producteur (lot C8) : « Noter » seulement pour une candidature terminée et pas encore notée.
const ratingOpen = ref(false)
const justRated = ref(false)
const canRate = computed(
  () => props.candidature.status === 'completed' && props.candidature.can_rate === true && !justRated.value,
)

function handleRated(rating: MissionRating): void {
  justRated.value = true
  ratingOpen.value = false
  emit('rated', props.candidature.id, rating)
}

/**
 * Computed: Show confirm button for accepted candidatures
 */
const canConfirm = computed(() => props.candidature.status === 'accepted')

/**
 * Computed: Show cancel button for pending candidatures
 */
const displayStatus = computed(() => props.candidature.display_status ?? props.candidature.status)
// Libellé vu par la Face (« À confirmer par vous », « Non retenue »), sinon celui du serveur.
const displayStatusLabel = computed(() => candidatureFaceStatusLabel(props.candidature))

// Faits en libellé / valeur : jamais de ligne vide (UGC : Type, Vous recevez, Prochaine étape).
const facts = computed(() => candidatureFacts(props.candidature))

// « Mission clôturée » : pending côté base, mais plus rien à annuler ni à attendre.
// « Sélection en cours de paiement » : la Face peut encore se retirer.
const canCancel = computed(() => displayStatus.value === 'pending' || displayStatus.value === 'selection_payment_pending')

/**
 * Computed: Show chat button when candidature allows chat and has conversation
 */
const canChat = computed(() => {
  const chatStatuses = ['accepted', 'confirmed', 'in_progress', 'completed']
  return chatStatuses.includes(props.candidature.status) && props.candidature.conversation_id != null
})

/**
 * Pied de carte : une seule action principale (confirmer > retirer > noter > discuter), sinon
 * « Voir d'autres missions » quand la candidature n'a pas abouti. Le chat reste accessible en
 * second geste (icône) quand une autre action est mise en avant.
 */
const primaryAction = computed<'confirm' | 'cancel' | 'rate' | 'chat' | 'browse' | null>(() => {
  if (canConfirm.value) return 'confirm'
  if (canCancel.value) return 'cancel'
  if (canRate.value) return 'rate'
  if (canChat.value) return 'chat'
  return ['rejected', 'cancelled', 'expired'].includes(props.candidature.status) ? 'browse' : null
})
const showSecondaryChat = computed(() => canChat.value && primaryAction.value !== 'chat')

/**
 * Handle confirm button click
 */
function handleConfirm(): void {
  if (isConfirming.value) return
  isConfirming.value = true
  emit('confirm', props.candidature.id)
}

/**
 * Handle cancel button click
 */
function handleCancel(): void {
  if (isCancelling.value) return
  isCancelling.value = true
  emit('cancel', props.candidature.id)
}

/**
 * Reset confirming state (called from parent after API response)
 */
function resetConfirming(): void {
  isConfirming.value = false
}

/**
 * Reset cancelling state (called from parent after API response)
 */
function resetCancelling(): void {
  isCancelling.value = false
}

/**
 * Expose methods for parent component
 */
defineExpose({ resetConfirming, resetCancelling })

/**
 * Computed: Format created_at for display
 */
const formattedCreatedAt = computed(() => {
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(props.candidature.created_at))
})

/**
 * Computed: Status badge class
 */
const statusBadgeClass = computed(() => {
  const color = displayStatus.value === 'mission_closed'
    ? 'gray'
    : displayStatus.value === 'selection_payment_pending'
      ? 'yellow'
      : CandidatureStatusColor[props.candidature.status]
  const colorClasses: Record<string, string> = {
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    gray: 'bg-gray-100 text-gray-600 dark:bg-gray-900/30 dark:text-gray-400',
  }
  return colorClasses[color] || colorClasses.yellow
})

/**
 * Computed: Format the reconfirmation deadline for display (9-2, D-9.2.e).
 * The backend exposes `reconfirm_deadline` (accepted_at + ugc.reconfirm_window_hours)
 * already computed; the front only formats it (fr-FR, jour/mois/année + heure).
 */
const formattedReconfirmDeadline = computed(() => {
  if (!props.candidature.reconfirm_deadline) return ''
  try {
    const date = new Date(props.candidature.reconfirm_deadline)
    if (isNaN(date.getTime())) return ''
    return new Intl.DateTimeFormat('fr-FR', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  } catch {
    return ''
  }
})

/**
 * Computed: Confirmation deadline of a cash selection (lot B3) — « jeudi 19 octobre à 08h51 ».
 * Backend `confirmation_deadline` (min(accepted_at + 48 h, début du jour de tournage)) ;
 * null/absent hors candidature cash `accepted` (UGC : voir reconfirm_deadline).
 */
const formattedConfirmationDeadline = computed(() =>
  canConfirm.value ? formatConfirmationDeadline(props.candidature.confirmation_deadline) : '',
)
</script>

<template>
  <RouterLink
    :to="{ name: 'face-mission-detail', params: { id: candidature.mission.id } }"
    class="candidature-card grid content-start gap-3 rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/50 hover:shadow-md sm:p-5"
  >
    <!-- Statut -->
    <div class="flex items-center justify-between gap-2">
      <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
        :class="statusBadgeClass"
      >
        {{ displayStatusLabel }}
      </span>
    </div>

    <!-- Mission Title -->
    <h3 class="text-base font-extrabold text-foreground line-clamp-2 sm:text-lg">
      {{ candidature.mission.titre }}
    </h3>

    <!-- Faits : libellé / valeur -->
    <dl class="grid gap-1.5 text-sm" data-testid="candidature-facts">
      <div v-for="fact in facts" :key="fact.key" class="flex items-baseline gap-2" :data-testid="`candidature-fact-${fact.key}`">
        <dt class="min-w-[7.5rem] shrink-0 text-muted-foreground">{{ fact.label }}</dt>
        <dd class="min-w-0 font-semibold text-foreground">{{ fact.value }}</dd>
      </div>
    </dl>

    <!-- Confirmation deadline label (cash accepted, lot B3) -->
    <p
      v-if="formattedConfirmationDeadline"
      data-testid="confirmation-deadline-label"
      class="text-center text-xs text-amber-600 dark:text-amber-400"
    >
      À confirmer avant le {{ formattedConfirmationDeadline }}
    </p>

    <!-- Reconfirmation deadline label (UGC accepted, 9-2, D-9.2.e) -->
    <p
      v-if="canConfirm && formattedReconfirmDeadline"
      data-testid="reconfirm-deadline-label"
      class="text-center text-xs text-amber-600 dark:text-amber-400"
    >
      Reconfirmez avant le {{ formattedReconfirmDeadline }}, sinon votre place sera libérée.
    </p>


    <!-- Tracking expédition UGC (3.4) — bloc compact informatif, l'action vit sur le détail (D-3.4.h) -->
    <div
      v-if="candidature.shipment"
      class="mt-3 flex flex-wrap items-center gap-2 border-t border-border pt-3 text-xs text-muted-foreground"
      data-testid="candidature-shipment-info"
    >
      <StatusPill :kind="tunnelStatusToPillKind(candidature.shipment.tunnel_status)">
        {{ candidature.shipment.tunnel_status_label }}
      </StatusPill>
      <span>{{ candidature.shipment.transporteur }} · {{ candidature.shipment.numero_suivi }}</span>
    </div>

    <!-- Absence déclarée par le Producteur : contestation 72 h (le clic ne navigue pas) -->
    <div
      v-if="candidature.attendance_dispute"
      class="mt-3 border-t border-border pt-3"
      @click.stop.prevent
    >
      <MissionAbsenceContestBlock
        :dispute="candidature.attendance_dispute"
        :mission-id="candidature.mission.id"
        @contested="emit('contested')"
      />
    </div>

    <!-- Pied de carte : date de candidature + une action -->
    <div class="flex items-center justify-between gap-2 border-t border-border pt-3 text-xs text-muted-foreground">
      <span>Postulé le {{ formattedCreatedAt }}</span>

      <div class="flex items-center gap-2">
        <RouterLink
          v-if="showSecondaryChat"
          :to="{ name: 'face-conversation', params: { conversationId: candidature.conversation_id } }"
          class="inline-flex size-8 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground transition-colors hover:bg-muted"
          aria-label="Discuter avec le producteur"
          title="Discuter avec le producteur"
          data-testid="candidature-chat-secondary"
          @click.stop
        >
          <MessageCircle class="h-4 w-4" aria-hidden="true" />
        </RouterLink>

        <button
          v-if="primaryAction === 'confirm'"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
          aria-label="Confirmer ma participation"
          data-testid="candidature-confirm"
          :disabled="isConfirming"
          @click.stop.prevent="handleConfirm"
        >
          <Loader2 v-if="isConfirming" class="h-4 w-4 animate-spin" />
          <Check v-else class="h-4 w-4" />
          {{ isConfirming ? 'Confirmation...' : 'Confirmer' }}
        </button>

        <button
          v-else-if="primaryAction === 'cancel'"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
          aria-label="Annuler ma candidature"
          data-testid="candidature-cancel"
          :disabled="isCancelling"
          @click.stop.prevent="handleCancel"
        >
          <Loader2 v-if="isCancelling" class="h-4 w-4 animate-spin" />
          <XCircle v-else class="h-4 w-4" />
          {{ isCancelling ? 'Annulation...' : 'Retirer' }}
        </button>

        <button
          v-else-if="primaryAction === 'rate'"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-3 py-1.5 text-sm font-medium text-foreground transition-colors hover:bg-muted"
          data-testid="candidature-rate"
          @click.stop.prevent="ratingOpen = true"
        >
          <Star class="h-4 w-4 text-amber-400" />
          Noter
        </button>

        <RouterLink
          v-else-if="primaryAction === 'chat'"
          :to="{ name: 'face-conversation', params: { conversationId: candidature.conversation_id } }"
          class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-primary/90"
          data-testid="candidature-chat"
          @click.stop
        >
          <MessageCircle class="h-4 w-4" aria-hidden="true" />
          Discuter
        </RouterLink>

        <RouterLink
          v-else-if="primaryAction === 'browse'"
          :to="{ name: 'face-missions' }"
          class="inline-flex items-center rounded-lg border border-border bg-background px-3 py-1.5 text-sm font-medium text-foreground transition-colors hover:bg-muted"
          data-testid="candidature-browse"
          @click.stop
        >
          Voir d'autres missions
        </RouterLink>
      </div>
    </div>

    <MissionRatingDialog
      :open="ratingOpen"
      :candidature-id="candidature.id"
      :producer-name="candidature.producer.display_name"
      @close="ratingOpen = false"
      @rated="handleRated"
    />
  </RouterLink>
</template>

<style scoped>
/* Entrée échelonnée (transform + opacity, ease-out fort) ; l'index vient de la page via --card-index. */
.candidature-card {
  animation: candidature-card-in 260ms cubic-bezier(0.23, 1, 0.32, 1) both;
  animation-delay: calc(min(var(--card-index, 0), 8) * 40ms);
}
@keyframes candidature-card-in {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
}
@media (prefers-reduced-motion: reduce) {
  .candidature-card {
    animation: none;
  }
}
</style>
