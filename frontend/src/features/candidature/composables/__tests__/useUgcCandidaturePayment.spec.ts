import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useUgcCandidaturePayment } from '../useUgcCandidaturePayment'
import { candidatureApi } from '../../services/candidatureApi'
import type {
  AcceptCandidatureResult,
  CandidaturePaymentStatusResponse,
  CandidatureStatusType,
} from '../../types'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    acceptCandidature: vi.fn(),
    getCandidaturePaymentStatus: vi.fn(),
  },
}))

vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn(() => "Une erreur est survenue lors de l'initiation du paiement."),
}))

const CHECKOUT_URL = 'https://checkout.fedapay.test/sess_candidature'

function acceptResult(checkoutUrl: string | undefined): AcceptCandidatureResult {
  return {
    data: { id: 'cand-1', status: 'pending' } as never,
    message: 'Paiement du règlement initié',
    checkout_url: checkoutUrl,
  }
}

function statusResponse(
  candidatureStatus: CandidatureStatusType,
  paymentStatus: 'pending' | 'paid' | 'failed',
  isTrackable: boolean,
): CandidaturePaymentStatusResponse {
  return {
    data: {
      candidature_status: candidatureStatus,
      payment_status: paymentStatus,
      is_trackable: isTrackable,
    },
  }
}

/** Mount a host component so onUnmounted(stopPolling) is registered. */
function mountWithComposable(): {
  api: ReturnType<typeof useUgcCandidaturePayment>
  unmount: () => void
} {
  let exposed: ReturnType<typeof useUgcCandidaturePayment> | undefined
  const Wrapper = defineComponent({
    setup() {
      exposed = useUgcCandidaturePayment()
      return () => h('div')
    },
  })
  const wrapper = mount(Wrapper)
  return {
    api: exposed as ReturnType<typeof useUgcCandidaturePayment>,
    unmount: () => wrapper.unmount(),
  }
}

describe('useUgcCandidaturePayment (8-5 hybrid per-Face payment)', () => {
  let openSpy: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
    openSpy = vi.spyOn(window, 'open').mockImplementation(() => ({}) as Window)
  })

  afterEach(() => {
    vi.useRealTimers()
    openSpy.mockRestore()
  })

  it('initiate() opens the FedaPay checkout tab and enters the waiting state', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(CHECKOUT_URL))
    vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue(
      statusResponse('pending', 'pending', true),
    )

    const { api, unmount } = mountWithComposable()
    await api.initiate('cand-1')

    expect(candidatureApi.acceptCandidature).toHaveBeenCalledWith('cand-1')
    expect(openSpy).toHaveBeenCalledWith(CHECKOUT_URL, '_blank', 'noopener,noreferrer')
    expect(api.paymentStatus.value).toBe('waiting')
    expect(api.isInitiating.value).toBe(false)

    unmount()
  })

  it('confirms the payment when polling detects candidature_status=accepted', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(CHECKOUT_URL))
    vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue(
      statusResponse('accepted', 'paid', false),
    )

    const { api, unmount } = mountWithComposable()
    await api.initiate('cand-1')
    await vi.advanceTimersByTimeAsync(5000)

    expect(api.paymentStatus.value).toBe('confirmed')
    expect(api.error.value).toBeNull()

    unmount()
  })

  it('fails the payment when polling detects payment_status=failed', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(CHECKOUT_URL))
    vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue(
      statusResponse('pending', 'failed', false),
    )

    const { api, unmount } = mountWithComposable()
    await api.initiate('cand-1')
    await vi.advanceTimersByTimeAsync(5000)

    expect(api.paymentStatus.value).toBe('failed')
    expect(api.error.value).toBe('Le paiement a échoué ou a été annulé.')

    unmount()
  })

  it('fails fast when polling returns pending + is_trackable=false (webhook resolved the failure first)', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(CHECKOUT_URL))
    // The webhook marked the candidature failed and DELETED the escrow entry before
    // this poll, so the backend returns { payment_status:'pending', is_trackable:false }.
    // The composable must surface the failure on the FIRST poll (5 s) instead of
    // waiting for the 120 s timeout — regression guard for the FIX-19.3 parity gap
    // (ProducerMissionCandidaturesPage:79 honours is_trackable the same way).
    vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue(
      statusResponse('pending', 'pending', false),
    )

    const { api, unmount } = mountWithComposable()
    await api.initiate('cand-1')
    await vi.advanceTimersByTimeAsync(5000)

    expect(api.paymentStatus.value).toBe('failed')
    // The "échoué ou annulé" message (not the timeout message) proves the
    // is_trackable branch fired, not the 120 s timeout.
    expect(api.error.value).toBe('Le paiement a échoué ou a été annulé.')

    unmount()
  })

  it('fails the payment after the 120 s polling timeout', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(CHECKOUT_URL))
    vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue(
      statusResponse('pending', 'pending', true),
    )

    const { api, unmount } = mountWithComposable()
    await api.initiate('cand-1')
    await vi.advanceTimersByTimeAsync(120_000)

    expect(api.paymentStatus.value).toBe('failed')
    expect(api.error.value).toContain('délai de confirmation a expiré')

    unmount()
  })

  it('fails without opening a tab when accept returns no checkout_url', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(undefined))

    const { api, unmount } = mountWithComposable()
    await api.initiate('cand-1')

    expect(openSpy).not.toHaveBeenCalled()
    expect(api.paymentStatus.value).toBe('failed')
    expect(api.error.value).not.toBeNull()
    expect(candidatureApi.getCandidaturePaymentStatus).not.toHaveBeenCalled()

    unmount()
  })

  it('surfaces a failed state when accept rejects', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockRejectedValue(new Error('500'))

    const { api, unmount } = mountWithComposable()
    await api.initiate('cand-1')

    expect(api.paymentStatus.value).toBe('failed')
    expect(api.error.value).toBe("Une erreur est survenue lors de l'initiation du paiement.")
    expect(openSpy).not.toHaveBeenCalled()

    unmount()
  })

  it('reset() clears state and stops polling', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(CHECKOUT_URL))
    vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue(
      statusResponse('pending', 'pending', true),
    )

    const { api, unmount } = mountWithComposable()
    await api.initiate('cand-1')

    api.reset()
    expect(api.paymentStatus.value).toBe('idle')
    expect(api.error.value).toBeNull()
    expect(api.isInitiating.value).toBe(false)

    // Polling is dead — no further status calls fire.
    const callsBefore = vi.mocked(candidatureApi.getCandidaturePaymentStatus).mock.calls.length
    await vi.advanceTimersByTimeAsync(15_000)
    expect(vi.mocked(candidatureApi.getCandidaturePaymentStatus).mock.calls.length).toBe(callsBefore)

    unmount()
  })

  it('stops polling when the consuming component unmounts (onUnmounted safety)', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(CHECKOUT_URL))
    vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue(
      statusResponse('pending', 'pending', true),
    )

    const { api, unmount } = mountWithComposable()
    await api.initiate('cand-1')
    expect(api.paymentStatus.value).toBe('waiting')

    unmount()

    const callsBefore = vi.mocked(candidatureApi.getCandidaturePaymentStatus).mock.calls.length
    await vi.advanceTimersByTimeAsync(30_000)
    expect(vi.mocked(candidatureApi.getCandidaturePaymentStatus).mock.calls.length).toBe(callsBefore)
  })
})
