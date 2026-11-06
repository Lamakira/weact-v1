import { describe, expect, it } from 'vitest'
import {
  dayStartMs,
  formatDayCompact,
  formatDayLong,
  formatDayMonth,
  formatDayWithYear,
  formatInstantShort,
  formatLimit,
  formatTimeLeft,
  initialsOf,
  minNewDateIso,
  responseLimitPreview,
} from '../utils/dateChangeFormat'

// Le Bénin est en UTC+1 toute l'année : minuit au Bénin = 23:00 UTC la veille.
const H = 3_600_000

describe('formats de date français', () => {
  it('formate un jour sans décalage de fuseau', () => {
    expect(formatDayLong('2026-11-20')).toBe('vendredi 20 novembre')
    expect(formatDayLong('2026-11-20T09:00:00+00:00')).toBe('vendredi 20 novembre')
    expect(formatDayWithYear('2026-11-15')).toBe('Dimanche 15 novembre 2026')
    expect(formatDayMonth('2026-11-20')).toBe('20 novembre')
    expect(formatDayCompact('2026-11-20')).toBe('Ven. 20 novembre')
    expect(formatDayLong(null)).toBe('')
  })

  it('formate une limite dans le fuseau métier', () => {
    // 09:00 UTC = 10:00 à Porto-Novo
    expect(formatLimit(new Date('2026-11-08T09:00:00Z'))).toBe('dimanche 8 novembre à 10:00')
    expect(formatLimit('2026-11-08T09:00:00Z')).toBe('dimanche 8 novembre à 10:00')
    expect(formatLimit('pas une date')).toBe('')
    expect(formatInstantShort('2026-11-06T10:42:00Z')).toBe('6 nov. à 11:42')
  })
})

describe('aperçu de la limite de réponse', () => {
  const now = new Date('2026-11-06T08:00:00Z')

  it('mission : maintenant + 48 h quand la nouvelle date est lointaine', () => {
    const limit = responseLimitPreview('mission', { now, newDate: '2026-11-20' })
    expect(limit?.getTime()).toBe(now.getTime() + 48 * H)
  })

  it('mission : borné par le début de la nouvelle date moins 1 h', () => {
    const limit = responseLimitPreview('mission', { now, newDate: '2026-11-07' })
    expect(limit?.getTime()).toBe(dayStartMs('2026-11-07') - H)
    expect(limit?.toISOString()).toBe('2026-11-06T22:00:00.000Z')
  })

  it('booking : borné aussi par le début de l’ancienne date', () => {
    const limit = responseLimitPreview('booking', { now, newDate: '2026-11-20', oldDate: '2026-11-07' })
    expect(limit?.getTime()).toBe(dayStartMs('2026-11-07'))
  })

  it('booking : pas de marge d’une heure sur la nouvelle date', () => {
    const limit = responseLimitPreview('booking', { now, newDate: '2026-11-07', oldDate: '2026-11-20' })
    expect(limit?.getTime()).toBe(dayStartMs('2026-11-07'))
  })

  it('booking : ancienne date lointaine, 48 h s’appliquent', () => {
    const limit = responseLimitPreview('booking', { now, newDate: '2026-11-20', oldDate: '2026-11-15' })
    expect(limit?.getTime()).toBe(now.getTime() + 48 * H)
  })

  it('mission : l’ancienne date ne borne pas', () => {
    const limit = responseLimitPreview('mission', { now, newDate: '2026-11-20', oldDate: '2026-11-07' })
    expect(limit?.getTime()).toBe(now.getTime() + 48 * H)
  })

  it('date illisible : null', () => {
    expect(responseLimitPreview('mission', { now, newDate: '' })).toBeNull()
  })
})

describe('borne minimale de la nouvelle date', () => {
  it('le jour dont le début est au moins 12 h plus tard', () => {
    // 6 nov 08:00 UTC = 09:00 Bénin ; +12 h = 21:00 Bénin le 6 : le 7 commence à 00:00 Bénin, soit 3 h plus tard que le seuil
    expect(minNewDateIso(new Date('2026-11-06T08:00:00Z'))).toBe('2026-11-07')
  })

  it('juste avant le seuil : le lendemain est encore valable', () => {
    // 6 nov 11:00 UTC = 12:00 Bénin ; +12 h = minuit pile : le 7 commence exactement au seuil
    expect(minNewDateIso(new Date('2026-11-06T11:00:00Z'))).toBe('2026-11-07')
  })

  it('une minute après le seuil : le jour suivant', () => {
    expect(minNewDateIso(new Date('2026-11-06T11:01:00Z'))).toBe('2026-11-08')
  })
})

describe('décompte', () => {
  const now = new Date('2026-11-06T08:00:00Z')

  it('jours et heures, heures et minutes, minutes', () => {
    expect(formatTimeLeft(new Date(now.getTime() + 47 * H + 30 * 60_000).toISOString(), now)).toBe('1 j 23 h')
    expect(formatTimeLeft(new Date(now.getTime() + 3 * H + 5 * 60_000).toISOString(), now)).toBe('3 h 05')
    expect(formatTimeLeft(new Date(now.getTime() + 12 * 60_000).toISOString(), now)).toBe('12 min')
  })

  it('échéance passée ou absente : null', () => {
    expect(formatTimeLeft(new Date(now.getTime() - 1000).toISOString(), now)).toBeNull()
    expect(formatTimeLeft(null, now)).toBeNull()
  })

  it('initiales', () => {
    expect(initialsOf('Awa D.')).toBe('AD')
    expect(initialsOf('Rachida')).toBe('R')
  })
})
