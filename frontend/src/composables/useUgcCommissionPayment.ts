import { ref, onUnmounted, type Ref } from 'vue'
import { bookingApi } from '@/features/booking/services/bookingApi'
import { BookingStatus } from '@/features/booking/types'
import { missionApi } from '@/features/mission/services/missionApi'
import { MissionStatus } from '@/features/mission/types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

const POLL_INTERVAL_MS = 5000
const POLL_TIMEOUT_MS = 120000

export type UgcPaymentOwnerKind = 'booking' | 'mission'
export type UgcPaymentStatus = 'idle' | 'waiting' | 'confirmed' | 'failed'

interface UseUgcCommissionPaymentReturn {
  isInitiating: Ref<boolean>
  isPolling: Ref<boolean>
  paymentStatus: Ref<UgcPaymentStatus>
  error: Ref<string | null>
  initiate: (kind: UgcPaymentOwnerKind, id: string) => Promise<boolean>
  stopPolling: () => void
  reset: () => void
}

/**
 * Drives the UGC commission payment tunnel (booking or mission), mirroring the
 * cash `useBookingPayment` pattern: initiate → open FedaPay hosted checkout in a
 * new tab → poll the `commission-status` endpoint until the owner settles.
 *
 * Terminal state per owner (story 1.5):
 * - booking → `BookingStatus.COMMISSION_PAID`
 * - mission → `MissionStatus.PUBLISHED`
 */
export function useUgcCommissionPayment(): UseUgcCommissionPaymentReturn {
  const isInitiating = ref(false)
  const isPolling = ref(false)
  const paymentStatus = ref<UgcPaymentStatus>('idle')
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

  // Terminal status per owner: booking → commission_paid (never `paid`), mission → published.
  async function isSettled(kind: UgcPaymentOwnerKind, id: string): Promise<boolean> {
    if (kind === 'booking') {
      const { data } = await bookingApi.checkCommissionStatus(id)
      return data.status === BookingStatus.COMMISSION_PAID
    }
    const { data } = await missionApi.getCommissionStatus(id)
    return data.status === MissionStatus.PUBLISHED
  }

  function startPolling(kind: UgcPaymentOwnerKind, id: string, onSettled: () => void): void {
    isPolling.value = true

    pollTimer = setInterval(async () => {
      try {
        // The commission-status endpoint checks FedaPay directly and settles if
        // approved — resilient to delayed/failed webhook delivery (sandbox/ngrok).
        if (await isSettled(kind, id)) {
          stopPolling()
          paymentStatus.value = 'confirmed'
          onSettled()
        }
      } catch {
        // Silently ignore polling errors — keep polling
      }
    }, POLL_INTERVAL_MS)

    pollTimeoutTimer = setTimeout(() => {
      if (isPolling.value) {
        stopPolling()
        error.value = 'Le délai de confirmation a expiré. Vérifiez votre paiement puis réessayez.'
        paymentStatus.value = 'failed'
      }
    }, POLL_TIMEOUT_MS)
  }

  async function initiate(kind: UgcPaymentOwnerKind, id: string): Promise<boolean> {
    isInitiating.value = true
    error.value = null
    paymentStatus.value = 'idle'

    try {
      const checkoutUrl =
        kind === 'booking'
          ? (await bookingApi.payCommission(id)).checkout_url
          : (await missionApi.payCommission(id)).checkout_url

      // Open FedaPay hosted checkout in a new tab (provider is chosen there).
      window.open(checkoutUrl, '_blank', 'noopener,noreferrer')

      paymentStatus.value = 'waiting'
      startPolling(kind, id, () => {
        // paymentStatus is already set to 'confirmed' inside startPolling.
      })

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
    initiate,
    stopPolling,
    reset,
  }
}
