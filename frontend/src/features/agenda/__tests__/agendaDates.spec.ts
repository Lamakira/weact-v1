import { describe, expect, it } from 'vitest'
import {
  addDays,
  addMonths,
  buildDayStates,
  daysInclusive,
  eachDay,
  eventDayLabels,
  eventsOnDay,
  filterEvents,
  firstUnavailableIn,
  formatDayList,
  formatDayLong,
  formatDayNumbers,
  formatEventWhen,
  formatListWhen,
  formatMonthTitle,
  formatPeriod,
  formatWeekLabel,
  formatWeekTitle,
  groupByWeek,
  groupConsecutiveDays,
  isDayTouchable,
  formatSelectionSummary,
  isWideSelection,
  monthPickerLabel,
  monthEnd,
  monthGrid,
  monthStart,
  monthStats,
  weekDays,
  weekStart,
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

  it('calcule la semaine du 16 au 22 novembre 2026 et son titre', () => {
    expect(weekStart('2026-11-19')).toBe('2026-11-16')
    expect(weekStart('2026-11-22')).toBe('2026-11-16')
    expect(weekStart('2026-11-16')).toBe('2026-11-16')
    expect(weekDays('2026-11-19')).toEqual([
      '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21', '2026-11-22',
    ])
    expect(formatWeekTitle('2026-11-19')).toBe('Semaine du 16 au 22 novembre')
    expect(formatWeekTitle('2026-12-02')).toBe('Semaine du 30 novembre au 6 décembre')
    expect(formatWeekLabel('2026-11-16')).toBe('Semaine du 16 nov')
  })

  it('regroupe par semaine, trié, et ramène à la période un début antérieur', () => {
    const groups = groupByWeek([
      event({ uuid: 'c', start: '2026-11-24', end: '2026-11-24' }),
      event({ uuid: 'a', start: '2026-11-18', end: '2026-11-20' }),
      event({ uuid: 'b', start: '2026-11-19', end: '2026-11-19' }),
    ])
    expect(groups.map((g) => [g.week, g.items.map((e) => e.uuid)])).toEqual([
      ['2026-11-16', ['a', 'b']],
      ['2026-11-23', ['c']],
    ])
    const early = groupByWeek([event({ uuid: 'z', start: '2026-10-29', end: '2026-11-03' })], '2026-11-01')
    expect(early[0]!.week).toBe('2026-10-26')
  })

  it('regroupe les jours consécutifs en plages', () => {
    expect(groupConsecutiveDays(['2026-11-13', '2026-11-12', '2026-11-20'])).toEqual([
      { start: '2026-11-12', end: '2026-11-13' },
      { start: '2026-11-20', end: '2026-11-20' },
    ])
    expect(groupConsecutiveDays(['2026-11-30', '2026-12-01'])).toEqual([{ start: '2026-11-30', end: '2026-12-01' }])
  })

  it("calcule les statistiques du mois : jours libres à partir d'aujourd'hui, 0 pour un mois passé", () => {
    const events = [
      event({}),
      event({ kind: 'mission', start: '2026-11-24', end: '2026-11-24' }),
      event({ state: 'pending', start: '2026-11-26', end: '2026-11-27' }),
      event({ state: 'done', start: '2026-11-02', end: '2026-11-03' }),
    ]
    const blocked = ['2026-11-12', '2026-11-13', '2026-10-30']
    // Engagés : 18, 19, 20, 24 = 4 jours ; bloqués dans le mois : 2.
    // Libres : du 8 au 30 = 23 jours, moins 4 engagés, moins 2 bloqués = 17.
    expect(monthStats('2026-11-01', '2026-11-08', blocked, events)).toEqual({ booked: 4, blocked: 2, free: 17 })
    expect(monthStats('2026-10-01', '2026-11-08', blocked, events)).toEqual({ booked: 0, blocked: 1, free: 0 })
    expect(monthStats('2026-11-01', null, blocked, events).free).toBe(0)
  })

  it('découpe une plage du jeudi au mardi : texte le jeudi et le lundi seulement', () => {
    const labels = eventDayLabels(event({ start: '2026-11-19', end: '2026-11-24' }))
    expect(labels.map((l) => [l.iso, l.withText])).toEqual([
      ['2026-11-19', true],
      ['2026-11-20', false],
      ['2026-11-21', false],
      ['2026-11-22', false],
      ['2026-11-23', true],
      ['2026-11-24', false],
    ])
    // Plage débutant avant la grille : le premier jour visible n'a pas de texte, sauf s'il est lundi.
    const clipped = eventDayLabels(event({ start: '2026-10-29', end: '2026-11-02' }), '2026-10-31', '2026-11-30')
    expect(clipped.map((l) => [l.iso, l.withText])).toEqual([
      ['2026-10-31', false],
      ['2026-11-01', false],
      ['2026-11-02', true],
    ])
  })

  it('filtre les événements par type coché', () => {
    const events = [
      event({ uuid: 'b' }),
      event({ uuid: 'm', kind: 'mission' }),
      event({ uuid: 'p', state: 'pending' }),
      event({ uuid: 'd', state: 'done' }),
    ]
    const filters = { bookings: true, missions: false, pending: true, blocked: true, done: false }
    expect(filterEvents(events, filters).map((e) => e.uuid)).toEqual(['b', 'p'])
  })

  it("juge un jour touchable : futur, dans l'horizon, ni bloqué ni engagé", () => {
    expect(isDayTouchable(undefined, '2026-11-08', '2026-11-08', null)).toBe(true)
    expect(isDayTouchable(['pending'], '2026-11-09', '2026-11-08', null)).toBe(true)
    expect(isDayTouchable(['booking'], '2026-11-09', '2026-11-08', null)).toBe(false)
    expect(isDayTouchable(['blocked'], '2026-11-09', '2026-11-08', null)).toBe(false)
    expect(isDayTouchable(undefined, '2026-11-07', '2026-11-08', null)).toBe(false)
    expect(isDayTouchable(undefined, '2027-12-01', '2026-11-08', '2027-11-08')).toBe(false)
    expect(isDayTouchable(undefined, '2026-11-09', null, null)).toBe(false)
  })

  it('donne les libellés courts des 12 mois du sélecteur', () => {
    expect(Array.from({ length: 12 }, (_, i) => monthPickerLabel(i))).toEqual([
      'janv.',
      'févr.',
      'mars',
      'avr.',
      'mai',
      'juin',
      'juil.',
      'août',
      'sept.',
      'oct.',
      'nov.',
      'déc.',
    ])
    expect(monthPickerLabel(12)).toBe('')
  })

  it('juge une sélection large : plus de 7 jours entre premier et dernier, ou plusieurs mois', () => {
    expect(isWideSelection([])).toBe(false)
    expect(isWideSelection(['2026-11-09', '2026-11-15'])).toBe(false)
    expect(isWideSelection(['2026-11-09', '2026-11-16'])).toBe(true)
    expect(isWideSelection(['2026-11-30', '2026-12-01'])).toBe(true)
  })

  it('résume une sélection : premier et dernier jour, nombre réel de jours', () => {
    expect(formatSelectionSummary([])).toBe('')
    const range = Array.from({ length: 17 }, (_, i) => addDays('2026-12-20', i))
    expect(formatSelectionSummary(range)).toBe('Du 20 décembre au 5 janvier · 17 jours')
    expect(formatSelectionSummary(['2026-11-28', '2026-11-09', '2026-11-12'])).toBe('Du 9 novembre au 28 novembre · 3 jours')
  })

  it('formate une période de liste avec jours de semaine', () => {
    expect(formatListWhen(event({}))).toBe('Mer 18 au ven 20 · 3 jours')
    expect(formatListWhen(event({ start: '2026-11-24', end: '2026-11-24' }))).toBe('Mar 24 · 1 jour')
    expect(formatListWhen(event({ kind: 'mission', start: '2026-11-24', end: '2026-11-24' }))).toBe('Mar 24 · mission')
    expect(formatListWhen(event({ start: '2026-10-31', end: '2026-11-02' }))).toBe('Sam 31 oct au lun 2 nov · 3 jours')
  })
})
