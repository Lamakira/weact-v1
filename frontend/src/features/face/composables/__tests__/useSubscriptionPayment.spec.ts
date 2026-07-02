import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useSubscriptionPayment } from '../useSubscriptionPayment'
import { useSubscriptionStatus } from '../useSubscriptionStatus'
import { faceApi } from '../../services/faceApi'
import { resetSharedCachedResourcesForTests } from '@/lib/createSharedCachedResource'
import type {
  FaceSubscriptionPlan,
  FaceSubscriptionTier,
  SubscriptionInitiatePaymentResponse,
  SubscriptionStatusData,
  SubscriptionStatusValue,
  SubscriptionVerifyPaymentResponse,
  TierCapabilities,
} from '../../types'

vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getSubscriptionStatus: vi.fn(),
    initiateSubscriptionPayment: vi.fn(),
    verifySubscriptionPayment: vi.fn(),
    cancelPendingSubscription: vi.fn(),
    resumePendingSubscription: vi.fn(),
  },
}))

vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn(() => 'Un paiement est déjà en cours pour cet abonnement.'),
}))

const CAPS: TierCapabilities = {
  max_album_photos: 4,
  max_presentation_videos: 1,
  max_acting_videos: 1,
  max_ugc_videos: 0,
  ugc_access: true,
  commission_rate: 0.1,
  sort_priority: 2,
  has_elite_badge: false,
}

function statusData(
  tier: FaceSubscriptionTier,
  status: SubscriptionStatusValue,
  expiresAt: string | null,
): SubscriptionStatusData {
  return {
    current: {
      tier,
      plan: tier === 'free' ? null : (tier as FaceSubscriptionPlan),
      status,
      starts_at: null,
      expires_at: expiresAt,
      cancelled_at: null,
      capabilities: CAPS,
    },
    offers: [],
    cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
  }
}

function initiateResponse(plan: FaceSubscriptionPlan): SubscriptionInitiatePaymentResponse {
  return {
    data: {
      subscription_id: 'sub_pending',
      status: 'pending_payment',
      plan,
      checkout_url: 'https://checkout.fedapay.test/sess_abc',
      amount: 25000,
      currency: 'XOF',
      forfeited_days: 0,
    },
    message: 'Redirection vers le paiement...',
  }
}

function verifyResponse(status: SubscriptionStatusValue): SubscriptionVerifyPaymentResponse {
  return { data: { subscription_id: 'sub_pending', status } }
}

function mountWithComposable(): {
  api: ReturnType<typeof useSubscriptionPayment>
  unmount: () => void
} {
  let exposed: ReturnType<typeof useSubscriptionPayment> | undefined
  const Wrapper = defineComponent({
    setup() {
      exposed = useSubscriptionPayment()
      return () => h('div')
    },
  })
  const wrapper = mount(Wrapper)
  return {
    api: exposed as ReturnType<typeof useSubscriptionPayment>,
    unmount: () => wrapper.unmount(),
  }
}

