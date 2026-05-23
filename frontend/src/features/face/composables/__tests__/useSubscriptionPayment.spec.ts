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
  },
}))

vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn(() => 'Un paiement est déjà en cours pour cet abonnement.'),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(() => ({ user: { id: 42 } })),
}))

const STASH_KEY = 'weact:pending-checkout:user-42'

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

  it('stashes the Fedapay checkout URL in sessionStorage on initiatePayment success (v2 resume mechanic)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    const setItemSpy = vi.spyOn(window.sessionStorage, 'setItem')

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')

    expect(setItemSpy).toHaveBeenCalledWith(
      STASH_KEY,
      expect.stringContaining('"url":"https://checkout.fedapay.test/sess_abc"'),
    )
    const stored = JSON.parse(sessionStorage.getItem(STASH_KEY) ?? '{}')
    expect(stored.url).toBe('https://checkout.fedapay.test/sess_abc')
    expect(stored.plan).toBe('pro')
    expect(typeof stored.storedAt).toBe('number')
    expect(api.pendingCheckoutAvailable.value).toBe(true)

    unmount()
  })

  it('resumePayment() reads the stash and reopens the Fedapay checkout', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    sessionStorage.setItem(
      STASH_KEY,
      JSON.stringify({
        url: 'https://checkout.fedapay.test/sess_resumed',
        plan: 'pro',
        storedAt: Date.now(),
      }),
    )

    const { api, unmount } = mountWithComposable()
    expect(api.pendingCheckoutAvailable.value).toBe(true)

    const result = await api.resumePayment()
    expect(result).toBe(true)
    expect(openSpy).toHaveBeenCalledWith(
      'https://checkout.fedapay.test/sess_resumed',
      '_blank',
      'noopener,noreferrer',
    )
    expect(api.paymentState.value).toBe('waiting')
    expect(api.isPolling.value).toBe(true)

    unmount()
  })

  it('resumePayment() returns false with a French error when no stash exists', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    // No setItem — stash is empty.

    const { api, unmount } = mountWithComposable()
    expect(api.pendingCheckoutAvailable.value).toBe(false)

    const result = await api.resumePayment()
    expect(result).toBe(false)
    expect(api.error.value).toContain('Aucun paiement à reprendre')
    expect(api.pendingCheckoutAvailable.value).toBe(false)
    expect(openSpy).not.toHaveBeenCalled()

    unmount()
  })

  it('readPendingCheckoutStash deletes entries older than 24h (TTL)', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    // Stash entry > 24h old.
    const oldStoredAt = Date.now() - 25 * 60 * 60 * 1000
    sessionStorage.setItem(
      STASH_KEY,
      JSON.stringify({
        url: 'https://checkout.fedapay.test/sess_stale',
        plan: 'pro',
        storedAt: oldStoredAt,
      }),
    )
    const removeItemSpy = vi.spyOn(window.sessionStorage, 'removeItem')

    const { api, unmount } = mountWithComposable()
    // On mount, pendingCheckoutAvailable is initialized via readPendingCheckoutStash —
    // the stale entry is deleted and the ref is false.
    expect(api.pendingCheckoutAvailable.value).toBe(false)
    expect(removeItemSpy).toHaveBeenCalledWith(STASH_KEY)
    expect(sessionStorage.getItem(STASH_KEY)).toBeNull()

    unmount()
  })

  it('clears the stash when a payment is confirmed via polling', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(verifyResponse('active'))
    vi.mocked(faceApi.getSubscriptionStatus)
      .mockResolvedValueOnce({ data: statusData('free', 'pending_payment', null) })
      .mockResolvedValueOnce({ data: statusData('pro', 'active', '2027-01-01T00:00:00Z') })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment('pro')
    expect(api.pendingCheckoutAvailable.value).toBe(true)
    expect(sessionStorage.getItem(STASH_KEY)).not.toBeNull()

    // First poll tick → verifyPayment → refresh returns active+pro → confirmed.
    await vi.advanceTimersByTimeAsync(5_000)

    expect(api.paymentState.value).toBe('confirmed')
    expect(api.pendingCheckoutAvailable.value).toBe(false)
    expect(sessionStorage.getItem(STASH_KEY)).toBeNull()

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
    expect(api.pendingCheckoutAvailable.value).toBe(false)

    unmount()
  })

  it('rejects resumePayment while a manual verify is already in flight (Findings #4 race guard)', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    sessionStorage.setItem(
      STASH_KEY,
      JSON.stringify({
        url: 'https://checkout.fedapay.test/sess_resumed',
        plan: 'pro',
        storedAt: Date.now(),
      }),
    )

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
    expect(openSpy).not.toHaveBeenCalled()

    resolveVerify(verifyResponse('pending_payment'))
    await flushPromises()

    unmount()
  })

  it('does NOT set pendingCheckoutAvailable when sessionStorage.setItem fails (Findings #6 stash-failure honesty)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(initiateResponse('pro'))
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })
    // Simulate Safari private mode / quota exceeded — setItem throws.
    const setItemSpy = vi.spyOn(window.sessionStorage, 'setItem').mockImplementation(() => {
      throw new Error('QuotaExceededError')
    })

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment('pro')

    expect(result).toBe(true) // payment still initiates — sessionStorage failure is non-fatal
    expect(api.paymentState.value).toBe('waiting')
    // pendingCheckoutAvailable must reflect the stash truth, not optimism.
    expect(api.pendingCheckoutAvailable.value).toBe(false)

    setItemSpy.mockRestore()
    unmount()
  })

  // Round 2 P1 — resumePayment wraps the body in try/catch/finally so a throw
  // (e.g. from window.open in a sandboxed iframe) cannot strand paymentState
  // in 'waiting' forever.
  it('resumePayment catches a throw from window.open and surfaces a failed state (Round 2 P1)', async () => {
    sessionStorage.setItem(
      STASH_KEY,
      JSON.stringify({ url: 'https://checkout.fedapay.test/sess_xyz', plan: 'pro', storedAt: Date.now() }),
    )
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

  // Round 2 P6 — dismissPaymentError clears the error + paymentState but PRESERVES
  // the sessionStorage stash so "Continuer le paiement" stays available after a
  // timeout (spec Resolved decision #5).
  it('dismissPaymentError clears the error WITHOUT clearing the stash (Round 2 P6)', async () => {
    sessionStorage.setItem(
      STASH_KEY,
      JSON.stringify({ url: 'https://checkout.fedapay.test/sess_xyz', plan: 'pro', storedAt: Date.now() }),
    )
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData('free', 'pending_payment', null),
    })

    const { api, unmount } = mountWithComposable()
    // Simulate a failed state with a stashed checkout.
    api.paymentState.value = 'failed'
    api.error.value = 'Le délai de confirmation a expiré.'

    api.dismissPaymentError()

    expect(api.error.value).toBeNull()
    expect(api.paymentState.value).toBe('idle')
    // The crucial invariant — the stash survives dismiss so the resume button
    // stays clickable. reset() (legacy behavior) WOULD have cleared it.
    expect(sessionStorage.getItem(STASH_KEY)).not.toBeNull()

    unmount()
  })
})
