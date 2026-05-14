import { ref, onUnmounted, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { SubscriptionPaymentState, SubscriptionStatusInfo } from '../types'
import { useSubscriptionStatus } from './useSubscriptionStatus'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

const POLL_INTERVAL_MS = 5000
const POLL_TIMEOUT_MS = 120000

interface UseSubscriptionPaymentReturn {
  isInitiating: Ref<boolean>
  isPolling: Ref<boolean>
  paymentState: Ref<SubscriptionPaymentState>
  error: Ref<string | null>
  initiatePayment: () => Promise<boolean>
  verifyPayment: () => Promise<boolean>
  stopPolling: () => void
  reset: () => void
}

export function useSubscriptionPayment(): UseSubscriptionPaymentReturn {
  const isInitiating = ref(false)
  const isPolling = ref(false)
  const paymentState = ref<SubscriptionPaymentState>('idle')
  const error = ref<string | null>(null)

  const { refreshStatus, status: subscriptionStatus } = useSubscriptionStatus()

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

  function startPolling(onConfirmed: (status: SubscriptionStatusInfo) => void): void {
    stopPolling()
    isPolling.value = true

    pollTimer = setInterval(async () => {
      try {
        await verifyPayment()
        const current = subscriptionStatus.value
        if (current && current.status === 'active' && current.is_premium) {
          stopPolling()
          paymentState.value = 'confirmed'
          onConfirmed(current)
        } else if (current && current.status === 'failed') {
          stopPolling()
          paymentState.value = 'failed'
          error.value = 'Le paiement a échoué. Veuillez réessayer.'
        }
      } catch {
        // Silently ignore polling errors — keep polling until timeout
      }
    }, POLL_INTERVAL_MS)

    pollTimeoutTimer = setTimeout(() => {
      if (isPolling.value) {
        stopPolling()
        error.value = 'Le délai de confirmation a expiré. Vérifiez votre paiement et rafraîchissez la page.'
        paymentState.value = 'failed'
      }
    }, POLL_TIMEOUT_MS)
  }

  async function initiatePayment(): Promise<boolean> {
    if (isInitiating.value || isPolling.value) {
      return false
    }

    isInitiating.value = true
    error.value = null
    paymentState.value = 'idle'

    try {
      const response = await faceApi.initiateSubscriptionPayment()

      // Open Fedapay hosted checkout in a new tab (matches FP-1.5 contract)
      const checkoutWindow = window.open(
        response.data.checkout_url,
        '_blank',
        'noopener,noreferrer',
      )

      if (!checkoutWindow) {
        error.value = 'La fenêtre de paiement a été bloquée. Autorisez les popups puis réessayez.'
        paymentState.value = 'failed'
        return false
      }

      paymentState.value = 'waiting'

      // Refresh once now so the UI flips to pending_payment immediately
      await refreshStatus()

      startPolling((confirmedStatus) => {
        // The confirmed status is written into the shared subscription-status ref.
        // Callers react to paymentState/status changes instead of awaiting final confirmation here.
        subscriptionStatus.value = confirmedStatus
      })

      return true
    } catch (err) {
      error.value = getApiErrorMessage(err)
      paymentState.value = 'failed'
      return false
    } finally {
      isInitiating.value = false
    }
  }

  async function verifyPayment(): Promise<boolean> {
    error.value = null

    try {
      await faceApi.verifySubscriptionPayment()
      await refreshStatus()

      const current = subscriptionStatus.value
      if (current && current.status === 'active' && current.is_premium) {
        stopPolling()
        paymentState.value = 'confirmed'
        return true
      }

      if (current && current.status === 'failed') {
        stopPolling()
        paymentState.value = 'failed'
        error.value = 'Le paiement a échoué. Veuillez réessayer.'
        return false
      }

      return false
    } catch (err) {
      error.value = getApiErrorMessage(err)
      return false
    }
  }

  function reset(): void {
    stopPolling()
    isInitiating.value = false
    paymentState.value = 'idle'
    error.value = null
  }

  onUnmounted(() => {
    stopPolling()
  })

  return {
    isInitiating,
    isPolling,
    paymentState,
    error,
    initiatePayment,
    verifyPayment,
    stopPolling,
    reset,
  }
}
