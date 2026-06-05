import { describe, it, expect } from 'vitest'
import {
  computeUgcCommission,
  UGC_COMMISSION_FLOOR,
  UGC_COMMISSION_RATE,
  UGC_PRODUCT_ONLY_VIDEO_COUNT,
} from '../ugc'

describe('computeUgcCommission', () => {
  // Same boundary values as the backend UgcCommissionServiceTest (story 1.1)
  it.each([
    [20000, 2500, 'floor (10% = 2000 < 2500)'],
    [25000, 2500, 'exact boundary (10% = 2500)'],
    [50000, 5000, 'percentage above the floor'],
    [25005, 2501, 'round half-up (2500.5 → 2501)'],
    [0, 2500, 'floor on empty/0 value'],
  ])('computeUgcCommission(%i) = %i (%s)', (productValue, expected) => {
    expect(computeUgcCommission(productValue)).toBe(expected)
  })

  it('falls back to the floor when given a falsy value', () => {
    expect(computeUgcCommission(NaN)).toBe(2500)
    // @ts-expect-error — guarding the `productValue || 0` runtime fallback
    expect(computeUgcCommission(undefined)).toBe(2500)
  })

  it('exposes constants that mirror backend/config/ugc.php', () => {
    expect(UGC_COMMISSION_RATE).toBe(0.1)
    expect(UGC_COMMISSION_FLOOR).toBe(2500)
    expect(UGC_PRODUCT_ONLY_VIDEO_COUNT).toBe(2)
  })
})
