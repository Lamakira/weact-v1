import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, h, type Ref } from 'vue'
import { mount } from '@vue/test-utils'
import { useSubscriptionPayment } from '../useSubscriptionPayment'
import { faceApi } from '../../services/faceApi'
import { resetSharedCachedResourcesForTests } from '@/lib/createSharedCachedResource'
import type {
  SubscriptionInitiatePaymentResponse,
  SubscriptionPaymentState,
  SubscriptionStatusInfo,
  SubscriptionStatusResponse,
} from '../../types'

vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getSubscriptionStatus: vi.fn(),
    initiateSubscriptionPayment: vi.fn(),
    verifySubscriptionPayment: vi.fn(),
  },
}))

vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn((error: unknown) => {
    if (error instanceof Error && error.message.includes('403')) {
      return 'Vous devez vérifier votre email pour effectuer cette action.'
    }

    if (error instanceof Error && error.message.includes('popup')) {
      return 'La fenêtre de paiement a été bloquée.'
    }

    return 'Un paiement est déjà en cours pour cette Face. Veuillez attendre la confirmation ou réessayer plus tard.'
  }),
}))

const baseStatus = (): SubscriptionStatusInfo => ({
  status: 'pending_payment',
  plan: 'annual_premium',
  starts_at: null,
  expires_at: null,
  cancelled_at: null,
  is_premium: false,
  is_featured_by_subscription: false,
  can_renew: false,
  subscription_id: 'sub_pending',
  entitlements: {
    album_upload_limit: 2,
    public_album_photo_limit: 2,
    current_album_photo_count: 0,
    public_album_photo_count: 0,
    locked_album_photo_count: 0,
    can_upload_acting_video: false,
    has_acting_video: false,
    is_acting_video_publicly_visible: false,
  },
  annual_plan: {
    amount: 50000,
    currency: 'XOF',
    provider: 'fedapay',
    is_available: false,
  },
})

const successInitiateResponse: SubscriptionInitiatePaymentResponse = {
  data: {
    subscription_id: 'sub_pending',
    status: 'pending_payment',
    checkout_url: 'https://checkout.fedapay.test/sess_abc',
    amount: 50000,
    currency: 'XOF',
  },
  message: 'Paiement initié',
}

const successVerifyResponse = {
  data: {
    subscription_id: 'sub_pending',
    status: 'active' as const,
  },
}

// Mount the composable inside a real component so onUnmounted is active.
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
    api: exposed!,
    unmount: () => wrapper.unmount(),
  }
}