describe('useSubscriptionPayment (FP-2.7 tier-aware contract)', () => {
  let openSpy: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    vi.clearAllMocks()
    resetSharedCachedResourcesForTests()
    sessionStorage.clear()
    vi.useFakeTimers()
    openSpy = vi.spyOn(window, 'open').mockImplementation(() => ({}) as Window)
  })

  afterEach(() => {
    vi.useRealTimers()
    openSpy.mockRestore()
  })

  it('initiatePayment(plan) opens the checkout tab, sets waiting state and starts polling', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment('pro')

    expect(result).toBe(true)
    expect(faceApi.initiateSubscriptionPayment).toHaveBeenCalledWith('pro')
    expect(openSpy).toHaveBeenCalledWith(
      'https://checkout.fedapay.test/sess_abc',
      '_blank',
      'noopener,noreferrer',
    )
    expect(api.paymentState.value).toBe('waiting')
    expect(api.isPolling.value).toBe(true)

    unmount()
  })

  it('confirms a payment when polling detects an active status with a changed tier (free → pro)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('active'))
    vi.mocked(faceApi.getSubscriptionStatus)
      .mockResolvedValueOnce({ data: statusData('free', 'pending_payment', null) })
      .mockResolvedValueOnce({ data: statusData('pro', 'active', '2027-05-22T00:00:00Z') })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    await vi.advanceTimersByTimeAsync(5000)

    expect(api.paymentState.value).toBe('confirmed')
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('confirms a same-tier renewal — tier unchanged but expires_at advanced (decision #7)', async () => {
    const before = '2026-06-01T00:00:00Z'
    const after = '2027-06-01T00:00:00Z'
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('active'))
    vi.mocked(faceApi.getSubscriptionStatus)
      .mockResolvedValueOnce({ data: statusData('pro', 'active', before) }) // initial seed
      .mockResolvedValueOnce({ data: statusData('pro', 'active', before) }) // refresh in initiate
      .mockResolvedValueOnce({ data: statusData('pro', 'active', after) }) // refresh in poll

    // Seed the shared status singleton with the active-Pro baseline.
    await useSubscriptionStatus().fetchStatus()

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    await vi.advanceTimersByTimeAsync(5000)

    expect(api.paymentState.value).toBe('confirmed')
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('fails the payment when polling detects status=failed', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('failed'))
    vi.mocked(faceApi.getSubscriptionStatus)
      .mockResolvedValueOnce({ data: statusData('free', 'pending_payment', null) })
      .mockResolvedValueOnce({ data: statusData('free', 'failed', null) })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    await vi.advanceTimersByTimeAsync(5000)

    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toBe('Le paiement a échoué. Veuillez réessayer.')
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('fails the payment after the 120 s polling timeout', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('pending_payment'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    await vi.advanceTimersByTimeAsync(120_000)

    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toContain('délai de confirmation a expiré')
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('returns false and does not poll when the checkout popup is blocked', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    openSpy.mockReturnValue(null)

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment('pro')

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toBe(
      'La fenêtre de paiement a été bloquée. Autorisez les popups puis réessayez.',
    )
    expect(api.isPolling.value).toBe(false)
    expect(faceApi.getSubscriptionStatus).not.toHaveBeenCalled()

    unmount()
  })

  it('does not initiate a second payment while an initiation is in progress', async () => {
    let resolveInitiation: ((value: SubscriptionInitiatePaymentResponse) => void) | undefined
    vi.mocked(faceApi.initiateSubscriptionPayment).mockReturnValue(
      new Promise((resolve) => {
        resolveInitiation = resolve
      }),
    )
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    const first = api.initiatePayment('pro')
    const second = await api.initiatePayment('pro')

    expect(second).toBe(false)
    expect(faceApi.initiateSubscriptionPayment).toHaveBeenCalledOnce()

    resolveInitiation?.(initiateResponse('pro'))
    await first
    unmount()
  })

  it('does not initiate a second payment while polling is active', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    const second = await api.initiatePayment('pro')

    expect(second).toBe(false)
    expect(faceApi.initiateSubscriptionPayment).toHaveBeenCalledOnce()

    unmount()
  })

  it('surfaces the formatted backend error when initiatePayment rejects', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockRejectedValue(new Error('409 conflict'))

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment('pro')

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toBe('Un paiement est déjà en cours pour cet abonnement.')
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('reset() clears ephemeral state and stops polling', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')

    api.reset()

    expect(api.isInitiating.value).toBe(false)
    expect(api.isPolling.value).toBe(false)
    expect(api.paymentState.value).toBe('idle')
    expect(api.error.value).toBeNull()

    unmount()
  })

  it('stops the poller when the consuming component unmounts (onUnmounted safety)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('pending_payment'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    expect(api.isPolling.value).toBe(true)

    unmount()

    const callsBefore = vi.mocked(faceApi.getSubscriptionStatus).mock.calls.length
    await vi.advanceTimersByTimeAsync(30_000)
    const callsAfter = vi.mocked(faceApi.getSubscriptionStatus).mock.calls.length
    expect(callsAfter).toBe(callsBefore)
  })

  it('does not overwrite paymentState=failed when an in-flight poll resolves after the timeout fires (P4 race guard)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))

    // Two getSubscriptionStatus calls: (1) the refresh inside initiatePayment;
    // (2) the refresh inside the in-flight verifyPayment, which resolves after the timeout.
    // The 2nd would normally flip paymentState to 'confirmed' (tier free → pro) — the P4
    // guard must short-circuit that mutation because polling has already stopped.
    vi.mocked(faceApi.getSubscriptionStatus)
      .mockResolvedValueOnce({ data: statusData('free', 'pending_payment', null) })
      .mockResolvedValueOnce({ data: statusData('pro', 'active', '2027-01-01T00:00:00Z') })

    // Hang the first verify call so we can resolve it manually after the timeout fires.
    let resolveVerify!: (response: SubscriptionVerifyPaymentResponse) => void
    vi.mocked(faceApi.verifySubscriptionPayment).mockImplementationOnce(
      () =>
        new Promise<SubscriptionVerifyPaymentResponse>((resolve) => {
          resolveVerify = resolve
        }),
    )

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    expect(api.isPolling.value).toBe(true)
    expect(api.paymentState.value).toBe('waiting')

    // Tick the first poll at T=5s — verifyPayment is now hanging on its first await.
    await vi.advanceTimersByTimeAsync(5_000)
    expect(api.isVerifying.value).toBe(true)

    // Advance to the 120 s timeout — paymentState flips to 'failed' and polling stops.
    await vi.advanceTimersByTimeAsync(115_000)
    expect(api.isPolling.value).toBe(false)
    expect(api.paymentState.value).toBe('failed')

    // Resolve the hung verify with a state that would otherwise be "confirmed".
    resolveVerify(verifyResponse('active'))
    await flushPromises()

    // P4 — the bail-out check must short-circuit the mutation; paymentState stays 'failed'.
    expect(api.paymentState.value).toBe('failed')

    unmount()
  })

  it('surfaces an error toast on a manual verifyPayment failure but stays silent on a polling failure (P11)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    vi.mocked(faceApi.verifySubscriptionPayment).mockRejectedValue(
      new Error('Network error during verify'),
    )

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')

    // Polling tick at T=5s — verifyPayment rejects, but polling swallows the error silently.
    const errorBefore = api.error.value
    await vi.advanceTimersByTimeAsync(5_000)
    expect(api.error.value).toBe(errorBefore) // unchanged

    // Manual call surfaces the formatted error.
    await api.verifyPayment({ manual: true })
    expect(api.error.value).toBe('Un paiement est déjà en cours pour cet abonnement.')

    unmount()
  })

  it('rejects a re-entrant verifyPayment while one is already in flight (P5 double-click guard)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    // Hang the verify so the second call hits the guard while the first is in flight.
    let resolveVerify!: (response: SubscriptionVerifyPaymentResponse) => void
    vi.mocked(faceApi.verifySubscriptionPayment).mockImplementationOnce(
      () =>
        new Promise<SubscriptionVerifyPaymentResponse>((resolve) => {
          resolveVerify = resolve
        }),
    )

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')

    // First manual click hangs.
    void api.verifyPayment({ manual: true })
    await flushPromises()
    expect(api.isVerifying.value).toBe(true)

    // Second click while in-flight is a no-op — verifySubscriptionPayment is NOT re-called.
    await api.verifyPayment({ manual: true })
    expect(vi.mocked(faceApi.verifySubscriptionPayment).mock.calls.length).toBe(1)

    // Resolve the first one to clean up.
    resolveVerify(verifyResponse('pending_payment'))
    await flushPromises()

    unmount()
  })

  it('does NOT confirm a manual verify when no payment was armed in this session (Findings #1 armed-snapshot guard)', async () => {
    // User is already on active Pro from a previous session. A separate pending
    // Élite row exists (initiated from another device). On this device, the
    // composable's snapshot is still the default {free, null} because no
    // initiatePayment / resumePayment ran here. Without the armed guard, the
    // manual verify would falsely emit 'confirmed' because current.tier='pro'
    // differs from snapshot.tier='free'.
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('pending_payment'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('pro', 'active', '2027-01-01T00:00:00Z'),
    })

    const { api, unmount } = mountWithComposable()
    await useSubscriptionStatus().fetchStatus() // seed current = Pro/active

    expect(api.paymentState.value).toBe('idle')

    await api.verifyPayment({ manual: true })

    // Without arming, the verify must NOT flip paymentState — it just refreshes.
    expect(api.paymentState.value).toBe('idle')

    unmount()
  })

  // Round 2 P3 — symmetric guard: verifyPayment must bail when isInitiating is
  // true so an in-flight initiate-then-Fedapay-await cannot race a manual verify.
  it('verifyPayment bails out when isInitiating is true (Round 2 P3)', async () => {
    // Lock the initiate in an unresolved promise so isInitiating stays true.
    let resolveInitiate: (value: SubscriptionInitiatePaymentResponse) => void = () => undefined
    vi.mocked(faceApi.initiateSubscriptionPayment).mockReturnValue(
      new Promise<SubscriptionInitiatePaymentResponse>((resolve) => {
        resolveInitiate = resolve
      }),
    )
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('active'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    const initiatePromise = api.initiatePayment('pro')
    await flushPromises()
    expect(api.isInitiating.value).toBe(true)

    await api.verifyPayment({ manual: true })
    expect(faceApi.verifySubscriptionPayment).not.toHaveBeenCalled()

    // Cleanup — unblock the initiate.
    resolveInitiate(initiateResponse('pro'))
    await initiatePromise
    unmount()
  })

  // Round 2 D3 — the composable re-arms hasArmedPayment on mount whenever the
  // current status is 'pending_payment'. Without this, a user returning after a
  // page refresh would click "Vérifier maintenant" and see no terminal feedback
  // when the backend has already confirmed the payment.
  it('arms hasArmedPayment on mount when statusValue is pending_payment (Round 2 D3)', async () => {
    // Seed the shared status with a pending row before mounting the composable.
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    await useSubscriptionStatus().fetchStatus()

    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('active'))
    // After verify, status flips to active Pro — the diff vs the snapshot {free,null}
    // captured at mount triggers isConfirmed() → paymentState='confirmed'.
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValueOnce({
      data: statusData('pro', 'active', '2027-05-23T00:00:00Z'),
    })

    const { api, unmount } = mountWithComposable()
    await flushPromises()

    await api.verifyPayment({ manual: true })

    expect(api.paymentState.value).toBe('confirmed')
    unmount()
  })
})

