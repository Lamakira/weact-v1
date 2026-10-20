<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Loader2, AlertCircle, CheckCircle } from 'lucide-vue-next'
import { ProducerCandidaturesSection } from '@/features/candidature/components'
import { redirectToCheckout } from '@/lib/redirectToCheckout'
import { useCheckoutRedirect } from '@/lib/useCheckoutRedirect'
import { usePaymentReturn } from '@/composables/usePaymentReturn'
import PaymentReturnBanner from '@/components/payment/PaymentReturnBanner.vue'
import { missionApi } from '@/features/mission/services/missionApi'
import { useMissionPayment } from '@/features/mission/composables'
import { useToast } from '@/composables/useToast'
import { formatApiError } from '@/services/errorFormatter'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { isUgcMission } from '@/features/mission/types'
import type { Mission } from '@/features/mission/types'

/**
 * Router and route
 */
const route = useRoute()
const router = useRouter()

/**
 * State
 */
const mission = ref<Mission | null>(null)
const isLoading = ref(true)
const error = ref<string | null>(null)
const paymentSuccessBanner = ref(false)
// FIX-19.3 — when the mission is stuck in pending_payment but the backend
// reports no trackable FedaPay transaction, show a dedicated error banner
// instead of the "Paiement en attente de confirmation..." spinner.
const paymentInitializationFailed = ref(false)
// True when the failed state comes from a still-existing Pending payment row without
// a live transaction (e.g. the canceled transaction was released server-side): the
// producer can relaunch it with the SAME selection through the resume path.
const resumablePaymentRow = ref(false)
const isResumingPayment = ref(false)
const paymentResumable = computed(
  () => paymentInitializationFailed.value && resumablePaymentRow.value,
)
// Checkout opened but not finished (abandoned tab, closed popup): the FedaPay transaction stays
// `pending` forever. While it is trackable we keep polling (a payment completed in another tab
// still flips the page) AND offer « Reprendre le paiement » (the server regenerates the checkout
// of the SAME transaction → no double charge). After PENDING_STALL_MS without confirmation the
// banner stops promising an imminent confirmation and points to the button instead.
const PENDING_STALL_MS = 60_000
const trackablePending = ref(false)
const pendingStalled = ref(false)
let pendingStallTimer: ReturnType<typeof setTimeout> | null = null
const paymentStatusUnavailable = ref(false)
// Lot B1 — échéance (ISO 8601) de paiement de la sélection, renvoyée par payment-status.
const paymentExpiresAt = ref<string | null>(null)
const isCancelSelectionDialogOpen = ref(false)
const isCancellingSelection = ref(false)
let isPageActive = true
let paymentStateRequestId = 0

/** « jeudi 17 octobre à 14h30 » (fuseau du navigateur). */
const paymentDeadlineLabel = computed(() => {
  if (!paymentExpiresAt.value) return null
  const date = new Date(paymentExpiresAt.value)
  if (Number.isNaN(date.getTime())) return null
  const day = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }).format(date)
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${day} à ${hours}h${minutes}`
})

// Lot B4 : échéance d'une place libérée (début du jour de tournage), même format que l'échéance de paiement.
const freedPlacesRefundLabel = computed(() => {
  const raw = mission.value?.freed_places_refund_at
  if (!raw) return null
  const date = new Date(raw)
  if (Number.isNaN(date.getTime())) return null
  const day = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }).format(date)
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${day} à ${hours}h${minutes}`
})

function clearTrackablePending(): void {
  trackablePending.value = false
  pendingStalled.value = false
  if (pendingStallTimer !== null) {
    clearTimeout(pendingStallTimer)
    pendingStallTimer = null
  }
}

function markTrackablePending(): void {
  trackablePending.value = true
  if (pendingStallTimer === null && !pendingStalled.value) {
    pendingStallTimer = setTimeout(() => {
      pendingStallTimer = null
      pendingStalled.value = true
    }, PENDING_STALL_MS)
  }
}

/**
 * Computed: Mission ID from route params
 */
const missionId = computed(() => route.params.id as string)