describe('useSubscriptionPayment', () => {
  let openSpy: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    vi.clearAllMocks()
    resetSharedCachedResourcesForTests()
    vi.useFakeTimers()
    openSpy = vi.spyOn(window, 'open').mockImplementation(() => ({} as Window))
  })

  afterEach(() => {
    vi.useRealTimers()
    openSpy.mockRestore()
  })

  it('opens the checkout URL in a new tab and starts polling on success', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(successInitiateResponse)
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: baseStatus() })

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment()

    expect(result).toBe(true)
    expect(openSpy).toHaveBeenCalledOnce()
    expect(openSpy).toHaveBeenCalledWith(
      'https://checkout.fedapay.test/sess_abc',
      '_blank',
      'noopener,noreferrer',
    )
    expect(api.paymentState.value).toBe<SubscriptionPaymentState>('waiting')
    expect(api.isPolling.value).toBe(true)

    unmount()
  })

  it('flips paymentState to confirmed when polling detects active+is_premium', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(successInitiateResponse)
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(successVerifyResponse)
    const activeResponse: SubscriptionStatusResponse = {
      data: { ...baseStatus(), status: 'active', is_premium: true },
    }
    vi.mocked(faceApi.getSubscriptionStatus)
      .mockResolvedValueOnce({ data: baseStatus() }) // initial refresh after initiate
      .mockResolvedValueOnce(activeResponse) // first poll tick

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment()

    await vi.advanceTimersByTimeAsync(5000)

    expect(api.paymentState.value).toBe('confirmed')
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('flips paymentState to failed when polling detects status=failed', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(successInitiateResponse)
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue({
      data: { subscription_id: 'sub_pending', status: 'failed' },
    })
    const failedResponse: SubscriptionStatusResponse = {
      data: { ...baseStatus(), status: 'failed' },
    }
    vi.mocked(faceApi.getSubscriptionStatus)
      .mockResolvedValueOnce({ data: baseStatus() })
      .mockResolvedValueOnce(failedResponse)

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment()

    await vi.advanceTimersByTimeAsync(5000)

    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toBe('Le paiement a échoué. Veuillez réessayer.')
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('times out after POLL_TIMEOUT_MS when status stays pending_payment', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(successInitiateResponse)
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue({
      data: { subscription_id: 'sub_pending', status: 'pending_payment' },
    })
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: baseStatus() })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment()

    await vi.advanceTimersByTimeAsync(120_000)

    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toBe(
      'Le délai de confirmation a expiré. Vérifiez votre paiement et rafraîchissez la page.',
    )
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('returns false and surfaces the backend message when initiatePayment rejects (409 PENDING_PAYMENT_EXISTS)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockRejectedValue(new Error('409 conflict'))

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment()

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toContain('Un paiement est déjà en cours')
    expect(openSpy).not.toHaveBeenCalled()
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('returns false and does not open checkout on EMAIL_NOT_VERIFIED (403)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockRejectedValue(new Error('403 unverified'))

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment()

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toBe('Vous devez vérifier votre email pour effectuer cette action.')
    expect(openSpy).not.toHaveBeenCalled()
    expect(api.isPolling.value).toBe(false)

    unmount()
  })

  it('does not initiate a second checkout while initiation is already in progress', async () => {
    let resolveInitiation: ((value: SubscriptionInitiatePaymentResponse) => void) | undefined
    vi.mocked(faceApi.initiateSubscriptionPayment).mockReturnValue(
      new Promise((resolve) => {
        resolveInitiation = resolve
      }),
    )
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: baseStatus() })

    const { api, unmount } = mountWithComposable()
    const first = api.initiatePayment()
    const second = await api.initiatePayment()

    expect(second).toBe(false)
    expect(faceApi.initiateSubscriptionPayment).toHaveBeenCalledOnce()

    resolveInitiation?.(successInitiateResponse)
    await first

    unmount()
  })

  it('does not initiate a second checkout while polling is active', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(successInitiateResponse)
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: baseStatus() })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment()
    const second = await api.initiatePayment()

    expect(second).toBe(false)
    expect(faceApi.initiateSubscriptionPayment).toHaveBeenCalledOnce()

    unmount()
  })

  it('returns false and does not poll when the checkout popup is blocked', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(successInitiateResponse)
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: baseStatus() })
    openSpy.mockReturnValue(null)

    const { api, unmount } = mountWithComposable()
    const result = await api.initiatePayment()

    expect(result).toBe(false)
    expect(api.paymentState.value).toBe('failed')
    expect(api.error.value).toBe('La fenêtre de paiement a été bloquée. Autorisez les popups puis réessayez.')
    expect(api.isPolling.value).toBe(false)
    expect(faceApi.getSubscriptionStatus).not.toHaveBeenCalled()

    unmount()
  })

  it('reset() clears ephemeral state and stops polling', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(successInitiateResponse)
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: baseStatus() })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment()

    api.reset()

    expect(api.isInitiating.value).toBe(false)
    expect(api.isPolling.value).toBe(false)
    expect(api.paymentState.value).toBe('idle')
    expect(api.error.value).toBeNull()

    unmount()
  })

  it('verifyPayment asks the backend to reconcile Fedapay before refreshing status', async () => {
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue(successVerifyResponse)
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: { ...baseStatus(), status: 'active', is_premium: true },
    })

    const { api, unmount } = mountWithComposable()
    const result = await api.verifyPayment()

    expect(result).toBe(true)
    expect(faceApi.verifySubscriptionPayment).toHaveBeenCalledOnce()
    expect(faceApi.getSubscriptionStatus).toHaveBeenCalledOnce()
    expect(api.paymentState.value).toBe('confirmed')

    unmount()
  })

  it('stopPolling clears interval + timeout cleanly', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(successInitiateResponse)
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: baseStatus() })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment()
    expect(api.isPolling.value).toBe(true)

    api.stopPolling()
    expect(api.isPolling.value).toBe(false)

    // Advance well past the timeout — no further state changes should fire.
    await vi.advanceTimersByTimeAsync(120_000)
    expect(api.paymentState.value).toBe('waiting')

    unmount()
  })

  it('clears the poller when the consuming component unmounts (onUnmounted safety)', async () => {
    vi.mocked(faceApi.initiateSubscriptionPayment).mockResolvedValue(successInitiateResponse)
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: baseStatus() })

    const { api, unmount } = mountWithComposable()
    await api.initiatePayment()
    expect(api.isPolling.value).toBe(true)

    unmount()

    // After unmount, polling should be stopped — advancing timers should not
    // trigger any further getSubscriptionStatus calls beyond the initial refresh
    // performed inside initiatePayment.
    const callsBefore = vi.mocked(faceApi.getSubscriptionStatus).mock.calls.length
    await vi.advanceTimersByTimeAsync(30_000)
    const callsAfter = vi.mocked(faceApi.getSubscriptionStatus).mock.calls.length
    expect(callsAfter).toBe(callsBefore)
  })
})

// Silence unused-import warning for shared type
export type { Ref }
