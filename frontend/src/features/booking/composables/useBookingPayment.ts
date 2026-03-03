import { ref, onUnmounted, type Ref } from 'vue'
import { bookingApi } from '../services/bookingApi'
import type { Booking, PaymentStatus } from '../types'
import { BookingStatus } from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

const POLL_INTERVAL_MS = 5000
const POLL_TIMEOUT_MS = 120000

export interface UseBookingPaymentReturn {
  isInitiating: Ref<boolean>
  isPolling: Ref<boolean>
  paymentStatus: Ref<PaymentStatus>
  error: Ref<string | null>
  initiatePayment: (bookingId: number, paymentMode: string, phoneNumber: string, phoneCountry: string) => Promise<Booking | null>
  stopPolling: () => void
  reset: () => void
}

export function useBookingPayment(): UseBookingPaymentReturn {
  const isInitiating = ref(false)
  const isPolling = ref(false)
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
    isPolling.value = false
  }

  function startPolling(bookingId: number, onPaid: (booking: Booking) => void): void {
    isPolling.value = true

    pollTimer = setInterval(async () => {
      try {
        // Use payment-status endpoint: checks Fedapay directly and processes if approved
        // This is resilient to webhook delivery failures (e.g. sandbox/ngrok)
        const response = await bookingApi.checkPaymentStatus(bookingId)
        if (response.data.status === BookingStatus.PAID || response.data.status === BookingStatus.IN_PROGRESS) {
          stopPolling()
          paymentStatus.value = 'confirmed'
          onPaid(response.data)
        }
      } catch {
        // Silently ignore polling errors — keep polling
      }
    }, POLL_INTERVAL_MS)

    // Stop polling after timeout
    pollTimeoutTimer = setTimeout(() => {
      if (isPolling.value) {
        stopPolling()
        error.value = 'Le délai de confirmation a expiré. Veuillez vérifier votre téléphone.'
        paymentStatus.value = 'failed'
      }
    }, POLL_TIMEOUT_MS)
  }

  async function initiatePayment(bookingId: number, paymentMode: string, phoneNumber: string, phoneCountry: string): Promise<Booking | null> {
    isInitiating.value = true
    error.value = null
    paymentStatus.value = 'idle'

    try {
      const response = await bookingApi.payBooking(bookingId, paymentMode, phoneNumber, phoneCountry)
      paymentStatus.value = 'waiting'

      // Start polling for payment confirmation
      let resolvedBooking: Booking | null = response.data
      startPolling(bookingId, (paidBooking) => {
        resolvedBooking = paidBooking
      })

      return resolvedBooking
    } catch (err) {
      error.value = getApiErrorMessage(err)
      paymentStatus.value = 'failed'
      return null
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
    isPolling,
    paymentStatus,
    error,
    initiatePayment,
    stopPolling,
    reset,
  }
}