const { startPolling, stopPolling } = useMissionPayment(0, missionId.value || '')
const toast = useToast()

// Return from the same-tab FedaPay checkout (?payment_return=mission_selection|
// candidature_escrow). The return composable owns the verification: the cash-only
// pending evaluation below is skipped meanwhile (it polls the CASH payment-status
// endpoint, which is wrong for a UGC payment and raised a false « initialisation
// échouée » banner). Once the return flow is over (any outcome) the normal
// evaluation is back, so a late webhook still updates the page.
let returnFinished = false
const sectionRef = ref<InstanceType<typeof ProducerCandidaturesSection> | null>(null)
const paymentReturn = usePaymentReturn({
  kinds: ['mission_selection', 'candidature_escrow'],
  ids: () => ({ missionId: missionId.value }),
  onConfirmed: async (kind) => {
    if (kind === 'mission_selection') paymentSuccessBanner.value = true
    await fetchMission()
  },
  onFinished: (kind, outcome) => {
    returnFinished = true
    // Not confirmed (failed / timeout): re-evaluate the cash pending state now — failed
    // offers « reconfirmer votre sélection », timeout resumes the normal polling.
    if (kind === 'mission_selection' && outcome !== 'confirmed') void fetchMission()
  },
  onRetry: (kind, ids) => {
    if (kind === 'candidature_escrow' && ids.candidatureId) {
      sectionRef.value?.openCandidaturePayment(ids.candidatureId)
    } else {
      // mission_selection: the selection was consumed — re-evaluate the page so
      // the producer can re-confirm it (« reconfirmer votre sélection »).
      void fetchMission()
    }
  },
})

// Back from FedaPay (bfcache) after a cash selection: the selection was consumed
// and the mission is now pending_payment — re-evaluate the page so the producer
// can re-confirm instead of staring at a frozen state.
useCheckoutRedirect(() => {
  void fetchMission()
})

function isPaymentReturnActive(): boolean {
  return !returnFinished && (route.query.payment_return !== undefined || paymentReturn.isVerifying.value)
}

/**
 * Decide whether to start polling the payment status and show the "pending"
 * banner. Calls the payment-status endpoint first so we can distinguish a
 * genuine pending checkout (is_trackable=true → poll + show banner) from a
 * stuck-pending record with no FedaPay transaction (is_trackable=false →
 * surface the initialization-failed banner instead). (FIX-19.3 AC #1, #2.)
 */
async function evaluatePendingPaymentState(): Promise<void> {
  if (!missionId.value || !mission.value) return

  const requestId = ++paymentStateRequestId

  try {
    const response = await missionApi.getPaymentStatus(missionId.value)
    if (!isPageActive || requestId !== paymentStateRequestId || !mission.value) return

    const data = response.data
    paymentStatusUnavailable.value = false
    paymentExpiresAt.value = data.expires_at ?? null

    if (data.status === 'paid') {
      paymentSuccessBanner.value = true
      paymentInitializationFailed.value = false
      clearTrackablePending()
      stopPolling()

      if (mission.value.status === 'pending_payment' || data.mission_status !== mission.value.status) {
        await fetchMission()
      }

      return
    }

    if (data.mission_status !== mission.value.status) {
      paymentInitializationFailed.value = false
      clearTrackablePending()
      stopPolling()
      await fetchMission()
      return
    }

    resumablePaymentRow.value = false

    if (data.is_trackable) {
      paymentInitializationFailed.value = false
      paymentStatusUnavailable.value = false
      markTrackablePending()
      startPolling(missionId.value, async () => {
        if (!isPageActive) return
        paymentSuccessBanner.value = true
        await fetchMission()
      })
    } else {
      // No trackable transaction to poll → do NOT start polling and do NOT
      // render the "Paiement en attente de confirmation..." banner. Tell the
      // producer the initialization failed and invite a retry. This covers
      // every non-trackable payment state on a pending_payment mission
      // (pending-without-transaction, failed, refunded, or missing row) — the
      // paid / mission_status-changed branches above already returned.
      paymentInitializationFailed.value = data.mission_status === 'pending_payment'
      resumablePaymentRow.value =
        data.has_payment === true && data.status === 'pending' && data.mission_status === 'pending_payment'
      paymentStatusUnavailable.value = false
      clearTrackablePending()
      stopPolling()
    }
  } catch (err: unknown) {
    if (!isPageActive || requestId !== paymentStateRequestId) return

    console.error('Failed to fetch payment status:', err)
    paymentInitializationFailed.value = false
    paymentStatusUnavailable.value = true
    clearTrackablePending()
    stopPolling()
  }
}

