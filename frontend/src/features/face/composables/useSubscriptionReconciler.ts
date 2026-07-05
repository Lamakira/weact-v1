import { computed, onMounted, onUnmounted, ref, watch, type ComputedRef } from 'vue'
import { faceApi } from '../services/faceApi'
import { useSubscriptionStatus } from './useSubscriptionStatus'
import { useAuthStore } from '@/stores/auth'

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
 * Mounted dashboard-wide via PendingSubscriptionPaymentBanner (FaceLayout) and site-wide
 * via SitewideSubscriptionPaymentBanner (public App.vue branches, gated to Faces). The
 * Facturation tab and /pricing already run their own useSubscriptionPayment polling, so
 * both mounts skip routes flagged `meta.ownSubscriptionSurface` in the router.
 */
const RECONCILE_POLL_MS = 6000

/**
 * Per-user localStorage key prefix for the "Ignorer" flag on a failed payment.
 * Scoped by user id so two Faces sharing a browser stay independent.
 */
const DISMISS_STORAGE_PREFIX = 'face-subscription-failure-dismissed:'

function canUseStorage(): boolean {
  return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'
}

export function useSubscriptionReconciler(): {
  hasPendingPayment: ComputedRef<boolean>
  paymentFailed: ComputedRef<boolean>
  dismissFailure: () => void
} {
  const { current, cta, statusValue, fetchStatus, refreshStatus } = useSubscriptionStatus()
  const authStore = useAuthStore()

  // FP-2.3 forces every CTA false while a payment is pending (also catches an
  // active + pending tier-change). Never true for a free, never-paid Face.
  const hasPendingPayment = computed(() => {
    if (!current.value) return false
    const c = cta.value
    return !c.upgrade_available && !c.downgrade_available && !c.renew_available
  })

  function storageKey(): string | null {
    const userId = authStore.user?.id
    return userId != null ? `${DISMISS_STORAGE_PREFIX}${userId}` : null
  }

  function readDismissed(): boolean {
    const key = storageKey()
    if (!key || !canUseStorage()) return false
    try {
      return window.localStorage.getItem(key) !== null
    } catch {
      return false
    }
  }

  // Durable "Ignorer" flag for a failed payment — persisted per user so it
  // survives navigation/reload. Cleared as soon as a NEW pending attempt is
  // observed (any retry goes through pending), so a fresh failure re-surfaces.
  const dismissed = ref(readDismissed())

  // Derived from the SERVER status (not from an observed pending→failed
  // transition): a Face whose payment failed sees the retry nudge on any page,
  // even after a full reload, until they dismiss it.
  const paymentFailed = computed(() => statusValue.value === 'failed' && !dismissed.value)

  function dismissFailure(): void {
    dismissed.value = true
    const key = storageKey()
    if (key && canUseStorage()) {
      try {
        window.localStorage.setItem(key, '1')
      } catch {
        // Storage blocked/full — the in-memory flag still hides the nudge for this session.
      }
    }
  }

  function clearDismissed(): void {
    dismissed.value = false
    const key = storageKey()
    if (key && canUseStorage()) {
      try {
        window.localStorage.removeItem(key)
      } catch {
        // Storage blocked — in-memory flag is cleared regardless.
      }
    }
  }

  let timer: ReturnType<typeof setInterval> | null = null
  let inFlight = false
  // Set by onUnmounted: the async continuation of onMounted (fetch → reconcile
  // → start) must not fire a verify POST or arm the poll interval on a dead
  // component — frequent with the site-wide mount, where route resolution can
  // unmount the wrapper while the initial status fetch is still in flight.
  let disposed = false

  function isVisible(): boolean {
    return typeof document === 'undefined' || document.visibilityState === 'visible'
  }

  async function reconcile(): Promise<void> {
    if (inFlight || !hasPendingPayment.value) return
    inFlight = true
    try {
      const response = await faceApi.verifySubscriptionPayment()
      // Continuation guard: no forced status GET on a dead component, and
      // never with credentials a logout just revoked (the 401 would bounce a
      // voluntary logout onto login?message=session-expired).
      if (disposed || !authStore.user) return
      // While the payment is STILL pending, the forced refresh would just
      // re-download an identical payload every tick. Only refresh when verify
      // reports the payment left the pending state (approved/declined) — the
      // moment the CTAs/banner need the authoritative status payload.
      if (response.data.status !== 'pending_payment') {
        await refreshStatus()
      }
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
    if (disposed || timer || !hasPendingPayment.value || !isVisible()) return
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
    if (disposed) return
    await reconcile()
    start()
  })

  onUnmounted(() => {
    disposed = true
    stop()
    document.removeEventListener('visibilitychange', onVisibilityChange)
  })

  // immediate: a reconciler mounted while the shared status cache ALREADY says
  // pending (SPA navigation during an in-flight retry) must also clear the
  // "Ignorer" flag — without it, only an observed false→true transition would,
  // and the new failure would stay dismissed forever.
  watch(hasPendingPayment, (pending) => {
    if (pending) {
      // A fresh pending attempt clears any previous "Ignorer" so a new
      // failure always re-surfaces the nudge.
      clearDismissed()
      start()
      return
    }
    stop()
  }, { immediate: true })

  return { hasPendingPayment, paymentFailed, dismissFailure }
}
