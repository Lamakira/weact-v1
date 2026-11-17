import { afterEach, describe, expect, it, vi } from 'vitest'
import { redirectToCheckout } from '../redirectToCheckout'
import { captureAnalyticsEvent } from '@/lib/analytics'

vi.mock('@/lib/analytics', () => ({ captureAnalyticsEvent: vi.fn() }))

describe('redirectToCheckout', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('navigates the current tab with location.assign and never opens a pop-up', () => {
    const assign = vi.fn()
    const open = vi.fn()
    vi.stubGlobal('location', { assign })
    vi.stubGlobal('open', open)

    redirectToCheckout('https://checkout.fedapay.test/abc', { type: 'booking' })

    expect(assign).toHaveBeenCalledWith('https://checkout.fedapay.test/abc')
    expect(open).not.toHaveBeenCalled()
  })

  it('sends paiement_lance instantly, then navigates', () => {
    const order: string[] = []
    const assign = vi.fn(() => order.push('assign'))
    vi.stubGlobal('location', { assign })
    vi.mocked(captureAnalyticsEvent).mockImplementationOnce(() => {
      order.push('capture')
    })

    redirectToCheckout('https://checkout.fedapay.test/abc', { type: 'abonnement', palier: 'pro' })

    expect(captureAnalyticsEvent).toHaveBeenCalledWith(
      'paiement_lance',
      { type: 'abonnement', palier: 'pro' },
      { sendInstantly: true },
    )
    expect(order).toEqual(['capture', 'assign'])
  })
})