/**
 * Fetch mission details for the header.
 * Evaluates whether the mission payment is trackable before auto-starting
 * polling.
 */
async function fetchMission(): Promise<void> {
  isLoading.value = true
  error.value = null

  if (!missionId.value) {
    error.value = 'ID de mission invalide'
    isLoading.value = false
    return
  }

  try {
    const response = await missionApi.getMission(missionId.value)
    if (!isPageActive) return

    mission.value = response.data

    if (mission.value.status === 'pending_payment' && !isPaymentReturnActive()) {
      await evaluatePendingPaymentState()
    } else {
      paymentInitializationFailed.value = false
      paymentStatusUnavailable.value = false
      paymentExpiresAt.value = null
      clearTrackablePending()
      stopPolling()
    }
  } catch (err: unknown) {
    if (!isPageActive) return

    console.error('Failed to fetch mission:', err)
    error.value = 'Impossible de charger les informations de la mission'
    paymentInitializationFailed.value = false
    paymentStatusUnavailable.value = false
    clearTrackablePending()
    stopPolling()
  } finally {
    if (isPageActive) {
      isLoading.value = false
    }
  }
}

/**
 * Navigate back to missions list
 */
function goBack(): void {
  router.push({ name: 'producer-missions' })
}

/**
 * Handle checkout URL from selection confirmation.
 * Redirects to FedaPay checkout — FedaPay webhook handles the rest.
 */
function handleSelectionConfirmed(checkoutUrl: string): void {
  redirectToCheckout(checkoutUrl)
}

/**
 * « Relancer le paiement »: the payment row exists but has no live transaction. The
 * server-side resume path (confirm-selection with NO candidature ids on a
 * pending_payment mission) keeps the same selection and creates a fresh checkout.
 */
async function handleResumePayment(): Promise<void> {
  if (isResumingPayment.value || !missionId.value) return

  isResumingPayment.value = true
  try {
    const response = await missionApi.confirmSelection(missionId.value, [])
    redirectToCheckout(response.data.checkout_url)
  } catch (err: unknown) {
    console.error('Failed to resume mission payment:', err)
    toast.error('Le paiement n\'a pas pu être relancé. Veuillez réessayer.')
    await fetchMission()
  } finally {
    isResumingPayment.value = false
  }
}

/**
 * « Modifier la sélection » : annule le paiement en attente (le serveur vérifie FedaPay avant),
 * la mission repasse en published et le Producteur peut choisir d'autres Faces.
 */
async function handleCancelSelection(): Promise<void> {
  if (isCancellingSelection.value || !missionId.value) return

  isCancellingSelection.value = true
  try {
    const response = await missionApi.cancelSelection(missionId.value)
    isCancelSelectionDialogOpen.value = false
    toast.success(response.message)
  } catch (err: unknown) {
    console.error('Failed to cancel mission selection:', err)
    isCancelSelectionDialogOpen.value = false
    toast.error(formatApiError(err, 'La sélection n\'a pas pu être annulée. Veuillez réessayer.'))
  } finally {
    isCancellingSelection.value = false
  }
  // Succès comme erreur (ex. paiement confirmé entre-temps) : on relit l'état réel de la page.
  await fetchMission()
}

/**
 * Handle a failed selection/initiation attempt.
 * We re-fetch the mission so the UI reflects the post-compensation state
 * (mission back to published, or stuck pending_payment with is_trackable
 * false). (FIX-19.3.)
 */
