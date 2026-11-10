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

/** Jour courant (AAAA-MM-JJ) au Bénin, quel que soit le fuseau du navigateur. */
export function businessTodayIso(now: Date = new Date()): string {
  return businessNow(now).date
}

/**
 * Première date de début acceptée pour une demande de booking cash (règle backend
 * `after_or_equal:today`) : aujourd'hui au Bénin (demande de dernière minute possible).
 */
export function bookingMinStartDateIso(now: Date = new Date()): string {
  return businessTodayIso(now)
}

const HOUR_MS = 60 * 60 * 1000

/**
 * Échéance de réponse de la Face à une demande de booking : maintenant + 72 h, ou 08 h le jour du
 * tournage (heure du Bénin, UTC+1 fixe) si plus tôt, mais jamais moins de 2 h après la demande.
 * Miroir indicatif de la règle serveur (le backend reste la source de vérité).
 */
export function bookingResponseDeadline(dateDebutIso: string, now: Date = new Date()): Date {
  const [y, m, d] = dateDebutIso.split('-').map(Number)
  // 08:00 au Bénin (UTC+1) = 07:00 UTC.
  const shootingMorning = Date.UTC(y!, (m ?? 1) - 1, d ?? 1, 7, 0, 0)
  const base = Math.min(now.getTime() + 72 * HOUR_MS, shootingMorning)

  return new Date(Math.max(base, now.getTime() + 2 * HOUR_MS))
}

/** « aujourd'hui à 17:00 », « demain à 08:00 » ou « le 12/11 à 08:00 » (jour et heure du Bénin). */
export function formatBusinessDeadline(deadline: Date, now: Date = new Date()): string {
  const time = new Intl.DateTimeFormat('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: BUSINESS_TIMEZONE,
  }).format(deadline)
  const day = businessTodayIso(deadline)
  const today = businessTodayIso(now)

  if (day === today) return `aujourd'hui à ${time}`
  if (day === addDaysIso(today, 1)) return `demain à ${time}`

  const [, m, d] = day.split('-')
  return `le ${d}/${m} à ${time}`
}
