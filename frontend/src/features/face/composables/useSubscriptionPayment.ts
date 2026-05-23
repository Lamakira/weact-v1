import { onUnmounted, ref, watch, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { FaceSubscriptionPlan, FaceSubscriptionTier, SubscriptionPaymentState } from '../types'
import { useSubscriptionStatus } from './useSubscriptionStatus'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { useAuthStore } from '@/stores/auth'

const POLL_INTERVAL_MS = 5000
const POLL_TIMEOUT_MS = 120000
const STASH_TTL_MS = 24 * 60 * 60 * 1000

interface PaymentSnapshot {
  tier: FaceSubscriptionTier
  expiresAt: string | null
}

interface VerifyPaymentOptions {
  manual?: boolean
}

interface PendingCheckoutStash {
  url: string
  plan: string
  storedAt: number
}

interface UseSubscriptionPaymentReturn {
  isInitiating: Ref<boolean>
  isPolling: Ref<boolean>
  isVerifying: Ref<boolean>
  pendingCheckoutAvailable: Ref<boolean>
  paymentState: Ref<SubscriptionPaymentState>
  error: Ref<string | null>
  initiatePayment: (plan: FaceSubscriptionPlan) => Promise<boolean>
  resumePayment: () => Promise<boolean>
  verifyPayment: (options?: VerifyPaymentOptions) => Promise<void>
  stopPolling: () => void
  dismissPaymentError: () => void
  reset: () => void
}

// Stash key is scoped to the authenticated user so one Face's pending checkout
// cannot leak to another on a shared browser. user.id is always present when
// isAuthenticated; the 'guest' fallback is defensive (no payment can be initiated
// unauthenticated — backend rejects 401).
function getStashKey(): string {
  const userId = useAuthStore().user?.id ?? 'guest'
  return `weact:pending-checkout:user-${userId}`
}

function readPendingCheckoutStash(): PendingCheckoutStash | null {
  try {
    const raw = sessionStorage.getItem(getStashKey())
    if (!raw) return null
    const parsed = JSON.parse(raw) as Partial<PendingCheckoutStash>
    if (!parsed.url || !parsed.plan || typeof parsed.storedAt !== 'number') return null
    // TTL: Fedapay checkout URLs typically expire after ~24h.
    if (Date.now() - parsed.storedAt > STASH_TTL_MS) {
      clearPendingCheckoutStash()
      return null
    }
    return parsed as PendingCheckoutStash
  } catch {
    return null
  }
}

// Returns true if the stash was successfully written, false on quota/private-mode
// failures. Callers use the return value to gate `pendingCheckoutAvailable` so the
// UI never advertises a resume button for a stash that was silently dropped.
function stashPendingCheckout(url: string, plan: string): boolean {
  try {
    sessionStorage.setItem(
      getStashKey(),
      JSON.stringify({ url, plan, storedAt: Date.now() } satisfies PendingCheckoutStash),
    )
    return true
  } catch {
    return false
  }
}

function clearPendingCheckoutStash(): void {
  try {
    sessionStorage.removeItem(getStashKey())
  } catch {
    // no-op
  }
}

export function useSubscriptionPayment(): UseSubscriptionPaymentReturn {
  const isInitiating = ref(false)
  const isPolling = ref(false)
  const isVerifying = ref(false)
  const paymentState = ref<SubscriptionPaymentState>('idle')
  const error = ref<string | null>(null)
  const pendingCheckoutAvailable = ref<boolean>(readPendingCheckoutStash() !== null)

  // Armed iff a payment attempt was actually initiated or resumed in THIS composable
  // instance. Without this flag, `verifyPayment({ manual: true })` would falsely
  // emit 'confirmed' when the backend's current.tier already differs from the default
  // snapshot {free, null} — e.g., a Pro user who has a separate pending Élite row
  // created from another device.
  const hasArmedPayment = ref<boolean>(false)

  const { current, statusValue, refreshStatus } = useSubscriptionStatus()

  let pollTimer: ReturnType<typeof setInterval> | null = null
  let pollTimeoutTimer: ReturnType<typeof setTimeout> | null = null
  let snapshot: PaymentSnapshot = { tier: 'free', expiresAt: null }

  // Round 2 D3 — re-arm the verify guard when the composable mounts (or status
  // refreshes) into a pending_payment state. Without this, a user who closed the
  // Fedapay tab, returns to /face/profile, and clicks "Vérifier maintenant" after
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

  // Decision #7 — confirmed when the refreshed status is active AND it differs
  // from the pre-initiate snapshot (tier changed → activation/upgrade/downgrade;
  // expires_at changed → renewal while still active).
  function isConfirmed(): boolean {
    const c = current.value
    if (!c || c.status !== 'active') return false
    return c.tier !== snapshot.tier || c.expires_at !== snapshot.expiresAt
  }

  function startPolling(): void {
    stopPolling()
    isPolling.value = true

    pollTimer = setInterval(() => {
      void verifyPayment()
    }, POLL_INTERVAL_MS)

    pollTimeoutTimer = setTimeout(() => {
      if (isPolling.value) {
        stopPolling()
        hasArmedPayment.value = false
        error.value =
          'Le délai de confirmation a expiré. Vérifiez votre paiement puis rafraîchissez la page.'
        paymentState.value = 'failed'
      }
    }, POLL_TIMEOUT_MS)
  }

  async function verifyPayment(options: VerifyPaymentOptions = {}): Promise<void> {
    // P5 — guard against overlapping verify calls (rapid manual clicks + interleaving polls).
    if (isVerifying.value) return
    // Round 2 P3 — symmetric guard against an in-flight initiatePayment. Otherwise
    // a manual "Vérifier" click during the initiate->Fedapay-await window can send
    // a verify call that resolves out of order and mutates paymentState.
    if (isInitiating.value) return
    isVerifying.value = true

    // P4 — capture polling state pre-await; if a polling-triggered verify completes
    // after stopPolling()/timeout/unmount ran, do not mutate paymentState.
    const wasPolling = isPolling.value

    try {
      await faceApi.verifySubscriptionPayment()
      await refreshStatus()

      // P4 — bail out if polling was alive when we started but has since been cleared
      // (timeout fired, component unmounted, or another path called stopPolling).
      if (wasPolling && !isPolling.value) return

      // Findings #1 — only emit terminal confirmation/failure when an in-session
      // payment attempt was actually armed. Without this guard, a manual verify
      // would falsely confirm an already-active subscription whose tier differs
      // from the default snapshot {free, null} — e.g., a Pro user checking a
      // cross-device Élite pending.
      if (!hasArmedPayment.value) return

      if (isConfirmed()) {
        stopPolling()
        clearPendingCheckoutStash()
        pendingCheckoutAvailable.value = false
        hasArmedPayment.value = false
        paymentState.value = 'confirmed'
      } else if (current.value?.status === 'failed') {
        stopPolling()
        hasArmedPayment.value = false
        paymentState.value = 'failed'
        error.value = 'Le paiement a échoué. Veuillez réessayer.'
      }
    } catch (err) {
      // P11 — manual clicks surface the error so the user gets feedback;
      // polling keeps the error swallowed and retries on the next tick.
      if (options.manual) {
        error.value = getApiErrorMessage(err)
      }
    } finally {
      isVerifying.value = false
    }
  }

  async function initiatePayment(plan: FaceSubscriptionPlan): Promise<boolean> {
    if (isInitiating.value || isPolling.value || isVerifying.value) {
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

      const checkoutWindow = window.open(
        response.data.checkout_url,
        '_blank',
        'noopener,noreferrer',
      )

      if (!checkoutWindow) {
        error.value =
          'La fenêtre de paiement a été bloquée. Autorisez les popups puis réessayez.'
        paymentState.value = 'failed'
        return false
      }

      // Findings #6 — only flip pendingCheckoutAvailable when the stash actually
      // landed in sessionStorage. Otherwise (Safari private mode, quota-restricted
      // storage), the resume button would be advertised for a stash that doesn't
      // exist, leading to a confusing "Aucun paiement à reprendre" on click.
      const stashed = stashPendingCheckout(response.data.checkout_url, plan)
      pendingCheckoutAvailable.value = stashed

      // Arm the payment attempt — see hasArmedPayment doc above.
      hasArmedPayment.value = true

      paymentState.value = 'waiting'

      // P3 — start polling even if the post-initiate refresh fails; the polling
      // cycle itself will refresh on its first tick, so a transient backend hiccup
      // doesn't strand the user on a Fedapay tab without any confirmation loop.
      try {
        await refreshStatus()
      } catch {
        // Swallow — the polling cycle will refresh.
      }
      startPolling()
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
    // Findings #4 — block resume while a manual verify is in flight, otherwise
    // the in-flight verify can resolve concurrently with the resume's polling
    // cycle and mutate paymentState unpredictably.
    if (isInitiating.value || isPolling.value || isVerifying.value) return false

    const stash = readPendingCheckoutStash()
    if (!stash) {
      error.value = 'Aucun paiement à reprendre. Initiez un nouveau paiement depuis la page Tarifs.'
      pendingCheckoutAvailable.value = false
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
      const checkoutWindow = window.open(stash.url, '_blank', 'noopener,noreferrer')
      if (!checkoutWindow) {
        error.value =
          'La fenêtre de paiement a été bloquée. Autorisez les popups puis réessayez.'
        paymentState.value = 'failed'
        return false
      }

      // Arm the payment attempt — see hasArmedPayment doc above.
      hasArmedPayment.value = true

      paymentState.value = 'waiting'

      try {
        await refreshStatus()
      } catch {
        // P3 tolerance — polling will refresh on first tick.
      }
      startPolling()
      return true
    } catch (err) {
      // Round 2 P1 — symmetric catch with initiatePayment. Any throw from
      // window.open (sandbox iframe), startPolling (setInterval unavailable),
      // or refreshStatus would otherwise leave paymentState in 'waiting' forever.
      error.value = getApiErrorMessage(err)
      paymentState.value = 'failed'
      return false
    } finally {
      isInitiating.value = false
    }
  }

  function reset(): void {
    stopPolling()
    isInitiating.value = false
    isVerifying.value = false
    hasArmedPayment.value = false
    paymentState.value = 'idle'
    error.value = null
    clearPendingCheckoutStash()
    pendingCheckoutAvailable.value = false
  }

  // Round 2 P6 — surgical dismiss for the failed-banner "Fermer" action: clears
  // the displayed error and resets paymentState, but PRESERVES the sessionStorage
  // stash so the user can still click "Continuer le paiement" after a timeout or
  // transient failure (per spec Resolved decision #5: "NOT cleared on failed").
  function dismissPaymentError(): void {
    error.value = null
    paymentState.value = 'idle'
  }

  onUnmounted(() => {
    stopPolling()
  })

  return {
    isInitiating,
    isPolling,
    isVerifying,
    pendingCheckoutAvailable,
    paymentState,
    error,
    initiatePayment,
    resumePayment,
    verifyPayment,
    stopPolling,
    dismissPaymentError,
    reset,
  }
}
