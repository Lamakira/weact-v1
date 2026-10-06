import { computed, onMounted, onUnmounted, ref, watch, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { FaceSubscriptionPlan, FaceSubscriptionTier, SubscriptionPaymentState } from '../types'
import { useSubscriptionStatus } from './useSubscriptionStatus'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { redirectToCheckout } from '@/lib/redirectToCheckout'
import { useCheckoutRedirect } from '@/lib/useCheckoutRedirect'

interface PaymentSnapshot {
  tier: FaceSubscriptionTier
  expiresAt: string | null
}

interface VerifyPaymentOptions {
  manual?: boolean
}

interface UseSubscriptionPaymentReturn {
  isInitiating: Ref<boolean>
  isVerifying: Ref<boolean>
  isCancelling: Ref<boolean>
  paymentState: Ref<SubscriptionPaymentState>
  error: Ref<string | null>
  initiatePayment: (plan: FaceSubscriptionPlan) => Promise<boolean>
  resumePayment: () => Promise<boolean>
  verifyPayment: (options?: VerifyPaymentOptions) => Promise<void>
  cancelPending: () => Promise<boolean>
  dismissPaymentError: () => void
  reset: () => void
}

export function useSubscriptionPayment(): UseSubscriptionPaymentReturn {
  const isInitiating = ref(false)
  const isVerifying = ref(false)
  const isCancelling = ref(false)
  const paymentState = ref<SubscriptionPaymentState>('idle')
  const error = ref<string | null>(null)

  // Armed iff a payment attempt was actually initiated or resumed in THIS composable
  // instance. Without this flag, `verifyPayment({ manual: true })` would falsely
  // emit 'confirmed' when the backend's current.tier already differs from the default
  // snapshot {free, null} — e.g., a Pro user who has a separate pending Élite row
  // created from another device.
  const hasArmedPayment = ref<boolean>(false)

  const { current, cta, statusValue, refreshStatus } = useSubscriptionStatus()

  // FP-2.15.1 — mirrors the UI pending-banner predicate (current row exists + every
  // CTA disabled). Broader than statusValue === 'pending_payment' on purpose: an
  // active + pending tier-change keeps representative statusValue 'active' while the
  // pending row is still being settled, but FP-2.3 forces CTA all-false in that window.
  const hasPendingPayment = computed(() => {
    if (!current.value) return false
    const paymentCta = cta.value
    return (
      !paymentCta.upgrade_available &&
      !paymentCta.downgrade_available &&
      !paymentCta.renew_available
    )
  })

  let snapshot: PaymentSnapshot = { tier: 'free', expiresAt: null }

  // Round 2 D3 — re-arm the verify guard when the composable mounts (or status
  // refreshes) into a pending_payment state. Without this, a user who left the
  // Fedapay checkout, returns to /face/profile, and clicks "Vérifier maintenant" after
  // the backend already confirmed sees no terminal feedback (paymentState stays
  // 'idle', no `subscription-changed` emit). The cross-device Pro/Élite false-
  // positive guarded by Round 1 #1 is unaffected: that scenario keeps the Pro
  // user's statusValue at 'active', so this watch does not fire for them.
  watch(
    statusValue,
    (value) => {
      if (value === 'pending_payment' && !hasArmedPayment.value) {
        snapshot = {
          tier: current.value?.tier ?? 'free',
          expiresAt: current.value?.expires_at ?? null,
        }
        hasArmedPayment.value = true
      }
    },
    { immediate: true },
  )

  // Decision #7 — confirmed when the refreshed status is active AND it differs
  // from the pre-initiate snapshot (tier changed → activation/upgrade/downgrade;
  // expires_at changed → renewal while still active).
  function isConfirmed(): boolean {
    const c = current.value
    if (!c || c.status !== 'active') return false
    return c.tier !== snapshot.tier || c.expires_at !== snapshot.expiresAt
  }

  async function verifyPayment(options: VerifyPaymentOptions = {}): Promise<void> {
    // P5 — guard against overlapping verify calls (rapid manual clicks + interleaving polls).
    if (isVerifying.value) return
    // Round 2 P3 — symmetric guard against an in-flight initiatePayment. Otherwise
    // a manual "Vérifier" click during the initiate->Fedapay-await window can send
    // a verify call that resolves out of order and mutates paymentState.
    if (isInitiating.value) return
    if (isCancelling.value) return
    isVerifying.value = true

    try {
      await faceApi.verifySubscriptionPayment()
      await refreshStatus()

      // Findings #1 — only emit terminal confirmation/failure when an in-session
      // payment attempt was actually armed. Without this guard, a manual verify
      // would falsely confirm an already-active subscription whose tier differs
      // from the default snapshot {free, null} — e.g., a Pro user checking a
      // cross-device Élite pending.
      if (!hasArmedPayment.value) return

      if (isConfirmed()) {
        hasArmedPayment.value = false
        paymentState.value = 'confirmed'
      } else if (current.value?.status === 'failed') {
        hasArmedPayment.value = false
        paymentState.value = 'failed'
        error.value = 'Le paiement a échoué. Veuillez réessayer.'
      }
    } catch (err) {
      // P11 — manual clicks surface the error so the user gets feedback;
      // non-manual calls (visibility reconciler) keep the error swallowed.
      if (options.manual) {
        error.value = getApiErrorMessage(err)
      }
    } finally {
      isVerifying.value = false
    }
  }

  async function initiatePayment(plan: FaceSubscriptionPlan): Promise<boolean> {
    if (isInitiating.value || isVerifying.value || isCancelling.value) {
      return false
    }

    isInitiating.value = true
    error.value = null
    paymentState.value = 'idle'
    snapshot = {
      tier: current.value?.tier ?? 'free',
      expiresAt: current.value?.expires_at ?? null,
    }

    try {
      const response = await faceApi.initiateSubscriptionPayment(plan)

      // Arm the payment attempt — see hasArmedPayment doc above.
      hasArmedPayment.value = true

      // 'waiting' = redirecting. The page is left: the return is verified on
      // /face/billing?payment_return=subscription (usePaymentReturn).
      paymentState.value = 'waiting'
      redirectToCheckout(response.data.checkout_url)
      return true
    } catch (err) {
      error.value = getApiErrorMessage(err)
      paymentState.value = 'failed'
      return false
    } finally {
      isInitiating.value = false
    }
  }

  async function resumePayment(): Promise<boolean> {
    if (isInitiating.value || isVerifying.value || isCancelling.value) {
      return false
    }
    isInitiating.value = true
    error.value = null
    paymentState.value = 'idle'
    snapshot = {
      tier: current.value?.tier ?? 'free',
      expiresAt: current.value?.expires_at ?? null,
    }
    try {
      const response = await faceApi.resumePendingSubscription()
      // Backend already reconciled to Active (Fedapay approved during resume race).
      if (response.data.status === 'active') {
        paymentState.value = 'confirmed'
        hasArmedPayment.value = false
        await refreshStatus()
        return true
      }
      const checkoutUrl = response.data.checkout_url
      if (!checkoutUrl) {
        // Defensive — backend returned pending_payment without a URL.
        error.value =
          'Aucune URL de paiement disponible. Veuillez initier un nouveau paiement depuis la page Tarifs.'
        paymentState.value = 'failed'
        return false
      }
      hasArmedPayment.value = true
      paymentState.value = 'waiting'
      redirectToCheckout(checkoutUrl)
      return true
    } catch (err) {
      error.value = getApiErrorMessage(err)
      paymentState.value = 'failed'
      // Refresh status so the UI reflects the backend reconciliation
      // (the resume endpoint flips the row to Failed on declined / canceled / expired,
      // so the pending banner unmounts as soon as the refresh lands).
      try {
        await refreshStatus()
      } catch {
        // Swallow — the user can manually verify / refresh.
      }
      return false
    } finally {
      isInitiating.value = false
    }
  }

  async function cancelPending(): Promise<boolean> {
    // The mutually-exclusive guards (isInitiating / isVerifying / isCancelling)
    // serialize against the 3 other mutators.
    if (isInitiating.value || isVerifying.value || isCancelling.value) {
      return false
    }
    isCancelling.value = true
    error.value = null
    // Unmount the waiting banner BEFORE the backend round-trip so the user sees
    // immediate feedback. If the backend call fails, the user lands on the
    // pending banner with the inline error and can retry.
    if (paymentState.value === 'waiting') {
      paymentState.value = 'idle'
    }
    try {
      await faceApi.cancelPendingSubscription()
      hasArmedPayment.value = false
      paymentState.value = 'idle'
      await refreshStatus()
      return true
    } catch (err) {
      error.value = getApiErrorMessage(err)
      return false
    } finally {
      isCancelling.value = false
    }
  }

  function reset(): void {
    isInitiating.value = false
    isVerifying.value = false
    isCancelling.value = false
    hasArmedPayment.value = false
    paymentState.value = 'idle'
    error.value = null
  }

  function dismissPaymentError(): void {
    error.value = null
    paymentState.value = 'idle'
  }

  // FP-2.15.1 — when the user switches back to the WEACT tab after paying on Fedapay
  // (e.g. browser Back from the checkout), reconcile the deferred webhook without
  // requiring a manual "Vérifier" click. The hasArmedPayment gate prevents a normal
  // active subscriber from getting a false-positive on a simple tab visit.
  function onVisibilityChange(): void {
    if (document.visibilityState !== 'visible') return
    if (!hasPendingPayment.value) return
    if (isInitiating.value || isVerifying.value || isCancelling.value) {
      return
    }
    if (!hasArmedPayment.value) return
    void verifyPayment({ manual: false })
  }

  // Back from FedaPay (bfcache): only the frozen « redirecting » state is reset;
  // a pending row stays handled by the pending banner / visibility reconciler.
  useCheckoutRedirect(() => {
    if (paymentState.value === 'waiting') paymentState.value = 'idle'
    isInitiating.value = false
  })

  onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange)
  })

  onUnmounted(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange)
  })

  return {
    isInitiating,
    isVerifying,
    isCancelling,
    paymentState,
    error,
    initiatePayment,
    resumePayment,
    verifyPayment,
    cancelPending,
    dismissPaymentError,
    reset,
  }
}
