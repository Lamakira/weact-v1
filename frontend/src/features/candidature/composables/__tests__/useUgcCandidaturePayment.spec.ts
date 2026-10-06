import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { useUgcCandidaturePayment } from '../useUgcCandidaturePayment'
import { candidatureApi } from '../../services/candidatureApi'
import { redirectToCheckout } from '@/lib/redirectToCheckout'
import type { AcceptCandidatureResult } from '../../types'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    acceptCandidature: vi.fn(),
    getCandidaturePaymentStatus: vi.fn(),
  },
}))

vi.mock('@/lib/redirectToCheckout', () => ({ redirectToCheckout: vi.fn() }))

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

// La vérification du paiement (polling) vit dans usePaymentReturn — cf. son spec.
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

  it('initiate() redirects the same tab to the FedaPay checkout and never calls window.open', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(CHECKOUT_URL))

    const api = useUgcCandidaturePayment()
    await api.initiate('cand-1')

    expect(candidatureApi.acceptCandidature).toHaveBeenCalledWith('cand-1')
    expect(redirectToCheckout).toHaveBeenCalledWith(CHECKOUT_URL)
    expect(openSpy).not.toHaveBeenCalled()
    expect(api.paymentStatus.value).toBe('waiting')
    expect(api.isInitiating.value).toBe(false)
  })

  it('does not poll after the redirect', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(CHECKOUT_URL))

    const api = useUgcCandidaturePayment()
    await api.initiate('cand-1')
    await vi.advanceTimersByTimeAsync(130_000)

    expect(candidatureApi.getCandidaturePaymentStatus).not.toHaveBeenCalled()
  })

  it('fails without redirecting when accept returns no checkout_url', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(undefined))

    const api = useUgcCandidaturePayment()
    await api.initiate('cand-1')

    expect(redirectToCheckout).not.toHaveBeenCalled()
    expect(api.paymentStatus.value).toBe('failed')
    expect(api.error.value).not.toBeNull()
  })

  it('surfaces a failed state when accept rejects', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockRejectedValue(new Error('500'))

    const api = useUgcCandidaturePayment()
    await api.initiate('cand-1')

    expect(api.paymentStatus.value).toBe('failed')
    expect(api.error.value).toBe("Une erreur est survenue lors de l'initiation du paiement.")
    expect(redirectToCheckout).not.toHaveBeenCalled()
  })

  it('reset() clears state', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue(acceptResult(CHECKOUT_URL))

    const api = useUgcCandidaturePayment()
    await api.initiate('cand-1')

    api.reset()
    expect(api.paymentStatus.value).toBe('idle')
    expect(api.error.value).toBeNull()
    expect(api.isInitiating.value).toBe(false)
  })
})