async function handleSelectionFailed(message: string): Promise<void> {
  toast.error(message)
  await fetchMission()
}

/**
 * Lifecycle
 */
onMounted(() => {
  isPageActive = true
  void fetchMission().then(() => {
    if (route.query.payment_return !== undefined) void paymentReturn.start()
  })
})

onUnmounted(() => {
  isPageActive = false
  paymentStateRequestId += 1
  clearTrackablePending()
  stopPolling()
})
</script>

<template>
  <div class="min-h-screen bg-background pb-8">
    <!-- Header Section -->
    <header class="border-b border-border bg-card">
      <div class="container mx-auto px-4 py-6 sm:px-6">
        <!-- Back Button -->
        <button
          type="button"
          class="mb-4 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          Retour aux missions
        </button>

        <!-- Loading State for Header -->
        <div v-if="isLoading" class="flex items-center gap-3">
          <Loader2 class="h-5 w-5 animate-spin text-primary" />
          <span class="text-muted-foreground">Chargement...</span>
        </div>

        <!-- Error State for Header -->
        <div v-else-if="error" class="flex items-center gap-3 text-destructive">
          <AlertCircle class="h-5 w-5" />
          <span>{{ error }}</span>
        </div>

        <!-- Mission Header -->
        <template v-else-if="mission">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold text-foreground sm:text-3xl">
              {{ mission.titre }}
            </h1>
            <span
              class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
              :class="{
                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300': mission.status === 'draft',
                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': mission.status === 'published',
                'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400': mission.status === 'pending_payment',
                'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': mission.status === 'closed',
                'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-400': mission.status === 'pending_attendance_validation',
                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400': mission.status === 'completed',
              }"
              data-testid="mission-status-pill"
            >
              {{ mission.display_status_label ?? mission.status_label }}
            </span>
          </div>
          <p class="mt-1 text-muted-foreground">
            Candidatures reçues pour cette mission
          </p>
          <PaymentReturnBanner
            class="mt-3"
            :state="paymentReturn.state.value"
            @retry="paymentReturn.retry"
            @dismiss="paymentReturn.dismiss"
          />
          <!-- Payment success banner -->
          <div
            v-if="paymentSuccessBanner"
            data-testid="mission-payment-success-banner"
            role="status"
            aria-live="polite"
            class="mt-3 flex items-center gap-2 rounded-lg bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-400"
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
            v-else-if="mission.status === 'pending_payment' && paymentInitializationFailed && paymentReturn.state.value !== 'failed'"
            data-testid="mission-payment-init-failed-banner"
            role="alert"
            aria-live="assertive"
            class="mt-3 flex items-start gap-2 rounded-lg bg-red-50 px-4 py-2.5 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400"
          >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            <span>
              {{ paymentResumable
                ? 'Votre paiement n\'a pas abouti. Vous pouvez le relancer avec la même sélection.'
                : 'L\'initialisation du paiement a échoué. Veuillez réessayer en reconfirmant votre sélection.' }}
            </span>
          </div>
          <div
            v-else-if="mission.status === 'pending_payment' && paymentStatusUnavailable"
            data-testid="mission-payment-status-unavailable-banner"
            role="alert"
            aria-live="assertive"
            class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-400"
          >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            <span>
              Impossible de vérifier le statut du paiement pour le moment. Veuillez actualiser la page dans un instant.
            </span>
          </div>
          <!-- Pending payment banner (only when we have a trackable transaction to poll) -->
          <div
            v-else-if="mission.status === 'pending_payment' && paymentReturn.state.value === 'idle'"
            data-testid="mission-payment-pending-banner"
            role="status"
            aria-live="polite"
            class="mt-3 flex items-center gap-2 rounded-lg bg-orange-50 px-4 py-2.5 text-sm text-orange-800 dark:bg-orange-900/20 dark:text-orange-400"
          >
            <Loader2 class="h-4 w-4 shrink-0 animate-spin" />
            {{ pendingStalled
              ? 'Paiement non finalisé ? Reprenez-le ci-dessous.'
              : 'Paiement en attente de confirmation...' }}
          </div>
          <!--
            Resume the payment with the same selection (shown whichever banner is up):
            « Relancer » = dead transaction, « Reprendre » = live pending transaction whose
            checkout the producer abandoned (polling keeps running underneath).
          -->
          <button
            v-if="mission.status === 'pending_payment' && (paymentResumable || (trackablePending && paymentReturn.state.value === 'idle'))"
            type="button"
            data-testid="mission-payment-resume-btn"
            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-60"
            :disabled="isResumingPayment"
            @click="handleResumePayment"
          >
            <Loader2 v-if="isResumingPayment" class="h-4 w-4 animate-spin" />
            {{ paymentResumable ? 'Relancer le paiement' : 'Reprendre le paiement' }}
          </button>
          <!-- Lot B1 — change de sélection : annule le paiement en attente. -->
          <button
            v-if="mission.status === 'pending_payment'"
            type="button"
            data-testid="mission-payment-cancel-selection-btn"
            class="mt-3 ml-0 inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-foreground transition-colors hover:bg-muted disabled:opacity-60 sm:ml-2"
            :disabled="isCancellingSelection"
            @click="isCancelSelectionDialogOpen = true"
          >
            <Loader2 v-if="isCancellingSelection" class="h-4 w-4 animate-spin" />
            Modifier la sélection
          </button>
          <!-- Lot B4 : place libérée (Face non confirmée) — remplaçante ou remboursement -->
          <div
            v-if="(mission.freed_places_count ?? 0) > 0"
            data-testid="mission-freed-place-banner"
            role="status"
            class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-400"
          >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            <span v-if="mission.can_replace_freed_place">
              {{ mission.freed_places_count === 1 ? 'Une place s\'est libérée' : `${mission.freed_places_count} places se sont libérées` }} :
              proposez-la à une Face non retenue ci-dessous, sans frais supplémentaires<template v-if="freedPlacesRefundLabel">, avant le {{ freedPlacesRefundLabel }}</template>.
              Sans remplaçante confirmée à cette date, le budget de la place est crédité sur votre wallet.
            </span>
            <span v-else>
              Place libérée sans remplaçante : son budget va être crédité sur votre wallet.
            </span>
          </div>
          <p
            v-if="mission.status === 'pending_payment' && paymentDeadlineLabel"
            data-testid="mission-payment-deadline"
            class="mt-3 text-sm text-muted-foreground"
          >
            À payer avant le {{ paymentDeadlineLabel }}. Sans paiement, la sélection sera annulée.
          </p>
        </template>
      </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto mt-6 px-4 sm:px-6">
      <!-- Show section only when we have the mission ID -->
      <ProducerCandidaturesSection
        v-if="!isLoading && !error && mission"
        ref="sectionRef"
        :mission-id="missionId"
        :mission-budget="mission.budget"
        :mission-status="mission.status"
        :nombre-faces-voulu="mission.nombre_faces_voulu"
        :allow-retry-selection="mission.status === 'pending_payment' && paymentInitializationFailed && !paymentResumable"
        :is-ugc-mission="isUgcMission(mission)"
        :ugc-compensation-type="mission.type_compensation"
        :ugc-product-name="mission.nom_produit"
        :mission-montant-remuneration="mission.montant_remuneration"
        :can-replace-freed-place="mission.can_replace_freed_place === true"
        @freed-place-replaced="fetchMission"
        @selection-confirmed="handleSelectionConfirmed"
        @selection-failed="handleSelectionFailed"
      />
    </main>

    <ConfirmModal
      :is-open="isCancelSelectionDialogOpen"
      title="Annuler le paiement en cours ?"
      message="La sélection actuelle sera annulée et vous pourrez choisir d'autres Faces. Si un paiement aboutit malgré tout, son montant sera crédité sur votre wallet WeAct."
      confirm-text="Annuler le paiement"
      cancel-text="Retour"
      variant="warning"
      :confirm-disabled="isCancellingSelection"
      @confirm="handleCancelSelection"
      @cancel="isCancelSelectionDialogOpen = false"
    />
  </div>
</template>
