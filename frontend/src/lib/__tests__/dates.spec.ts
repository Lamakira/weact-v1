import { describe, it, expect } from 'vitest'
import {
  addDaysIso,
  bookingMinStartDateIso,
  bookingResponseDeadline,
  formatBusinessDeadline,
  toLocalIsoDate,
  tomorrowIso,
} from '../dates'

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

  it('date de début minimale d\'un booking : aujourd\'hui au Bénin, à 10 h comme à 18 h', () => {
    expect(bookingMinStartDateIso(new Date('2026-10-20T09:00:00Z'))).toBe('2026-10-20')
    expect(bookingMinStartDateIso(new Date('2026-10-20T17:00:00Z'))).toBe('2026-10-20')
  })

  it('se base sur le jour du Bénin, pas sur celui du navigateur', () => {
    // 23:30 UTC le 20/10 = 00:30 le 21/10 au Bénin : aujourd'hui = 21/10.
    expect(bookingMinStartDateIso(new Date('2026-10-20T23:30:00Z'))).toBe('2026-10-21')
  })

  describe('bookingResponseDeadline', () => {
    it('jour même, demande à 15:00 Bénin : 17:00 (minimum 2 h)', () => {
      const now = new Date('2026-10-20T14:00:00Z')
      expect(bookingResponseDeadline('2026-10-20', now).toISOString()).toBe('2026-10-20T16:00:00.000Z')
    })

    it('demain, demande à 20:00 Bénin : demain 08:00', () => {
      const now = new Date('2026-10-20T19:00:00Z')
      expect(bookingResponseDeadline('2026-10-21', now).toISOString()).toBe('2026-10-21T07:00:00.000Z')
    })

    it('jour même, demande à 07:00 Bénin : 09:00 (08 h déjà dépassé ou trop proche)', () => {
      const now = new Date('2026-10-20T06:00:00Z')
      expect(bookingResponseDeadline('2026-10-20', now).toISOString()).toBe('2026-10-20T08:00:00.000Z')
    })

    it('tournage dans 5 jours : maintenant + 72 h', () => {
      const now = new Date('2026-10-20T10:00:00Z')
      expect(bookingResponseDeadline('2026-10-25', now).toISOString()).toBe('2026-10-23T10:00:00.000Z')
    })
  })

  describe('formatBusinessDeadline', () => {
    const now = new Date('2026-10-20T10:00:00Z')

    it('aujourd\'hui', () => {
      expect(formatBusinessDeadline(new Date('2026-10-20T16:00:00Z'), now)).toBe('aujourd\'hui à 17:00')
    })

    it('demain', () => {
      expect(formatBusinessDeadline(new Date('2026-10-21T07:00:00Z'), now)).toBe('demain à 08:00')
    })

    it('une date plus lointaine', () => {
      expect(formatBusinessDeadline(new Date('2026-10-23T07:00:00Z'), now)).toBe('le 23/10 à 08:00')
    })

    it('utilise le jour du Bénin (23:30 UTC = 00:30 le lendemain)', () => {
      expect(formatBusinessDeadline(new Date('2026-10-20T23:30:00Z'), now)).toBe('demain à 00:30')
    })
  })
})
