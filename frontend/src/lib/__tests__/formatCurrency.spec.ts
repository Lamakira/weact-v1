import { describe, expect, it } from 'vitest'
import { formatXof } from '../formatCurrency'

describe('formatXof', () => {
  it('formats with a thousands separator and the XOF code', () => {
    expect(formatXof(100000).replace(/\s/g, ' ')).toBe('100 000 XOF')
    expect(formatXof(0).replace(/\s/g, ' ')).toBe('0 XOF')
  })
})
