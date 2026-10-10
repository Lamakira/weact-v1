/**
 * Durées et heures relatives, en français, pour les listes compactes des dashboards.
 */

const MINUTE = 60_000
const HOUR = 60 * MINUTE
const DAY = 24 * HOUR

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

/** Âge d'un événement : « 20 min », « 2 h », « hier », « 3 j » (minimum 1 min). */
export function formatAge(iso: string, now: Date = new Date()): string {
  const elapsed = Math.max(0, now.getTime() - new Date(iso).getTime())

  if (elapsed < HOUR) return `${Math.max(1, Math.floor(elapsed / MINUTE))} min`
  if (elapsed < DAY) return `${Math.floor(elapsed / HOUR)} h`
  if (elapsed < 2 * DAY) return 'hier'
  return `${Math.floor(elapsed / DAY)} j`
}

/** Horodatage d'un message : « 10:42 » aujourd'hui, « hier », sinon « 02/10 ». */
export function formatMessageTime(iso: string, now: Date = new Date()): string {
  const date = new Date(iso)
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const startOfYesterday = new Date(startOfToday.getTime() - DAY)

  if (date >= startOfToday) return `${pad(date.getHours())}:${pad(date.getMinutes())}`
  if (date >= startOfYesterday) return 'hier'
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}`
}