describe('cancelPending + visibility-aware auto-verify (FP-2.15.1)', () => {
  let openSpy: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    vi.clearAllMocks()
    resetSharedCachedResourcesForTests()
    sessionStorage.clear()
    vi.useFakeTimers()
    openSpy = vi.spyOn(window, 'open').mockImplementation(() => ({}) as Window)
    // Default JSDOM document.visibilityState to 'visible' for cleanliness across tests.
    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      get: () => 'visible',
    })
  })

  afterEach(() => {
    vi.useRealTimers()
    openSpy.mockRestore()
  })

  it('T1 — cancelPending() POSTs cancel-pending, refreshes status, flips paymentState to idle', async () => {
    // Real-world entry point for the cancel button: the user is on a surface that
    // hosts the pending banner (the Facturation tab / pricing page), paymentState='idle'
    // so the banner v-else-if cascade shows the pending state. Polling is not active.
    vi.mocked(faceApi.cancelPendingSubscription).mockResolvedValue({
      data: { subscription_id: 'sub_pending', status: 'failed' as SubscriptionStatusValue },
      message: 'Paiement annulé.',
    })
    vi.mocked(faceApi.getSubscriptionStatus)
      .mockResolvedValueOnce({ data: statusData('free', 'pending_payment', null) }) // initial seed
      .mockResolvedValueOnce({ data: statusData('free', 'failed', null) }) // post-cancel refresh

    // Seed singleton so the Round 2 D3 watch arms hasArmedPayment on mount.
    await useSubscriptionStatus().fetchStatus()

    const { api, unmount } = mountWithComposable()
    await flushPromises()

    const ok = await api.cancelPending()

    expect(ok).toBe(true)
    expect(faceApi.cancelPendingSubscription).toHaveBeenCalledOnce()
    expect(api.paymentState.value).toBe('idle')
    expect(api.isPolling.value).toBe(false)
    expect(api.error.value).toBeNull()
    expect(api.isCancelling.value).toBe(false)

    unmount()
  })

  it('T2 — cancelPending() returns false and sets error.value on a 404 NO_PENDING_PAYMENT response', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    vi.mocked(faceApi.cancelPendingSubscription).mockRejectedValue(
      new Error('Request failed with status code 404'),
    )

    const { api, unmount } = mountWithComposable()
    const ok = await api.cancelPending()

    expect(ok).toBe(false)
    expect(api.error.value).toBe('Un paiement est déjà en cours pour cet abonnement.')
    expect(api.isCancelling.value).toBe(false)

    unmount()
  })

  it('T3 — cancelPending() returns false and sets error.value on a network error', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    vi.mocked(faceApi.cancelPendingSubscription).mockRejectedValue(new Error('Network Error'))

    const { api, unmount } = mountWithComposable()
    const ok = await api.cancelPending()

    expect(ok).toBe(false)
    expect(api.error.value).not.toBeNull()
    expect(api.isCancelling.value).toBe(false)

    unmount()
  })

  it('T4 — verifyPayment() / initiatePayment() / resumePayment() bail out when isCancelling is true', async () => {
    let resolveCancel: (value: {
      data: { subscription_id: string; status: SubscriptionStatusValue }
      message?: string
    }) => void = () => undefined
    vi.mocked(faceApi.cancelPendingSubscription).mockReturnValue(
      new Promise((resolve) => {
        resolveCancel = resolve
      }),
    )
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    const cancelPromise = api.cancelPending()
    await flushPromises()
    expect(api.isCancelling.value).toBe(true)

    const initiateResult = await api.initiatePayment('pro')
    expect(initiateResult).toBe(false)
    expect(faceApi.initiateSubscriptionPayment).not.toHaveBeenCalled()

    const resumeResult = await api.resumePayment()
    expect(resumeResult).toBe(false)
    expect(faceApi.resumePendingSubscription).not.toHaveBeenCalled()

    await api.verifyPayment({ manual: true })
    expect(faceApi.verifySubscriptionPayment).not.toHaveBeenCalled()

    resolveCancel({ data: { subscription_id: 'sub_pending', status: 'failed' }, message: 'OK' })
    await cancelPromise

    unmount()
  })

  it('T5 — cancelPending() bails out (returns false) when isInitiating is true (FP-2.15.1 L2: isPolling intentionally no longer in the guard)', async () => {
    let resolveInitiate: (value: SubscriptionInitiatePaymentResponse) => void = () => undefined
    vi.mocked(faceApi.initiateSubscriptionPayment).mockReturnValue(
      new Promise<SubscriptionInitiatePaymentResponse>((resolve) => {
        resolveInitiate = resolve
      }),
    )
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    const initiatePromise = api.initiatePayment('pro')
    await flushPromises()
    expect(api.isInitiating.value).toBe(true)

    const cancelResult = await api.cancelPending()
    expect(cancelResult).toBe(false)
    expect(faceApi.cancelPendingSubscription).not.toHaveBeenCalled()

    resolveInitiate(initiateResponse('pro'))
    await initiatePromise
    unmount()
  })

  it('T10 — cancelPending() called during paymentState=waiting stops polling, flips to idle, and reaches the backend (FP-2.15.1 L2)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('pending_payment'))
    vi.mocked(faceApi.cancelPendingSubscription).mockResolvedValue({
      data: { subscription_id: 'sub_pending', status: 'failed' as SubscriptionStatusValue },
      message: 'Paiement annulé.',
    })
    vi.mocked(faceApi.getSubscriptionStatus)
      .mockResolvedValueOnce({ data: statusData('free', 'pending_payment', null) })
      .mockResolvedValueOnce({ data: statusData('free', 'failed', null) })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    expect(api.isPolling.value).toBe(true)
    expect(api.paymentState.value).toBe('waiting')

    const verifyCallsBefore = vi.mocked(faceApi.verifySubscriptionPayment).mock.calls.length

    const ok = await api.cancelPending()

    expect(ok).toBe(true)
    expect(faceApi.cancelPendingSubscription).toHaveBeenCalledOnce()
    expect(api.isPolling.value).toBe(false)
    expect(api.paymentState.value).toBe('idle')

    // Polling is dead — no further verify calls fire after cancel.
    await vi.advanceTimersByTimeAsync(10_000)
    expect(vi.mocked(faceApi.verifySubscriptionPayment).mock.calls.length).toBe(verifyCallsBefore)

    unmount()
  })

  it('T6 — visibilitychange auto-fires verifyPayment after the 120s polling timeout when document becomes visible and the pending-banner predicate is true', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('pending_payment'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    await vi.advanceTimersByTimeAsync(120_000)

    expect(api.paymentState.value).toBe('failed')
    expect(api.isPolling.value).toBe(false)

    // Re-mock for the visibility-triggered verify — backend has now confirmed.
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('active'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('pro', 'active', '2027-05-23T00:00:00Z'),
    })

    const verifyCallsBefore = vi.mocked(faceApi.verifySubscriptionPayment).mock.calls.length
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(vi.mocked(faceApi.verifySubscriptionPayment).mock.calls.length).toBe(
      verifyCallsBefore + 1,
    )
    expect(api.paymentState.value).toBe('confirmed')

    unmount()
  })

  it('T7 — visibilitychange does NOT fire verifyPayment when document becomes hidden', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('pending_payment'))

    // Seed the singleton so the Round 2 D3 watch arms hasArmedPayment on mount.
    await useSubscriptionStatus().fetchStatus()

    const { unmount } = mountWithComposable()
    await flushPromises()

    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      get: () => 'hidden',
    })
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(faceApi.verifySubscriptionPayment).not.toHaveBeenCalled()

    unmount()
  })

  it('T8 — visibilitychange does NOT fire verifyPayment when statusValue is active and CTA is not all-false (no pending banner / false-positive guard)', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: {
        current: {
          tier: 'pro',
          plan: 'pro',
          status: 'active',
          starts_at: null,
          expires_at: '2026-06-30T00:00:00Z',
          cancelled_at: null,
          capabilities: CAPS,
        },
        offers: [],
        cta: { upgrade_available: true, downgrade_available: false, renew_available: true },
      },
    })
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('active'))

    await useSubscriptionStatus().fetchStatus()

    const { api, unmount } = mountWithComposable()
    await flushPromises()

    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(faceApi.verifySubscriptionPayment).not.toHaveBeenCalled()
    expect(api.paymentState.value).toBe('idle')

    unmount()
  })

  it('T9 — visibilitychange auto-fires verifyPayment when statusValue is active but CTA is all-false (active + pending tier-change)', async () => {
    // Active Pro with a pending Élite tier change → representative statusValue stays
    // 'active' but FP-2.3 forces CTA all-false because a pending row exists.
    const activeProCtaForced = {
      current: {
        tier: 'pro' as FaceSubscriptionTier,
        plan: 'pro' as FaceSubscriptionPlan,
        status: 'active' as SubscriptionStatusValue,
        starts_at: null,
        expires_at: '2027-01-01T00:00:00Z',
        cancelled_at: null,
        capabilities: CAPS,
      },
      offers: [],
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    }

    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('elite'))
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('pending_payment'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: activeProCtaForced })

    await useSubscriptionStatus().fetchStatus()

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('elite')
    expect(api.isPolling.value).toBe(true)

    await vi.advanceTimersByTimeAsync(120_000)
    expect(api.paymentState.value).toBe('failed')

    const activeEliteConfirmed = {
      current: {
        tier: 'elite' as FaceSubscriptionTier,
        plan: 'elite' as FaceSubscriptionPlan,
        status: 'active' as SubscriptionStatusValue,
        starts_at: null,
        expires_at: '2027-05-23T00:00:00Z',
        cancelled_at: null,
        capabilities: { ...CAPS, sort_priority: 1 },
      },
      offers: [],
      cta: { upgrade_available: false, downgrade_available: false, renew_available: false },
    }
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('active'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: activeEliteConfirmed })

    const verifyCallsBefore = vi.mocked(faceApi.verifySubscriptionPayment).mock.calls.length
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(vi.mocked(faceApi.verifySubscriptionPayment).mock.calls.length).toBe(
      verifyCallsBefore + 1,
    )
    expect(api.paymentState.value).toBe('confirmed')

    unmount()
  })
})

