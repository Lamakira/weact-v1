import { addDaysIso } from '@/lib/dates'

/**
 * Formats et bornes du changement de date. Les jours sont des dates sans heure : le début de
 * journée est minuit au Bénin (Africa/Porto-Novo, UTC+1 toute l'année, pas d'heure d'été).
 */

export const BUSINESS_TIMEZONE = 'Africa/Porto-Novo'
export const RESPONSE_WINDOW_HOURS = 48
export const MIN_NOTICE_HOURS = 12

const HOUR_MS = 3_600_000
const BUSINESS_UTC_OFFSET_HOURS = 1

function parseDay(value: string | null | undefined): { y: number; m: number; d: number } | null {
  if (!value) return null
  const [y, m, d] = value.slice(0, 10).split('-').map(Number)
  if (!y || !m || !d) return null
  return { y, m, d }
}

/** Début de journée (minuit au Bénin) en millisecondes ; NaN si la date est illisible. */
export function dayStartMs(day: string): number {
  const parsed = parseDay(day)
  if (!parsed) return Number.NaN
  return Date.UTC(parsed.y, parsed.m - 1, parsed.d) - BUSINESS_UTC_OFFSET_HOURS * HOUR_MS
}

/** Le jour AAAA-MM-JJ lu en midi UTC : le jour de semaine reste exact quel que soit le fuseau du navigateur. */
function dayAsDate(day: string): Date | null {
  const parsed = parseDay(day)
  return parsed ? new Date(Date.UTC(parsed.y, parsed.m - 1, parsed.d, 12)) : null
}

function capitalize(text: string): string {
  return text.charAt(0).toUpperCase() + text.slice(1)
}

/** « vendredi 20 novembre ». */
export function formatDayLong(day: string | null | undefined): string {
  const date = day ? dayAsDate(day) : null
  if (!date) return ''
  return new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(date)
}

/** « Vendredi 20 novembre 2026 » (champ date de la fenêtre). */
export function formatDayWithYear(day: string | null | undefined): string {
  const date = day ? dayAsDate(day) : null
  if (!date) return ''
  return capitalize(
    new Intl.DateTimeFormat('fr-FR', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      timeZone: 'UTC',
    }).format(date),
  )
}

/** « 20 novembre » (libellé des boutons). */
export function formatDayMonth(day: string | null | undefined): string {
  const date = day ? dayAsDate(day) : null
  if (!date) return ''
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', timeZone: 'UTC' }).format(date)
}

/** « Ven. 20 novembre » (comparaison avant / après). */
export function formatDayCompact(day: string | null | undefined): string {
  const date = day ? dayAsDate(day) : null
  if (!date) return ''
  return capitalize(
    new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(date),
  )
}

function businessParts(date: Date, options: Intl.DateTimeFormatOptions): Record<string, string> {
  const parts = new Intl.DateTimeFormat('fr-FR', { timeZone: BUSINESS_TIMEZONE, hourCycle: 'h23', ...options }).formatToParts(date)
  return Object.fromEntries(parts.map((part) => [part.type, part.value]))
}

/** « dimanche 8 novembre à 10:00 » (fuseau métier) ; chaîne vide si l'instant est illisible. */
export function formatLimit(value: Date | string | null | undefined): string {
  if (!value) return ''
  const date = typeof value === 'string' ? new Date(value) : value
  if (Number.isNaN(date.getTime())) return ''
  const p = businessParts(date, { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
  return `${p.weekday} ${p.day} ${p.month} à ${p.hour}:${p.minute}`
}

/** « 8 nov. à 10:00 » (lignes de réponses du Producteur). */
export function formatInstantShort(value: string | null | undefined): string {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const p = businessParts(date, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
  return `${p.day} ${p.month} à ${p.hour}:${p.minute}`
}

export type DateChangeKind = 'mission' | 'booking'

/**
 * Aperçu de la limite de réponse (le serveur reste maître du calcul).
 * Mission : min(maintenant + 48 h, début de la nouvelle date - 1 h).
 * Booking : idem, et aussi borné par le début de l'ancienne date.
 */
export function responseLimitPreview(
  kind: DateChangeKind,
  params: { now: Date; newDate: string; oldDate?: string | null },
): Date | null {
  const newStart = dayStartMs(params.newDate)
  if (Number.isNaN(newStart)) return null
  // Mission : une heure de marge avant le début de la nouvelle date ; booking : début exact.
  const newBound = kind === 'mission' ? newStart - HOUR_MS : newStart
  const bounds = [params.now.getTime() + RESPONSE_WINDOW_HOURS * HOUR_MS, newBound]
  if (kind === 'booking') {
    const oldStart = params.oldDate ? dayStartMs(params.oldDate) : Number.NaN
    if (!Number.isNaN(oldStart)) bounds.push(oldStart)
  }
  return new Date(Math.min(...bounds))
}

/** Premier jour acceptable pour la nouvelle date : son début est au moins 12 h après `now`. */
export function minNewDateIso(now: Date = new Date()): string {
  const threshold = now.getTime() + MIN_NOTICE_HOURS * HOUR_MS
  const p = businessParts(new Date(threshold), { year: 'numeric', month: '2-digit', day: '2-digit' })
  const businessDay = `${p.year}-${p.month}-${p.day}`
  return dayStartMs(businessDay) >= threshold ? businessDay : addDaysIso(businessDay, 1)
}

/** « 1 j 23 h », « 3 h 05 », « 12 min » ; null une fois l'échéance passée ou illisible. */
export function formatTimeLeft(iso: string | null | undefined, now: Date): string | null {
  if (!iso) return null
  const target = new Date(iso).getTime()
  if (Number.isNaN(target)) return null
  const totalMinutes = Math.floor((target - now.getTime()) / 60_000)
  if (totalMinutes <= 0) return null
  const days = Math.floor(totalMinutes / 1440)
  const hours = Math.floor((totalMinutes % 1440) / 60)
  const minutes = totalMinutes % 60
  if (days > 0) return `${days} j ${hours} h`
  if (hours > 0) return `${hours} h ${String(minutes).padStart(2, '0')}`
  return `${minutes} min`
}

/** « AD » depuis « Awa D. ». */
export function initialsOf(name: string): string {
  const letters = name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase())
  return letters.join('')
}
