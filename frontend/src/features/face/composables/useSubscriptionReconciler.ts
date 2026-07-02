import { computed, onMounted, onUnmounted, ref, watch, type ComputedRef, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import { useSubscriptionStatus } from './useSubscriptionStatus'

/**
 * Dashboard-wide auto-reconciliation of a pending subscription payment.
 *
 * After paying on Fedapay, the Face returns to the app — possibly on a page that
 * is NOT the one that initiated the payment (e.g. their profile). Without this,
 * nothing would reconcile the approval and the Face would have to hit "Vérifier
 * maintenant" manually.
 *
 * Strategy (no manual action required):
 *  - on mount: read status (cache-friendly) and reconcile once if a payment is pending;
 *  - on tab focus (visibilitychange → visible): reconcile (covers returning from the Fedapay tab);
 *  - while pending AND the tab is visible: poll every {@link RECONCILE_POLL_MS} so a
 *    late mobile-money confirmation is still caught — self-stops when the payment
 *    resolves (active/failed) or the tab is hidden.
 *
 * Reconcile = POST verify-payment (server polls Fedapay + flips the row) then force a
 * status refresh. Because the status lives in a shared cached resource, every consumer
 * (profile capabilities, billing card, this banner) updates reactively once it resolves.
 *
 * Mounted once, dashboard-wide, via PendingSubscriptionPaymentBanner. The Facturation
 * tab and /pricing already run their own useSubscriptionPayment polling, so this is not
 * mounted there (the banner is hidden on the billing route).
 */
const RECONCILE_POLL_MS = 6000

export function useSubscriptionReconciler(): {
  hasPendingPayment: ComputedRef<boolean>
  paymentFailed: Ref<boolean>
  dismissFailure: () => void
} {
  const { current, cta, fetchStatus, refreshStatus } = useSubscriptionStatus()

  // FP-2.3 forces every CTA false while a payment is pending (also catches an
  // active + pending tier-change). Never true for a free, never-paid Face.
  const hasPendingPayment = computed(() => {
    if (!current.value) return false
    const c = cta.value
    return !c.upgrade_available && !c.downgrade_available && !c.renew_available
  })

  // Set when a pending payment WE were watching resolved to `failed` (Fedapay
  // decline / stale-pending cron). Lets dashboard pages surface a retry nudge
  // instead of the pending banner silently vanishing. Session-transient + dismissable.
  const paymentFailed = ref(false)
  function dismissFailure(): void {
    paymentFailed.value = false
  }

  let timer: ReturnType<typeof setInterval> | null = null
  let inFlight = false

  function isVisible(): boolean {
    return typeof document === 'undefined' || document.visibilityState === 'visible'
  }

  async function reconcile(): Promise<void> {
    if (inFlight || !hasPendingPayment.value) return
    inFlight = true
    try {
      await faceApi.verifySubscriptionPayment()
      await refreshStatus()
    } catch {
      // Transient — the next tick or visibility change retries.
    } finally {
      inFlight = false
    }
  }

  function stop(): void {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  function start(): void {
    if (timer || !hasPendingPayment.value || !isVisible()) return
    timer = setInterval(() => {
      if (!hasPendingPayment.value || !isVisible()) {
        stop()
        return
      }
      void reconcile()
    }, RECONCILE_POLL_MS)
  }

  function onVisibilityChange(): void {
    if (!isVisible()) {
      stop()
      return
    }
    void reconcile()
    start()
  }

  onMounted(async () => {
    document.addEventListener('visibilitychange', onVisibilityChange)
    // Cache-friendly read first (the initiating page already set the cache to
    // pending), then reconcile + start polling only if a payment is pending.
    try {
      await fetchStatus()
    } catch {
      // Swallow — a failed status read just leaves the banner hidden.
    }
    await reconcile()
    start()
  })

  onUnmounted(() => {
    stop()
    document.removeEventListener('visibilitychange', onVisibilityChange)
  })

  watch(hasPendingPayment, (pending, wasPending) => {
    if (pending) {
      // A fresh pending payment clears any previous failure nudge.
      paymentFailed.value = false
      start()
      return
    }
    stop()
    // pending → failed = a real decline / stale-pending timeout. pending → active
    // (success) leaves paymentFailed false.
    if (wasPending && current.value?.status === 'failed') {
      paymentFailed.value = true
    }
  })

  return { hasPendingPayment, paymentFailed, dismissFailure }
}
