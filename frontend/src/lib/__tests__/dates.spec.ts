import { describe, it, expect } from 'vitest'
import { addDaysIso, bookingMinStartDateIso, isBookingNextDayClosed, toLocalIsoDate, tomorrowIso } from '../dates'

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

  it('date de début minimale d\'un booking : demain avant 16 h au Bénin', () => {
    // 15:59 à Porto-Novo (UTC+1) = 14:59 UTC.
    const now = new Date('2026-10-20T14:59:00Z')
    expect(isBookingNextDayClosed(now)).toBe(false)
    expect(bookingMinStartDateIso(now)).toBe('2026-10-21')
  })

  it('date de début minimale d\'un booking : après-demain à partir de 16 h au Bénin', () => {
    const now = new Date('2026-10-20T15:00:00Z')
    expect(isBookingNextDayClosed(now)).toBe(true)
    expect(bookingMinStartDateIso(now)).toBe('2026-10-22')
  })

  it('se base sur le jour du Bénin, pas sur celui du navigateur', () => {
    // 23:30 UTC le 20/10 = 00:30 le 21/10 au Bénin : demain = 22/10.
    expect(bookingMinStartDateIso(new Date('2026-10-20T23:30:00Z'))).toBe('2026-10-22')
  })
})
