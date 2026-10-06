import { computed, onDeactivated, onUnmounted, ref, type ComputedRef, type Ref } from 'vue'
import { useRoute, useRouter, type LocationQuery } from 'vue-router'
import { bookingApi } from '@/features/booking/services/bookingApi'
import { BookingStatus } from '@/features/booking/types'
import { missionApi } from '@/features/mission/services/missionApi'
import { MissionStatus } from '@/features/mission/types'
import { candidatureApi } from '@/features/candidature/services/candidatureApi'
import { faceApi } from '@/features/face/services/faceApi'
import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'
import {
  clearSubscriptionPaymentSnapshot,
  readSubscriptionPaymentSnapshot,
} from '@/features/face/services/subscriptionPaymentSnapshot'
import { useToast } from '@/composables/useToast'

const POLL_INTERVAL_MS = 5000
const POLL_TIMEOUT_MS = 120000
// First check is immediate, then one per interval until the timeout is reached.
const MAX_CHECKS = POLL_TIMEOUT_MS / POLL_INTERVAL_MS + 1

/** Query keys owned by the return flow — removed from the URL once it is over. */
const RETURN_QUERY_KEYS = ['payment_return', 'mission', 'candidature', 'fedapay_status'] as const

export const PAYMENT_RETURN_KINDS = [
  'booking',
  'booking_commission',
  'mission_commission',
  'candidature_escrow',
  'mission_selection',
  'subscription',
] as const

export type PaymentReturnKind = (typeof PAYMENT_RETURN_KINDS)[number]
export type PaymentReturnState = 'idle' | 'verifying' | 'confirmed' | 'failed' | 'timeout'
type CheckOutcome = 'settled' | 'failed' | 'pending'

export interface PaymentReturnIds {
  bookingId?: string | null
  missionId?: string | null
  candidatureId?: string | null
}

export interface UsePaymentReturnOptions {
  /** Kinds this page is a destination for; any other `payment_return` is ignored. */
  kinds: PaymentReturnKind[]
  /** Ids known by the page (route params). `?mission=` / `?candidature=` override them. */
  ids?: () => PaymentReturnIds
  /** Called after a confirmed payment, before the success toast (refetch page data). */
  onConfirmed?: (kind: PaymentReturnKind) => void | Promise<void>
  /**
   * Called once the check is over, whatever the outcome (after the URL cleanup): lets
   * the page re-enable its own pending-state evaluation (a late webhook still updates it).
   */
  onFinished?: (kind: PaymentReturnKind, outcome: 'confirmed' | 'failed' | 'timeout') => void
  /** « Réessayer le paiement » — restarts the payment for this kind. */
  onRetry?: (kind: PaymentReturnKind, ids: PaymentReturnIds) => void
}

interface UsePaymentReturnReturn {
  state: Ref<PaymentReturnState>
  kind: Ref<PaymentReturnKind | null>
  isVerifying: ComputedRef<boolean>
  /** True when the current URL carries a `payment_return` this page handles. */
  hasPendingReturn: ComputedRef<boolean>
  start: () => Promise<boolean>
  retry: () => void
  dismiss: () => void
}

export const PAYMENT_RETURN_MESSAGES = {
  verifying: 'Vérification de votre paiement…',
  timeout:
    'Votre paiement est en cours de confirmation. Vous serez notifié dès qu\'il est validé.',
  failed: 'Paiement annulé ou refusé. Vous pouvez réessayer.',
} as const

const SUCCESS_MESSAGES: Record<PaymentReturnKind, string> = {
  booking: 'Paiement confirmé. Votre booking est payé.',
  booking_commission: 'Commission payée. La Face va recevoir votre demande.',
  mission_commission: 'Commission payée. Votre mission est publiée.',
  candidature_escrow: 'Paiement confirmé. La candidature est acceptée.',
  mission_selection: 'Paiement confirmé. Votre sélection est validée.',
  subscription: 'Votre abonnement est activé.',
}

const BOOKING_DEAD_STATUSES: string[] = [
  BookingStatus.REFUSED,
  BookingStatus.EXPIRED,
  BookingStatus.CANCELLED_BY_PRODUCER,
  BookingStatus.CANCELLED_BY_FACE,
]

