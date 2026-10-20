import { computed, onMounted, onUnmounted, ref, type ComputedRef } from 'vue'
import { useRoute } from 'vue-router'
import { redirectToCheckout } from '@/lib/redirectToCheckout'
import { useCheckoutRedirect } from '@/lib/useCheckoutRedirect'
import { usePaymentReturn } from '@/composables/usePaymentReturn'
import { useToast } from '@/composables/useToast'
import { formatApiError } from '@/services/errorFormatter'
import { missionApi } from '../services/missionApi'
import { useMissionPayment } from './useMissionPayment'
import { formatDeadlineLabel } from '../utils/missionDetailModel'
import type { Mission } from '../types'

// Checkout opened but not finished (abandoned tab, closed popup): the FedaPay transaction stays
// `pending` forever. While it is trackable we keep polling (a payment completed in another tab
// still flips the page) AND offer « Reprendre le paiement » (the server regenerates the checkout
// of the SAME transaction → no double charge). After PENDING_STALL_MS without confirmation the
// banner stops promising an imminent confirmation and points to the button instead.
const PENDING_STALL_MS = 60_000

export interface UseMissionPaymentFlowOptions {
  /** « Réessayer le paiement » d'un règlement de candidature UGC : rouvre son overlay. */
  onRetryCandidaturePayment?: (candidatureId: string) => void
}

/**
 * État et actions de paiement d'une mission côté Producteur (page détail mission) :
 * chargement de la mission, évaluation du paiement en attente (FIX-19.3), reprise
 * (« Relancer / Reprendre le paiement »), « Modifier la sélection » (lot B1) et retour
 * du checkout FedaPay dans le même onglet. Logique reprise telle quelle de l'ancienne
 * page « Candidatures » de la mission.
 */
export function useMissionPaymentFlow(
  missionId: ComputedRef<string>,
  options: UseMissionPaymentFlowOptions = {},
) {
  const route = useRoute()
  const toast = useToast()

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
  const paymentDeadlineLabel = computed(() => formatDeadlineLabel(paymentExpiresAt.value))

  // Lot B4 : échéance d'une place libérée (début du jour de tournage), même format que l'échéance de paiement.
  const freedPlacesRefundLabel = computed(() => formatDeadlineLabel(mission.value?.freed_places_refund_at))

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

  const { startPolling, stopPolling } = useMissionPayment(0, missionId.value || '')

  // Return from the same-tab FedaPay checkout (?payment_return=mission_selection|
  // candidature_escrow). The return composable owns the verification: the cash-only
  // pending evaluation below is skipped meanwhile (it polls the CASH payment-status
  // endpoint, which is wrong for a UGC payment and raised a false « initialisation
  // échouée » banner). Once the return flow is over (any outcome) the normal
  // evaluation is back, so a late webhook still updates the page.
  let returnFinished = false
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
        options.onRetryCandidaturePayment?.(ids.candidatureId)
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
   * Fetch mission details (with the lifecycle summary).
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

  return {
    mission,
    isLoading,
    error,
    fetchMission,
    paymentReturn,
    paymentSuccessBanner,
    paymentInitializationFailed,
    paymentResumable,
    trackablePending,
    pendingStalled,
    paymentStatusUnavailable,
    paymentExpiresAt,
    paymentDeadlineLabel,
    freedPlacesRefundLabel,
    isResumingPayment,
    isCancelSelectionDialogOpen,
    isCancellingSelection,
    handleSelectionConfirmed,
    handleResumePayment,
    handleCancelSelection,
    handleSelectionFailed,
  }
}
