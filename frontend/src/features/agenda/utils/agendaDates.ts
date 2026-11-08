import type { AgendaEvent, DayState } from '../types'

/**
 * Fonctions pures sur des jours AAAA-MM-JJ. Aucune date locale du navigateur : tout passe par des
 * dates UTC construites depuis la chaîne, pour que le jour de semaine et les ajouts restent exacts.
 */

const WEEKDAYS_SHORT = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'] as const
const WEEKDAYS_LONG = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'] as const
const MONTHS_LONG = [
  'janvier',
  'février',
  'mars',
  'avril',
  'mai',
  'juin',
  'juillet',
  'août',
  'septembre',
  'octobre',
  'novembre',
  'décembre',
] as const
const MONTHS_SHORT = [
  'janv',
  'févr',
  'mars',
  'avr',
  'mai',
  'juin',
  'juil',
  'août',
  'sept',
  'oct',
  'nov',
  'déc',
] as const

/** Initiales des colonnes, semaine du lundi au dimanche. */
export const WEEKDAY_INITIALS = ['L', 'M', 'M', 'J', 'V', 'S', 'D'] as const

export interface GridDay {
  iso: string
  inMonth: boolean
}

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

function toIso(date: Date): string {
  return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`
}

function fromIso(iso: string): Date {
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number)
  return new Date(Date.UTC(y ?? 1970, (m ?? 1) - 1, d ?? 1))
}

function capitalize(text: string): string {
  return text.charAt(0).toUpperCase() + text.slice(1)
}

/** Ajoute `days` jours (négatif permis). */
export function addDays(iso: string, days: number): string {
  const date = fromIso(iso)
  date.setUTCDate(date.getUTCDate() + days)
  return toIso(date)
}

/** Premier jour du mois contenant `iso`. */
export function monthStart(iso: string): string {
  return `${iso.slice(0, 7)}-01`
}

/** Dernier jour du mois contenant `iso`. */
export function monthEnd(iso: string): string {
  const date = fromIso(monthStart(iso))
  return toIso(new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0)))
}

/** Premier jour du mois situé `delta` mois avant/après celui de `iso`. */
export function addMonths(iso: string, delta: number): string {
  const date = fromIso(monthStart(iso))
  return toIso(new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + delta, 1)))
}

/** Indice du jour de semaine, lundi = 0 … dimanche = 6. */
export function weekdayIndex(iso: string): number {
  return (fromIso(iso).getUTCDay() + 6) % 7
}

/** Nombre de jours entre deux jours, bornes comprises. */
export function daysInclusive(start: string, end: string): number {
  return Math.round((fromIso(end).getTime() - fromIso(start).getTime()) / 86_400_000) + 1
}

/** Tous les jours de [start, end], bornes comprises (vide si end < start). */
export function eachDay(start: string, end: string): string[] {
  const count = daysInclusive(start, end)
  return count > 0 ? Array.from({ length: count }, (_, i) => addDays(start, i)) : []
}

/** Grille du mois : du lundi précédant (ou égal au) 1er au dimanche suivant (ou égal au) dernier jour. */
export function monthGrid(monthIso: string): GridDay[] {
  const first = monthStart(monthIso)
  const last = monthEnd(monthIso)
  const gridStart = addDays(first, -weekdayIndex(first))
  const gridEnd = addDays(last, 6 - weekdayIndex(last))
  return eachDay(gridStart, gridEnd).map((iso) => ({ iso, inMonth: iso >= first && iso <= last }))
}

/** « Novembre 2026 ». */
export function formatMonthTitle(iso: string): string {
  const date = fromIso(iso)
  return `${capitalize(MONTHS_LONG[date.getUTCMonth()] ?? '')} ${date.getUTCFullYear()}`
}

/** « novembre ». */
export function formatMonthName(iso: string): string {
  return MONTHS_LONG[fromIso(iso).getUTCMonth()] ?? ''
}

/** « 12 novembre ». */
export function formatDayMonth(iso: string): string {
  const date = fromIso(iso)
  return `${date.getUTCDate()} ${MONTHS_LONG[date.getUTCMonth()] ?? ''}`
}

/** « jeudi 19 novembre ». */
export function formatDayLongLower(iso: string): string {
  return `${WEEKDAYS_LONG[weekdayIndex(iso)]} ${formatDayMonth(iso)}`
}

/** « Jeudi 19 novembre ». */
export function formatDayLong(iso: string): string {
  return capitalize(formatDayLongLower(iso))
}

/** Libellé court d'un jour pour une liste : « sam 28 » (avec le mois : « sam 28 nov »). */
function shortDay(iso: string, withMonth: boolean): string {
  const date = fromIso(iso)
  const base = `${WEEKDAYS_SHORT[weekdayIndex(iso)]} ${date.getUTCDate()}`
  return withMonth ? `${base} ${MONTHS_SHORT[date.getUTCMonth()] ?? ''}` : base
}

/**
 * Liste courte de jours : « Sam 28, dim 29 et lun 30 nov ». Le mois n'est écrit qu'une fois, à la fin,
 * quand tous les jours sont du même mois ; sinon chaque jour porte le sien. Au-delà de 4 jours : les 3
 * premiers puis « et N autres ».
 */
export function formatDayList(days: string[]): string {
  const sorted = [...days].sort()
  if (sorted.length === 0) return ''
  const sameMonth = sorted.every((d) => d.slice(0, 7) === sorted[0]!.slice(0, 7))
  const label = (iso: string, isLast: boolean): string => shortDay(iso, sameMonth ? isLast : true)

  let text: string
  if (sorted.length === 1) {
    text = label(sorted[0]!, true)
  } else if (sorted.length <= 4) {
    const head = sorted.slice(0, -1).map((d) => label(d, false))
    text = `${head.join(', ')} et ${label(sorted[sorted.length - 1]!, true)}`
  } else {
    const shown = sorted.slice(0, 3).map((d) => label(d, false))
    text = `${shown.join(', ')} et ${sorted.length - 3} autres`
  }
  return capitalize(text)
}

/** « 24 nov », « 18 au 20 nov », « 30 oct au 2 nov ». */
export function formatPeriod(start: string, end: string): string {
  const s = fromIso(start)
  const e = fromIso(end)
  const startMonth = MONTHS_SHORT[s.getUTCMonth()] ?? ''
  const endMonth = MONTHS_SHORT[e.getUTCMonth()] ?? ''
  if (start === end) return `${s.getUTCDate()} ${startMonth}`
  if (s.getUTCMonth() === e.getUTCMonth() && s.getUTCFullYear() === e.getUTCFullYear()) {
    return `${s.getUTCDate()} au ${e.getUTCDate()} ${endMonth}`
  }
  return `${s.getUTCDate()} ${startMonth} au ${e.getUTCDate()} ${endMonth}`
}

/** « 18 au 20 nov · 3 jours », « 24 nov · mission », « 24 nov · 1 jour ». */
export function formatEventWhen(event: Pick<AgendaEvent, 'kind' | 'start' | 'end'>): string {
  const period = formatPeriod(event.start, event.end)
  if (event.kind === 'mission' && event.start === event.end) return `${period} · mission`
  const count = daysInclusive(event.start, event.end)
  return `${period} · ${count} jour${count > 1 ? 's' : ''}`
}

/**
 * Numéros de jours d'un mois, plages à partir de 3 jours consécutifs : « 12, 13, 18 au 20 ».
 * `days` : jours AAAA-MM-JJ d'un même mois.
 */
export function formatDayNumbers(days: string[]): string {
  const numbers = [...new Set(days)].sort().map((d) => Number(d.slice(8, 10)))
  const parts: string[] = []
  let i = 0
  while (i < numbers.length) {
    let j = i
    while (j + 1 < numbers.length && numbers[j + 1] === numbers[j]! + 1) j++
    if (j - i >= 2) {
      parts.push(`${numbers[i]} au ${numbers[j]}`)
    } else {
      for (let k = i; k <= j; k++) parts.push(String(numbers[k]))
    }
    i = j + 1
  }
  return parts.join(', ')
}

/** Premier jour de `unavailable` compris dans [start, end], sinon null. */
export function firstUnavailableIn(unavailable: Iterable<string>, start: string, end: string): string | null {
  let found: string | null = null
  for (const day of unavailable) {
    if (day >= start && day <= end && (found === null || day < found)) found = day
  }
  return found
}

function stateOfEvent(event: AgendaEvent): DayState {
  if (event.state === 'pending') return 'pending'
  if (event.state === 'done') return 'done'
  return event.kind === 'mission' ? 'mission' : 'booking'
}

/** États de chaque jour (limités à [from, to]) à partir des jours bloqués et des événements. */
export function buildDayStates(
  unavailableDays: string[],
  events: AgendaEvent[],
  from: string,
  to: string,
): Record<string, DayState[]> {
  const states: Record<string, DayState[]> = {}
  const push = (iso: string, state: DayState): void => {
    if (iso < from || iso > to) return
    const list = (states[iso] ??= [])
    if (!list.includes(state)) list.push(state)
  }
  unavailableDays.forEach((iso) => push(iso, 'blocked'))
  events.forEach((event) => {
    const state = stateOfEvent(event)
    eachDay(event.start < from ? from : event.start, event.end > to ? to : event.end).forEach((iso) =>
      push(iso, state),
    )
  })
  return states
}

/** Événements qui couvrent le jour `iso`. */
export function eventsOnDay(events: AgendaEvent[], iso: string): AgendaEvent[] {
  return events.filter((event) => event.start <= iso && iso <= event.end)
}
