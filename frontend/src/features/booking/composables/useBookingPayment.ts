import { ref, type Ref } from 'vue'
import { bookingApi } from '../services/bookingApi'
import type { Booking, PaymentStatus } from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { redirectToCheckout } from '@/lib/redirectToCheckout'
import { useCheckoutRedirect } from '@/lib/useCheckoutRedirect'

interface UseBookingPaymentReturn {
  isInitiating: Ref<boolean>
  paymentStatus: Ref<PaymentStatus>
  error: Ref<string | null>
  initiatePayment: (bookingId: string) => Promise<Booking | null>
  reset: () => void
}

export function useBookingPayment(): UseBookingPaymentReturn {
  const isInitiating = ref(false)
  const paymentStatus = ref<PaymentStatus>('idle')
  const error = ref<string | null>(null)

  async function initiatePayment(bookingId: string): Promise<Booking | null> {
    isInitiating.value = true
    error.value = null
    paymentStatus.value = 'idle'

    try {
      const response = await bookingApi.payBooking(bookingId)

      // Same-tab redirect to the FedaPay hosted checkout ('waiting' = redirecting).
      // The user comes back on the booking page with ?payment_return=booking,
      // where usePaymentReturn verifies the payment.
      paymentStatus.value = 'waiting'
      redirectToCheckout(response.checkout_url)

      return response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      paymentStatus.value = 'failed'
      return null
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
    initiatePayment,
    reset,
  }
}
