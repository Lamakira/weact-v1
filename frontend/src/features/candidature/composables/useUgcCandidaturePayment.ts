import { ref, type Ref } from 'vue'
import { candidatureApi } from '../services/candidatureApi'
import type { PaymentStatus } from '@/features/booking/types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { redirectToCheckout } from '@/lib/redirectToCheckout'
import { useCheckoutRedirect } from '@/lib/useCheckoutRedirect'

interface UseUgcCandidaturePaymentReturn {
  isInitiating: Ref<boolean>
  paymentStatus: Ref<PaymentStatus>
  error: Ref<string | null>
  initiate: (candidatureId: string) => Promise<void>
  reset: () => void
}

/**
 * Hybrid per-Face payment for a UGC candidature acceptance (ugc-8-5, D-8.5.f).
 *
 * Calque of useBookingPayment but candidature-typed: when the Producer accepts a
 * HYBRID mission candidature, accept initiates a FedaPay checkout and surfaces a
 * `checkout_url`. This composable redirects the browser (same tab) to that checkout;
 * the payment is verified on return (`?payment_return=candidature_escrow`) by
 * `usePaymentReturn`, which polls the self-heal payment-status endpoint.
 *
 * Kept STRICTLY separate from useAcceptCandidature (product-only, free direct
 * accept) — the overlay only drives this one.
 */
export function useUgcCandidaturePayment(): UseUgcCandidaturePaymentReturn {
  const isInitiating = ref(false)
  const paymentStatus = ref<PaymentStatus>('idle')
  const error = ref<string | null>(null)

  async function initiate(candidatureId: string): Promise<void> {
    isInitiating.value = true
    error.value = null
    paymentStatus.value = 'idle'

    try {
      const res = await candidatureApi.acceptCandidature(candidatureId)

      if (!res.checkout_url) {
        throw new Error('checkout_url manquant')
      }

      // Same-tab redirect; 'waiting' = redirecting.
      paymentStatus.value = 'waiting'
      redirectToCheckout(res.checkout_url)
    } catch (err) {
      error.value = getApiErrorMessage(err)
      paymentStatus.value = 'failed'
    } finally {
      isInitiating.value = false
    }
  }

  function reset(): void {
    isInitiating.value = false
    paymentStatus.value = 'idle'
    error.value = null
  }

  // Back from FedaPay (bfcache): leave the frozen « redirecting » state.
  useCheckoutRedirect(reset)

  return {
    isInitiating,
    paymentStatus,
    error,
    initiate,
    reset,
  }
}
