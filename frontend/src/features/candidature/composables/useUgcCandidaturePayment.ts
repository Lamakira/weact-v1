import { onUnmounted, ref, type Ref } from 'vue'
import { candidatureApi } from '../services/candidatureApi'
import type { PaymentStatus } from '@/features/booking/types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

const POLL_INTERVAL_MS = 5000
const POLL_TIMEOUT_MS = 120000

interface UseUgcCandidaturePaymentReturn {
  isInitiating: Ref<boolean>
  paymentStatus: Ref<PaymentStatus>
  error: Ref<string | null>
  initiate: (candidatureId: string) => Promise<void>
  stopPolling: () => void
  reset: () => void
}

/**
 * Hybrid per-Face payment for a UGC candidature acceptance (ugc-8-5, D-8.5.f).
 *
 * Calque of useBookingPayment but candidature-typed: when the Producer accepts a
 * HYBRID mission candidature, accept initiates a FedaPay checkout and surfaces a
 * `checkout_url`. This composable opens that checkout in a new tab then polls the
 * self-heal payment-status endpoint (resilient to a delayed/missed webhook) until
 * the candidature is `accepted` (confirmed) or the payment fails/times out.
 *
 * Kept STRICTLY separate from useAcceptCandidature (product-only, free direct
 * accept) — the overlay only drives this one.
 */
export function useUgcCandidaturePayment(): UseUgcCandidaturePaymentReturn {
  const isInitiating = ref(false)
  const paymentStatus = ref<PaymentStatus>('idle')
  const error = ref<string | null>(null)

  let pollTimer: ReturnType<typeof setInterval> | null = null
  let pollTimeoutTimer: ReturnType<typeof setTimeout> | null = null

  function stopPolling(): void {
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
    if (pollTimeoutTimer) {
      clearTimeout(pollTimeoutTimer)
      pollTimeoutTimer = null
    }
  }

  function startPolling(candidatureId: string): void {
    pollTimer = setInterval(async () => {
      try {
        // Self-heal payment-status: re-checks FedaPay directly and settles if
        // approved — resilient to webhook delivery failures (sandbox/ngrok).
        const res = await candidatureApi.getCandidaturePaymentStatus(candidatureId)
        if (res.data.candidature_status === 'accepted' || res.data.payment_status === 'paid') {
          stopPolling()
          paymentStatus.value = 'confirmed'
        } else if (res.data.payment_status === 'failed') {
          stopPolling()
          error.value = 'Le paiement a échoué ou a été annulé.'
          paymentStatus.value = 'failed'
        } else if (!res.data.is_trackable) {
          // Plus aucune transaction traçable à poller : le webhook a déjà résolu
          // ce paiement en échec (entry supprimée, candidature restée pending)
          // AVANT que le poll ne détecte le statut FedaPay terminal — cas fréquent,
          // le webhook étant souvent plus rapide que l'intervalle de 5 s. On surface
          // l'échec immédiatement au lieu d'attendre le timeout de 120 s. Le cas
          // 'paid' est traité au-dessus ⇒ un statut non-traçable arrivant ici est
          // toujours un échec résolu. Calque ProducerMissionCandidaturesPage:79
          // (FIX-19.3 : is_trackable=false → surface l'échec, stop poll).
          stopPolling()
          error.value = 'Le paiement a échoué ou a été annulé.'
          paymentStatus.value = 'failed'
        }
      } catch {
        // Silently ignore polling errors — keep polling
      }
    }, POLL_INTERVAL_MS)

    // Stop polling after timeout
    pollTimeoutTimer = setTimeout(() => {
      if (paymentStatus.value === 'waiting') {
        stopPolling()
        error.value = 'Le délai de confirmation a expiré. Veuillez vérifier le paiement.'
        paymentStatus.value = 'failed'
      }
    }, POLL_TIMEOUT_MS)
  }

  async function initiate(candidatureId: string): Promise<void> {
    isInitiating.value = true
    error.value = null
    paymentStatus.value = 'idle'

    try {
      const res = await candidatureApi.acceptCandidature(candidatureId)

      if (!res.checkout_url) {
        throw new Error('checkout_url manquant')
      }

      // Open FedaPay hosted checkout in a new tab
      window.open(res.checkout_url, '_blank', 'noopener,noreferrer')

      paymentStatus.value = 'waiting'
      startPolling(candidatureId)
    } catch (err) {
      error.value = getApiErrorMessage(err)
      paymentStatus.value = 'failed'
    } finally {
      isInitiating.value = false
    }
  }

  function reset(): void {
    stopPolling()
    isInitiating.value = false
    paymentStatus.value = 'idle'
    error.value = null
  }

  onUnmounted(() => {
    stopPolling()
  })

  return {
    isInitiating,
    paymentStatus,
    error,
    initiate,
    stopPolling,
    reset,
  }
}
