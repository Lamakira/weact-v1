import { describe, it, expect } from 'vitest'
import { addDaysIso, toLocalIsoDate, tomorrowIso } from '../dates'

describe('dates', () => {
  it('formate une date locale en AAAA-MM-JJ', () => {
    expect(toLocalIsoDate(new Date(2026, 0, 5, 23, 59))).toBe('2026-01-05')
  })

  it('ajoute des jours en franchissant mois et année', () => {
    expect(addDaysIso('2026-12-31', 1)).toBe('2027-01-01')
    expect(addDaysIso('2026-03-01', -1)).toBe('2026-02-28')
  })

  it('calcule demain à partir de la date locale', () => {
    expect(tomorrowIso(new Date(2026, 9, 9, 23, 30))).toBe('2026-10-10')
  })
})
