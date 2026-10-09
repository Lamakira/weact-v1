/**
 * Formats de date de la messagerie (fuseau local du navigateur).
 */

function startOfDay(date: Date): number {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()
}

function dayDiff(date: Date, now: Date): number {
  return Math.round((startOfDay(now) - startOfDay(date)) / 86_400_000)
}

function capitalize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1)
}

export function formatClock(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(date)
}

/** Heure du jour pour aujourd'hui, « Hier », puis jj/mm. */
export function formatListTime(iso: string, now: Date = new Date()): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const diff = dayDiff(date, now)
  if (diff <= 0) return formatClock(iso)
  if (diff === 1) return 'Hier'
  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit' }).format(date)
}

/** Clé de regroupement par jour local (yyyy-mm-dd). */
export function dayKey(iso: string): string {
  const date = new Date(iso)
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

/** Libellé du séparateur de jour : « Aujourd’hui », « Hier », « Mardi 7 octobre ». */
export function formatDayLabel(iso: string, now: Date = new Date()): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const diff = dayDiff(date, now)
  if (diff === 0) return 'Aujourd’hui'
  if (diff === 1) return 'Hier'
  const label = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    ...(date.getFullYear() !== now.getFullYear() ? { year: 'numeric' as const } : {}),
  }).format(date)
  return capitalize(label)
}

/** Date de tournage `yyyy-mm-dd` -> « sam. 14 oct. ». */
export function formatContextDate(ymd: string | null): string | null {
  if (!ymd) return null
  const [year, month, day] = ymd.split('-').map(Number)
  if (!year || !month || !day) return null
  return new Intl.DateTimeFormat('fr-FR', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  }).format(new Date(year, month - 1, day))
}
