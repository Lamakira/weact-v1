import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useSubscriptionPayment } from '../useSubscriptionPayment'
import { useSubscriptionStatus } from '../useSubscriptionStatus'
import { faceApi } from '../../services/faceApi'
import { redirectToCheckout } from '@/lib/redirectToCheckout'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'
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

vi.mock('@/lib/redirectToCheckout', () => ({ redirectToCheckout: vi.fn() }))

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
    resetAllSharedCachedResources()
    sessionStorage.clear()
    vi.useFakeTimers()
    openSpy = vi.spyOn(window, 'open').mockImplementation(() => ({}) as Window)
  })

  afterEach(() => {
    vi.useRealTimers()
    openSpy.mockRestore()
  })

  it('initiatePayment(plan) redirects the same tab to the checkout and sets the waiting (redirecting) state', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment('pro')

    expect(result).toBe(true)
    expect(faceApi.initiateSubscriptionPayment).toHaveBeenCalledWith('pro')
    expect(redirectToCheckout).toHaveBeenCalledWith('https://checkout.fedapay.test/sess_abc')
    expect(openSpy).not.toHaveBeenCalled()
    expect(api.paymentState.value).toBe('waiting')
    expect(api.error.value).toBeNull()

    unmount()
  })

  it('never reports « fenêtre bloquée » — window.open (null with noopener) is no longer used', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    openSpy.mockReturnValue(null)

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment('pro')

    expect(result).toBe(true)
    expect(api.paymentState.value).toBe('waiting')
    expect(api.error.value).toBeNull()
    expect(openSpy).not.toHaveBeenCalled()

    unmount()
  })

  it('does not poll after the redirect', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    await vi.advanceTimersByTimeAsync(130_000)

    expect(faceApi.verifySubscriptionPayment).not.toHaveBeenCalled()

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


  it('surfaces the formatted backend error when initiatePayment rejects', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockRejectedValue(new Error('409 conflict'))

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment('pro')

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toBe('Un paiement est déjà en cours pour cet abonnement.')

    unmount()
  })

  it('reset() clears ephemeral state', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')

    api.reset()

    expect(api.isInitiating.value).toBe(false)
    expect(api.paymentState.value).toBe('idle')
    expect(api.error.value).toBeNull()

    unmount()
  })


  it('surfaces an error on a manual verifyPayment failure but stays silent on a non-manual failure (P11)', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    vi.mocked(faceApi.verifySubscriptionPayment).mockRejectedValue(
      new Error('Network error during verify'),
    )
    await useSubscriptionStatus().fetchStatus()

    const { api, unmount } = mountWithComposable()
    await flushPromises()

    // Non-manual verify (visibility reconciler path) swallows the error.
    await api.verifyPayment()
    expect(api.error.value).toBeNull()

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
    resetAllSharedCachedResources()
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

  it('T5 — cancelPending() bails out (returns false) when isInitiating is true ', async () => {
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



  it('T6 — visibilitychange auto-fires verifyPayment when the page is shown again with a pending payment armed (e.g. browser Back from the checkout)', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('active'))

    // Seed the singleton so the Round 2 D3 watch arms hasArmedPayment on mount.
    await useSubscriptionStatus().fetchStatus()

    const { api, unmount } = mountWithComposable()
    await flushPromises()

    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('pro', 'active', '2027-05-23T00:00:00Z'),
    })
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(faceApi.verifySubscriptionPayment).toHaveBeenCalledOnce()
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

})

describe('resumePayment via backend (FP-2.15.2)', () => {
  let openSpy: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    vi.clearAllMocks()
    resetAllSharedCachedResources()
    sessionStorage.clear()
    vi.useFakeTimers()
    openSpy = vi.spyOn(window, 'open').mockImplementation(() => ({}) as Window)
  })

  afterEach(() => {
    vi.useRealTimers()
    openSpy.mockRestore()
  })

  it('R1 — resumePayment() POSTs resume-payment, redirects the same tab to the returned URL, returns true', async () => {
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
    expect(redirectToCheckout).toHaveBeenCalledWith('https://checkout.fedapay.test/sess_resumed')
    expect(openSpy).not.toHaveBeenCalled()
    expect(api.paymentState.value).toBe('waiting')

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
    expect(redirectToCheckout).not.toHaveBeenCalled()
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
    expect(redirectToCheckout).not.toHaveBeenCalled()

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

  // The resume flow wraps the body in try/catch so a throwing redirect cannot
  // strand paymentState in 'waiting' forever.
  it('resumePayment catches a throw from the redirect and surfaces a failed state', async () => {
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
    vi.mocked(redirectToCheckout).mockImplementationOnce(() => {
      throw new Error('navigation blocked')
    })

    const { api, unmount } = mountWithComposable()
    const result = await api.resumePayment()

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.isInitiating.value).toBe(false)
    expect(api.error.value).not.toBeNull()

    unmount()
  })

  it('R7 — resumePayment() bails out when isInitiating / isVerifying / isCancelling is true', async () => {
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

    const result = await api.resumePayment()
    expect(result).toBe(false)
    expect(faceApi.resumePendingSubscription).not.toHaveBeenCalled()

    resolveInitiate(initiateResponse('pro'))
    await initiatePromise

    unmount()
  })
})
