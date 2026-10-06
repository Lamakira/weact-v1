import { computed, onUnmounted, ref, type ComputedRef, type Ref } from 'vue'
import { useRoute, useRouter, type LocationQuery } from 'vue-router'
import { bookingApi } from '@/features/booking/services/bookingApi'
import { BookingStatus } from '@/features/booking/types'
import { missionApi } from '@/features/mission/services/missionApi'
import { MissionStatus } from '@/features/mission/types'
import { candidatureApi } from '@/features/candidature/services/candidatureApi'
import { faceApi } from '@/features/face/services/faceApi'
import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'
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
        if (verify.data.status === 'active') return 'settled'
        if (verify.data.status === 'failed') return 'failed'
        const refreshed = subscriptionStatus?.current.value?.status
        if (refreshed === 'failed') return 'failed'
        // verify returns 'free' when no pending row is left: the webhook got there first.
        if (verify.data.status === 'free' && refreshed === 'active') return 'settled'
        return 'pending'
      }
    }
  }

  function clearTimer(): void {
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
  }

  async function cleanUrl(): Promise<void> {
    const query = { ...route.query }
    for (const key of RETURN_QUERY_KEYS) delete query[key]
    await router.replace({ query })
  }

  async function finish(k: PaymentReturnKind, outcome: PaymentReturnState): Promise<void> {
    clearTimer()
    state.value = outcome
    if (outcome === 'confirmed') {
      try {
        await options.onConfirmed?.(k)
      } catch {
        // The payment IS confirmed — a failed refetch must not turn it into an error.
      }
      toast.success(SUCCESS_MESSAGES[k])
    }
    await cleanUrl()
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
    // Display hint only (never a state change): FedaPay says the user cancelled or
    // was declined → show the failure right away instead of polling to the timeout.
    // `approved` / absent / unknown keep the normal polling.
    const hint = firstString(route.query.fedapay_status)
    if (hint === 'canceled' || hint === 'declined') {
      await finish(k, 'failed')
      return true
    }
    state.value = 'verifying'
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

  return { state, kind, isVerifying, hasPendingReturn, start, retry, dismiss }
}
