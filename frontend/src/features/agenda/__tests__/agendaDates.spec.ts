import { describe, expect, it } from 'vitest'
import {
  addDays,
  addMonths,
  buildDayStates,
  daysInclusive,
  eachDay,
  eventsOnDay,
  firstUnavailableIn,
  formatDayList,
  formatDayLong,
  formatDayNumbers,
  formatEventWhen,
  formatMonthTitle,
  formatPeriod,
  monthEnd,
  monthGrid,
  monthStart,
  weekdayIndex,
} from '../utils/agendaDates'
import type { AgendaEvent } from '../types'

function event(partial: Partial<AgendaEvent>): AgendaEvent {
  return {
    kind: 'booking',
    uuid: 'u',
    title: 'Clip',
    status: 'accepted',
    state: 'engaged',
    start: '2026-11-18',
    end: '2026-11-20',
    counterpart: 'Studio',
    ...partial,
  }
}

describe('agendaDates', () => {
  it('construit la grille de novembre 2026 : du lundi 26 octobre au dimanche 6 décembre', () => {
    const grid = monthGrid('2026-11-01')
    expect(grid).toHaveLength(42)
    expect(grid[0]).toEqual({ iso: '2026-10-26', inMonth: false })
    expect(grid[6]).toEqual({ iso: '2026-11-01', inMonth: true })
    expect(grid[35]).toEqual({ iso: '2026-11-30', inMonth: true })
    expect(grid[41]).toEqual({ iso: '2026-12-06', inMonth: false })
    expect(grid.filter((d) => d.inMonth)).toHaveLength(30)
  })

  it('commence la grille le jour même quand le 1er est un lundi (juin 2026)', () => {
    const grid = monthGrid('2026-06-15')
    expect(grid[0]).toEqual({ iso: '2026-06-01', inMonth: true })
    expect(grid).toHaveLength(35)
  })

  it('calcule le jour de semaine avec lundi = 0', () => {
    expect(weekdayIndex('2026-10-26')).toBe(0)
    expect(weekdayIndex('2026-11-01')).toBe(6)
    expect(weekdayIndex('2026-11-19')).toBe(3)
  })

  it('change de mois, y compris autour du changement d\'année', () => {
    expect(addMonths('2026-11-17', 1)).toBe('2026-12-01')
    expect(addMonths('2026-12-31', 1)).toBe('2027-01-01')
    expect(addMonths('2026-01-10', -1)).toBe('2025-12-01')
    expect(monthStart('2026-11-17')).toBe('2026-11-01')
    expect(monthEnd('2026-11-17')).toBe('2026-11-30')
    expect(monthEnd('2028-02-03')).toBe('2028-02-29')
  })

  it('ajoute des jours sans dérive de fuseau', () => {
    expect(addDays('2026-10-31', 1)).toBe('2026-11-01')
    expect(addDays('2026-11-08', 365)).toBe('2027-11-08')
    expect(addDays('2026-11-01', -1)).toBe('2026-10-31')
  })

  it('formate les libellés en français', () => {
    expect(formatMonthTitle('2026-11-01')).toBe('Novembre 2026')
    expect(formatMonthTitle('2027-02-14')).toBe('Février 2027')
    expect(formatDayLong('2026-11-19')).toBe('Jeudi 19 novembre')
    expect(formatDayList(['2026-11-28', '2026-11-29', '2026-11-30'])).toBe('Sam 28, dim 29 et lun 30 nov')
    expect(formatDayList(['2026-11-28'])).toBe('Sam 28 nov')
    expect(formatDayList(['2026-11-30', '2026-12-01'])).toBe('Lun 30 nov et mar 1 déc')
    expect(formatDayList(['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06'])).toBe(
      'Lun 2, mar 3, mer 4 et 2 autres',
    )
  })

  it('formate une plage multi-jours', () => {
    expect(formatPeriod('2026-11-18', '2026-11-20')).toBe('18 au 20 nov')
    expect(formatPeriod('2026-11-24', '2026-11-24')).toBe('24 nov')
    expect(formatPeriod('2026-10-30', '2026-11-02')).toBe('30 oct au 2 nov')
    expect(daysInclusive('2026-11-18', '2026-11-20')).toBe(3)
    expect(eachDay('2026-10-30', '2026-11-02')).toEqual(['2026-10-30', '2026-10-31', '2026-11-01', '2026-11-02'])
    expect(eachDay('2026-11-02', '2026-11-01')).toEqual([])
    expect(formatEventWhen(event({}))).toBe('18 au 20 nov · 3 jours')
    expect(formatEventWhen(event({ kind: 'mission', start: '2026-11-24', end: '2026-11-24' }))).toBe('24 nov · mission')
    expect(formatEventWhen(event({ start: '2026-11-24', end: '2026-11-24' }))).toBe('24 nov · 1 jour')
  })

  it('regroupe les numéros de jours : plages à partir de trois jours consécutifs', () => {
    expect(formatDayNumbers(['2026-11-12', '2026-11-13', '2026-11-18', '2026-11-19', '2026-11-20'])).toBe('12, 13, 18 au 20')
    expect(formatDayNumbers(['2026-11-20', '2026-11-12'])).toBe('12, 20')
  })

  it('trouve le premier jour indisponible d\'une plage', () => {
    const days = ['2026-11-20', '2026-11-12']
    expect(firstUnavailableIn(days, '2026-11-10', '2026-11-25')).toBe('2026-11-12')
    expect(firstUnavailableIn(days, '2026-11-13', '2026-11-19')).toBeNull()
  })

  it('construit les états par jour et limite aux bornes chargées', () => {
    const states = buildDayStates(
      ['2026-11-12', '2026-11-19'],
      [event({}), event({ kind: 'mission', start: '2026-11-24', end: '2026-11-24' }), event({ state: 'pending', start: '2026-11-30', end: '2026-12-02' })],
      '2026-11-01',
      '2026-11-30',
    )
    expect(states['2026-11-12']).toEqual(['blocked'])
    expect(states['2026-11-19']).toEqual(['blocked', 'booking'])
    expect(states['2026-11-24']).toEqual(['mission'])
    expect(states['2026-11-30']).toEqual(['pending'])
    expect(states['2026-12-01']).toBeUndefined()
  })

  it('liste les événements qui couvrent un jour', () => {
    const events = [event({}), event({ uuid: 'm', kind: 'mission', start: '2026-11-19', end: '2026-11-19' })]
    expect(eventsOnDay(events, '2026-11-19').map((e) => e.uuid)).toEqual(['u', 'm'])
    expect(eventsOnDay(events, '2026-11-21')).toEqual([])
  })
})
