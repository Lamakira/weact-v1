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