function firstString(value: LocationQuery[string] | undefined): string | null {
  const v = Array.isArray(value) ? value[0] : value
  return typeof v === 'string' && v !== '' ? v : null
}

/**
 * Handles the browser coming back from a same-tab FedaPay checkout.
 *
 * The backend return handler routes to the initiating page with
 * `?payment_return=<kind>` (+ ids). On mount the page calls `start()`: it polls
 * the endpoint matching the kind every 5 s for up to 120 s (the endpoints
 * self-heal against FedaPay, so a late webhook does not matter), then toasts /
 * surfaces the failure / reports a non-error timeout, and finally strips the
 * return keys from the URL so a reload does not re-run the verification.
 * The only FedaPay-originated value read is `fedapay_status` (whitelisted by the
 * backend), used purely as a display hint to fail fast on canceled/declined; the
 * payment state itself is only ever decided by the server endpoints.
 */
export function usePaymentReturn(options: UsePaymentReturnOptions): UsePaymentReturnReturn {
  const route = useRoute()
  const router = useRouter()
  const toast = useToast()
  const subscriptionStatus = options.kinds.includes('subscription')
    ? useSubscriptionStatus()
    : null

  const state = ref<PaymentReturnState>('idle')
  const kind = ref<PaymentReturnKind | null>(null)

  let timer: ReturnType<typeof setTimeout> | null = null
  let cancelled = false
  let lastIds: PaymentReturnIds = {}
  let startPath: string | undefined

  const isVerifying = computed(() => state.value === 'verifying')

  function requestedKind(): PaymentReturnKind | null {
    const raw = firstString(route.query.payment_return)
    if (!raw) return null
    const candidate = raw as PaymentReturnKind
    return options.kinds.includes(candidate) ? candidate : null
  }

  const hasPendingReturn = computed(() => requestedKind() !== null)

  function resolveIds(): PaymentReturnIds {
    const base = options.ids?.() ?? {}
    return {
      bookingId: base.bookingId ?? null,
      missionId: firstString(route.query.mission) ?? base.missionId ?? null,
      candidatureId: firstString(route.query.candidature) ?? base.candidatureId ?? null,
    }
  }

  async function check(k: PaymentReturnKind, ids: PaymentReturnIds): Promise<CheckOutcome> {
    switch (k) {
      case 'booking': {
        const { data } = await bookingApi.checkPaymentStatus(ids.bookingId as string)
        if (data.status === BookingStatus.ACCEPTED) return 'pending'
        return BOOKING_DEAD_STATUSES.includes(data.status) ? 'failed' : 'settled'
      }
      case 'booking_commission': {
        const { data, commission_payment_status } = await bookingApi.checkCommissionStatus(
          ids.bookingId as string,
        )
        if (data.status === BookingStatus.COMMISSION_PAID) return 'settled'
        if (commission_payment_status === 'failed') return 'failed'
        if (BOOKING_DEAD_STATUSES.includes(data.status)) return 'failed'
        return data.status === BookingStatus.PENDING ? 'pending' : 'settled'
      }
      case 'mission_commission': {
        const { data, commission_payment_status } = await missionApi.getCommissionStatus(
          ids.missionId as string,
        )
        if (data.status === MissionStatus.PUBLISHED) return 'settled'
        if (commission_payment_status === 'failed') return 'failed'
        return 'pending'
      }
      case 'candidature_escrow': {
        const res = await candidatureApi.getCandidaturePaymentStatus(ids.candidatureId as string)
        if (res.data.candidature_status === 'accepted' || res.data.payment_status === 'paid') {
          return 'settled'
        }
        // Webhook already resolved the failure (entry removed) or provider terminal status.
        if (res.data.payment_status === 'failed' || !res.data.is_trackable) return 'failed'
        return 'pending'
      }
      case 'mission_selection': {
        const { data } = await missionApi.getPaymentStatus(ids.missionId as string)
        if (data.status === 'paid') return 'settled'
        if (data.status === 'failed' || data.status === 'refunded') return 'failed'
        if (!data.has_payment || !data.is_trackable) return 'failed'
        return 'pending'
      }
      case 'subscription': {
        const verify = await faceApi.verifySubscriptionPayment()
        await subscriptionStatus?.refreshStatus()
        if (verify.data.status === 'failed') return 'failed'

        const current = subscriptionStatus?.current.value
        if (current?.status === 'failed') return 'failed'

        const snapshot = readSubscriptionPaymentSnapshot()
        if (snapshot === null) {
          // No pre-redirect snapshot (storage cleared / other device): trust only
          // verify-payment's own answer about the row it just processed.
          return verify.data.status === 'active' ? 'settled' : 'pending'
        }

        // The status endpoint reports the OLD active row for a renewal/upgrade whose
        // payment failed: only a change vs the snapshot proves the activation.
        if (current?.status !== 'active') return 'pending'
        const expiresMovedForward =
          current.expires_at !== null &&
          (snapshot.expires_at === null ||
            new Date(current.expires_at).getTime() > new Date(snapshot.expires_at).getTime())
        return current.tier !== snapshot.tier || expiresMovedForward ? 'settled' : 'pending'
      }
    }
  }

  function clearTimer(): void {
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
  }

  async function cleanUrl(startPath: string | undefined): Promise<void> {
    // Only strip the params while still on the route that started the check
    // (keep-alive / navigation must never get another route's query replaced).
    if (route.path !== startPath) return
    const query = { ...route.query }
    for (const key of RETURN_QUERY_KEYS) delete query[key]
    await router.replace({ query })
  }

  async function finish(k: PaymentReturnKind, outcome: PaymentReturnState): Promise<void> {
    clearTimer()
    state.value = outcome
    if (k === 'subscription') clearSubscriptionPaymentSnapshot()
    if (outcome === 'confirmed') {
      try {
        await options.onConfirmed?.(k)
      } catch {
        // The payment IS confirmed — a failed refetch must not turn it into an error.
      }
      if (cancelled) return
      toast.success(SUCCESS_MESSAGES[k])
    }
    await cleanUrl(startPath)
    options.onFinished?.(k, outcome as 'confirmed' | 'failed' | 'timeout')
  }

  async function runCheck(k: PaymentReturnKind, ids: PaymentReturnIds, attempt: number): Promise<void> {
    if (cancelled) return
    let outcome: CheckOutcome = 'pending'
    try {
      outcome = await check(k, ids)
    } catch {
      // Transient error: keep polling.
    }
    if (cancelled) return

    if (outcome === 'settled') return finish(k, 'confirmed')
    if (outcome === 'failed') return finish(k, 'failed')
    if (attempt >= MAX_CHECKS) return finish(k, 'timeout')

    timer = setTimeout(() => {
      void runCheck(k, ids, attempt + 1)
    }, POLL_INTERVAL_MS)
  }

  async function start(): Promise<boolean> {
    const k = requestedKind()
    if (!k) return false
    if (state.value === 'verifying') return true

    cancelled = false
    kind.value = k
    lastIds = resolveIds()
    startPath = route.path
    // Display hint only (never a state change): FedaPay says the user cancelled or was
    // declined. The server is still asked ONCE (a crafted link must not show a false
    // failure, and the self-heal endpoints also clean the server side up): settled →
    // success, anything else → failure right away instead of polling to the timeout.
    // `approved` / absent / unknown keep the normal polling.
    const hint = firstString(route.query.fedapay_status)
    state.value = 'verifying'
    if (hint === 'canceled' || hint === 'declined') {
      let outcome: CheckOutcome = 'failed'
      try {
        outcome = await check(k, lastIds)
      } catch {
        // Unreachable server: trust the hint for display only.
      }
      if (cancelled) return true
      await finish(k, outcome === 'settled' ? 'confirmed' : 'failed')
      return true
    }
    await runCheck(k, lastIds, 1)
    return true
  }

  function dismiss(): void {
    clearTimer()
    state.value = 'idle'
  }

  function retry(): void {
    const k = kind.value
    state.value = 'idle'
    if (k) options.onRetry?.(k, lastIds)
  }

  onUnmounted(() => {
    cancelled = true
    clearTimer()
  })

  // Under <keep-alive> the page survives a navigation: stop polling when it is
  // deactivated (a late webhook still finishes the payment server-side).
  onDeactivated(() => {
    cancelled = true
    clearTimer()
    if (state.value === 'verifying') state.value = 'idle'
  })

  return { state, kind, isVerifying, hasPendingReturn, start, retry, dismiss }
}
