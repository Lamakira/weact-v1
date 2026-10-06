import { ref, type Ref } from 'vue'
import { bookingApi } from '@/features/booking/services/bookingApi'
import { missionApi } from '@/features/mission/services/missionApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { redirectToCheckout } from '@/lib/redirectToCheckout'
import { useCheckoutRedirect } from '@/lib/useCheckoutRedirect'

export type UgcPaymentOwnerKind = 'booking' | 'mission'
export type UgcPaymentStatus = 'idle' | 'waiting' | 'confirmed' | 'failed'

interface UseUgcCommissionPaymentReturn {
  isInitiating: Ref<boolean>
  paymentStatus: Ref<UgcPaymentStatus>
  error: Ref<string | null>
  initiate: (kind: UgcPaymentOwnerKind, id: string) => Promise<boolean>
  reset: () => void
}

/**
 * Drives the UGC commission payment tunnel (booking or mission), mirroring the
 * cash `useBookingPayment` pattern: initiate → same-tab redirect to the FedaPay
 * hosted checkout. The settlement is verified on return (`?payment_return=
 * booking_commission|mission_commission`) by `usePaymentReturn`.
 */
export function useUgcCommissionPayment(): UseUgcCommissionPaymentReturn {
  const isInitiating = ref(false)
  const paymentStatus = ref<UgcPaymentStatus>('idle')
  const error = ref<string | null>(null)

  async function initiate(kind: UgcPaymentOwnerKind, id: string): Promise<boolean> {
    isInitiating.value = true
    error.value = null
    paymentStatus.value = 'idle'

    try {
      const checkoutUrl =
        kind === 'booking'
          ? (await bookingApi.payCommission(id)).checkout_url
          : (await missionApi.payCommission(id)).checkout_url

      // Same-tab redirect (provider is chosen on the FedaPay page); 'waiting' = redirecting.
      paymentStatus.value = 'waiting'
      redirectToCheckout(checkoutUrl)

      return true
    } catch (err) {
      error.value = getApiErrorMessage(err)
      paymentStatus.value = 'failed'
      return false
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
