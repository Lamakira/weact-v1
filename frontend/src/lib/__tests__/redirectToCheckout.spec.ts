import { afterEach, describe, expect, it, vi } from 'vitest'
import { redirectToCheckout } from '../redirectToCheckout'

describe('redirectToCheckout', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('navigates the current tab with location.assign and never opens a pop-up', () => {
    const assign = vi.fn()
    const open = vi.fn()
    vi.stubGlobal('location', { assign })
    vi.stubGlobal('open', open)

    redirectToCheckout('https://checkout.fedapay.test/abc')

    expect(assign).toHaveBeenCalledWith('https://checkout.fedapay.test/abc')
    expect(open).not.toHaveBeenCalled()
  })
})
