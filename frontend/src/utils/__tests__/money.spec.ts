import { describe, expect, it } from 'vitest'
import { formatMoney, formatMoneyPer, formatNumber } from '../money'

describe('money', () => {
  it('formate un nombre avec séparateur de milliers', () => {
    expect(formatNumber(3941)).toBe('3 941')
    expect(formatNumber(999)).toBe('999')
    expect(formatNumber(1234567)).toBe('1 234 567')
    expect(formatNumber(null)).toBe('0')
  })

  it('formate un montant en F CFA avec espaces insécables', () => {
    expect(formatMoney(12500)).toBe('12 500 F CFA')
    expect(formatMoney(0)).toBe('0 F CFA')
    expect(formatMoney(undefined)).toBe('0 F CFA')
  })

  it('ne contient jamais XOF ni FCFA ni espace sécable', () => {
    const out = formatMoney(1250000)
    expect(out).not.toMatch(/XOF|FCFA/)
    expect(out).not.toContain(' ')
  })

  it('formate un tarif par période', () => {
    expect(formatMoneyPer(25000, 'jour')).toBe('25 000 F CFA / jour')
    expect(formatMoneyPer(12500, 'demi-journée')).toContain('12 500 F CFA')
  })
})
