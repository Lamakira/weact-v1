<script setup lang="ts">
import { AlertCircle, CheckCircle, Loader2 } from 'lucide-vue-next'
import PaymentReturnBanner from '@/components/payment/PaymentReturnBanner.vue'
import type { PaymentReturnState } from '@/composables/usePaymentReturn'
import type { Mission } from '../types'

/**
 * Bandeaux de paiement de la page détail mission (repris de l'ancienne page
 * « Candidatures ») : retour du checkout FedaPay, paiement confirmé, initialisation
 * échouée (FIX-19.3), statut indisponible, paiement en attente, place libérée (lot B4)
 * et échéance de paiement (lot B1). Purement présentationnel : l'état vient de
 * `useMissionPaymentFlow`.
 */
const props = defineProps<{
  mission: Mission
  returnState: PaymentReturnState
  paymentSuccess: boolean
  initializationFailed: boolean
  resumable: boolean
  statusUnavailable: boolean
  pendingStalled: boolean
  deadlineLabel: string | null
  freedPlacesRefundLabel: string | null
}>()

const emit = defineEmits<{
  retry: []
  dismiss: []
}>()
</script>

<template>
  <div class="grid gap-3">
    <PaymentReturnBanner :state="props.returnState" @retry="emit('retry')" @dismiss="emit('dismiss')" />
    <!-- Payment success banner -->
    <div
      v-if="paymentSuccess"
      data-testid="mission-payment-success-banner"
      role="status"
      aria-live="polite"
      class="flex items-center gap-2 rounded-lg bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-400"
    >
      <CheckCircle class="h-4 w-4 shrink-0" />
      Paiement confirmé : les Faces sélectionnées doivent maintenant confirmer leur participation.
    </div>
    <!--
      Payment initialization failed banner (FIX-19.3 AC #4).
      Surfaced when the mission is flagged pending_payment but the
      backend reports no trackable FedaPay transaction, so we must NOT
      show the spinner-style "pending confirmation" banner.
    -->
    <div
      v-else-if="mission.status === 'pending_payment' && initializationFailed && returnState !== 'failed'"
      data-testid="mission-payment-init-failed-banner"
      role="alert"
      aria-live="assertive"
      class="flex items-start gap-2 rounded-lg bg-red-50 px-4 py-2.5 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400"
    >
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
      <span>
        {{ resumable
          ? 'Votre paiement n\'a pas abouti. Vous pouvez le relancer avec la même sélection.'
          : 'L\'initialisation du paiement a échoué. Veuillez réessayer en reconfirmant votre sélection.' }}
      </span>
    </div>
    <div
      v-else-if="mission.status === 'pending_payment' && statusUnavailable"
      data-testid="mission-payment-status-unavailable-banner"
      role="alert"
      aria-live="assertive"
      class="flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-400"
    >
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
      <span>
        Impossible de vérifier le statut du paiement pour le moment. Veuillez actualiser la page dans un instant.
      </span>
    </div>
    <!-- Pending payment banner (only when we have a trackable transaction to poll) -->
    <div
      v-else-if="mission.status === 'pending_payment' && returnState === 'idle'"
      data-testid="mission-payment-pending-banner"
      role="status"
      aria-live="polite"
      class="flex items-center gap-2 rounded-lg bg-orange-50 px-4 py-2.5 text-sm text-orange-800 dark:bg-orange-900/20 dark:text-orange-400"
    >
      <Loader2 class="h-4 w-4 shrink-0 animate-spin" />
      {{ pendingStalled
        ? 'Paiement non finalisé ? Reprenez-le avec le bouton d\'action de la page.'
        : 'Paiement en attente de confirmation...' }}
    </div>
    <!-- Lot B4 : place libérée (Face non confirmée) — remplaçante ou remboursement -->
    <div
      v-if="(mission.freed_places_count ?? 0) > 0"
      data-testid="mission-freed-place-banner"
      role="status"
      class="flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-400"
    >
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
      <span v-if="mission.can_replace_freed_place">
        {{ mission.freed_places_count === 1 ? 'Une place s\'est libérée' : `${mission.freed_places_count} places se sont libérées` }} :
        proposez-la à une Face non retenue dans l'onglet Candidatures, sans frais supplémentaires<template v-if="freedPlacesRefundLabel">, avant le {{ freedPlacesRefundLabel }}</template>.
        Sans remplaçante confirmée à cette date, le budget de la place est crédité sur votre wallet.
      </span>
      <span v-else>
        Place libérée sans remplaçante : son budget va être crédité sur votre wallet.
      </span>
    </div>
    <p
      v-if="mission.status === 'pending_payment' && deadlineLabel"
      data-testid="mission-payment-deadline"
      class="text-sm text-muted-foreground"
    >
      À payer avant le {{ deadlineLabel }}. Sans paiement, la sélection sera annulée.
    </p>
  </div>
</template>