describe('resumePayment via backend (FP-2.15.2)', () => {
  let openSpy: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    vi.clearAllMocks()
    resetSharedCachedResourcesForTests()
    sessionStorage.clear()
    vi.useFakeTimers()
    openSpy = vi.spyOn(window, 'open').mockImplementation(() => ({}) as Window)
  })

  afterEach(() => {
    vi.useRealTimers()
    openSpy.mockRestore()
  })

  it('R1 — resumePayment() POSTs resume-payment, opens window with returned URL, starts polling, returns true', async () => {
    vi.mocked(faceApi.resumePendingSubscription).mockResolvedValue({
      data: {
        subscription_id: 'sub_x',
        status: 'pending_payment',
        checkout_url: 'https://checkout.fedapay.test/sess_resumed',
        amount: 25000,
        currency: 'XOF',
      },
      message: 'Reprise du paiement…',
    })
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    const result = await api.resumePayment()

    expect(result).toBe(true)
    expect(faceApi.resumePendingSubscription).toHaveBeenCalledOnce()
    expect(openSpy).toHaveBeenCalledWith(
      'https://checkout.fedapay.test/sess_resumed',
      '_blank',
      'noopener,noreferrer',
    )
    expect(api.paymentState.value).toBe('waiting')
    expect(api.isPolling.value).toBe(true)

    unmount()
  })

  it("R2 — resumePayment() with status='active' from backend (Fedapay-approved race) sets paymentState=confirmed, no window.open, returns true", async () => {
    vi.mocked(faceApi.resumePendingSubscription).mockResolvedValue({
      data: {
        subscription_id: 'sub_x',
        status: 'active',
        checkout_url: null,
        amount: 25000,
        currency: 'XOF',
      },
      message: 'Le paiement a déjà été confirmé.',
    })
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('pro', 'active', '2027-05-23T00:00:00Z'),
    })

    const { api, unmount } = mountWithComposable()
    const result = await api.resumePayment()

    expect(result).toBe(true)
    expect(openSpy).not.toHaveBeenCalled()
    expect(api.paymentState.value).toBe('confirmed')
    expect(api.isPolling.value).toBe(false)
    expect(faceApi.getSubscriptionStatus).toHaveBeenCalled()

    unmount()
  })

  it("R3 — resumePayment() with checkout_url=null and status='pending_payment' (defensive) surfaces error and returns false", async () => {
    vi.mocked(faceApi.resumePendingSubscription).mockResolvedValue({
      data: {
        subscription_id: 'sub_x',
        status: 'pending_payment',
        checkout_url: null,
        amount: 25000,
        currency: 'XOF',
      },
    })
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    const result = await api.resumePayment()

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toContain('Aucune URL')
    expect(openSpy).not.toHaveBeenCalled()
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('R4 — resumePayment() catches a 410 RESUME_NOT_AVAILABLE response, sets error.value, calls refreshStatus, returns false', async () => {
    vi.mocked(faceApi.resumePendingSubscription).mockRejectedValue(
      new Error('Request failed with status code 410'),
    )
    // Backend has flipped the row to Failed during resume; refresh reflects that.
    vi.mocked(faceApi.getSubscriptionStatus)
      .mockResolvedValueOnce({ data: statusData('free', 'pending_payment', null) })
      .mockResolvedValueOnce({ data: statusData('free', 'failed', null) })

    const { api, unmount } = mountWithComposable()
    const result = await api.resumePayment()

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toBe('Un paiement est déjà en cours pour cet abonnement.')
    expect(openSpy).not.toHaveBeenCalled()
    // refreshStatus was called (best-effort) so the pending banner can unmount.
    expect(faceApi.getSubscriptionStatus).toHaveBeenCalled()

    unmount()
  })

  it('R5 — resumePayment() catches a 404 NO_PENDING_PAYMENT response (webhook race), sets error.value, returns false', async () => {
    vi.mocked(faceApi.resumePendingSubscription).mockRejectedValue(
      new Error('Request failed with status code 404'),
    )
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    const result = await api.resumePayment()

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).not.toBeNull()
    expect(openSpy).not.toHaveBeenCalled()

    unmount()
  })

  it('R6 — resumePayment() catches a network error, sets error.value, returns false', async () => {
    vi.mocked(faceApi.resumePendingSubscription).mockRejectedValue(new Error('Network Error'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    const result = await api.resumePayment()

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).not.toBeNull()
    expect(openSpy).not.toHaveBeenCalled()

    unmount()
  })

  it('rejects resumePayment while a manual verify is already in flight (Findings #4 race guard)', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    // Hang the verify so resume tries to run while isVerifying is true.
    let resolveVerify!: (response: SubscriptionVerifyPaymentResponse) => void
    vi.mocked(faceApi.verifySubscriptionPayment).mockImplementationOnce(
      () =>
        new Promise<SubscriptionVerifyPaymentResponse>((resolve) => {
          resolveVerify = resolve
        }),
    )

    const { api, unmount } = mountWithComposable()
    void api.verifyPayment({ manual: true })
    await flushPromises()
    expect(api.isVerifying.value).toBe(true)

    const result = await api.resumePayment()
    expect(result).toBe(false)
    expect(faceApi.resumePendingSubscription).not.toHaveBeenCalled()
    expect(openSpy).not.toHaveBeenCalled()

    resolveVerify(verifyResponse('pending_payment'))
    await flushPromises()

    unmount()
  })

  // Symmetric to initiatePayment's catch of a window.open throw (Round 2 P1):
  // the resume flow wraps the body in try/catch so a sandbox iframe throw cannot
  // strand paymentState in 'waiting' forever. With the backend-driven resume,
  // the API call succeeds first; window.open then throws and is caught.
  it('resumePayment catches a throw from window.open and surfaces a failed state', async () => {
    vi.mocked(faceApi.resumePendingSubscription).mockResolvedValue({
      data: {
        subscription_id: 'sub_x',
        status: 'pending_payment',
        checkout_url: 'https://checkout.fedapay.test/sess_xyz',
        amount: 25000,
        currency: 'XOF',
      },
    })
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    openSpy.mockImplementationOnce(() => {
      throw new Error('Window.open is not allowed in this sandbox')
    })

    const { api, unmount } = mountWithComposable()
    const result = await api.resumePayment()

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.isInitiating.value).toBe(false)
    expect(api.error.value).not.toBeNull()

    unmount()
  })

  it('R7 — resumePayment() bails out when isInitiating / isPolling / isVerifying / isCancelling is true', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    // Case 1 — isInitiating in flight
    let resolveInitiate: (v: SubscriptionInitiatePaymentResponse) => void = () => undefined
    vi.mocked(faceApi.initiateSubscriptionPayment).mockReturnValueOnce(
      new Promise<SubscriptionInitiatePaymentResponse>((r) => {
        resolveInitiate = r
      }),
    )

    const { api, unmount } = mountWithComposable()
    const initiatePromise = api.initiatePayment('pro')
    await flushPromises()
    expect(api.isInitiating.value).toBe(true)

    let result = await api.resumePayment()
    expect(result).toBe(false)
    expect(faceApi.resumePendingSubscription).not.toHaveBeenCalled()

    resolveInitiate(initiateResponse('pro'))
    await initiatePromise
    expect(api.isPolling.value).toBe(true)

    // Case 2 — isPolling already true (after initiate completes, polling is active)
    result = await api.resumePayment()
    expect(result).toBe(false)
    expect(faceApi.resumePendingSubscription).not.toHaveBeenCalled()

    unmount()
  })
})
