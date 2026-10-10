import { describe, it, expect } from 'vitest'
import {
  dayKey,
  formatContextDate,
  formatDayLabel,
  formatListTime,
} from '../utils/messageFormat'

const now = new Date(2030, 9, 9, 15, 0, 0) // mercredi 9 octobre 2030, heure locale

describe('formatListTime', () => {
  it("renvoie l'heure pour aujourd'hui", () => {
    expect(formatListTime(new Date(2030, 9, 9, 10, 42).toISOString(), now)).toBe('10:42')
  })

  it('renvoie « Hier » pour la veille', () => {
    expect(formatListTime(new Date(2030, 9, 8, 22, 0).toISOString(), now)).toBe('Hier')
  })

  it('renvoie jj/mm au-delà', () => {
    expect(formatListTime(new Date(2030, 9, 2, 8, 0).toISOString(), now)).toBe('02/10')
  })
})

describe('formatDayLabel', () => {
  it('gère aujourd’hui, hier et une date longue capitalisée', () => {
    expect(formatDayLabel(new Date(2030, 9, 9, 8, 0).toISOString(), now)).toBe('Aujourd’hui')
    expect(formatDayLabel(new Date(2030, 9, 8, 8, 0).toISOString(), now)).toBe('Hier')
    expect(formatDayLabel(new Date(2030, 9, 1, 8, 0).toISOString(), now)).toBe('Mardi 1 octobre')
  })
})

describe('dayKey / formatContextDate', () => {
  it('regroupe par jour local', () => {
    expect(dayKey(new Date(2030, 0, 5, 23, 59).toISOString())).toBe('2030-01-05')
  })

  it('formate la date de tournage et tolère null', () => {
    expect(formatContextDate('2030-10-14')).toBe('lun. 14 oct.')
    expect(formatContextDate(null)).toBeNull()
  })
})
