import { describe, expect, it } from 'vitest'
import router from '../index'

describe('payment-return route', () => {
  it('is registered at /paiement/retour and requires authentication', () => {
    const resolved = router.resolve('/paiement/retour?fedapay_status=canceled')

    expect(resolved.name).toBe('payment-return')
    expect(resolved.meta.requiresAuth).toBe(true)
  })
})
