/**
 * Helpers pour les bornes min/max des champs <input type="date"> (format AAAA-MM-JJ, date locale).
 */

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

/** Formate une Date en AAAA-MM-JJ selon le fuseau local. */
export function toLocalIsoDate(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** Ajoute `days` jours à une date AAAA-MM-JJ (sans dérive de fuseau). */
export function addDaysIso(iso: string, days: number): string {
  const [y, m, d] = iso.split('-').map(Number)
  return toLocalIsoDate(new Date(y!, (m ?? 1) - 1, (d ?? 1) + days))
}

/** Demain (AAAA-MM-JJ) : première date acceptée par la règle backend `after:today`. */
export function tomorrowIso(now: Date = new Date()): string {
  return addDaysIso(toLocalIsoDate(now), 1)
}

/** Fuseau métier (Bénin), aligné sur `app.business_timezone` côté backend. */
const BUSINESS_TIMEZONE = 'Africa/Porto-Novo'

/** Heure (Bénin) à partir de laquelle on ne peut plus demander un booking pour le lendemain. */
export const BOOKING_NEXT_DAY_CUTOFF_HOUR = 16

/** Jour (AAAA-MM-JJ) et heure (0-23) courants au Bénin, quel que soit le fuseau du navigateur. */
function businessNow(now: Date): { date: string; hour: number } {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: BUSINESS_TIMEZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(now)
  const get = (type: string): string => parts.find((p) => p.type === type)?.value ?? '0'

  return { date: `${get('year')}-${get('month')}-${get('day')}`, hour: Number(get('hour')) }
}

/** Le lendemain (Bénin) n'est plus réservable : il est 16 h ou plus au Bénin. */
export function isBookingNextDayClosed(now: Date = new Date()): boolean {
  return businessNow(now).hour >= BOOKING_NEXT_DAY_CUTOFF_HOUR
}

/**
 * Première date de début acceptée pour une demande de booking cash (règle backend) : demain
 * (Bénin) avant 16 h, après-demain à partir de 16 h.
 */
export function bookingMinStartDateIso(now: Date = new Date()): string {
  return addDaysIso(businessNow(now).date, isBookingNextDayClosed(now) ? 2 : 1)
}
