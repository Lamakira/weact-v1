/**
 * Fuseau métier des échéances de booking : le backend formate les mêmes échéances en
 * Africa/Porto-Novo dans les notifications et les e-mails (« 23/10 à 13:00 »).
 */
export const BOOKING_BUSINESS_TIMEZONE = 'Africa/Porto-Novo'

/** « 23/10 à 13:00 » pour un horodatage ISO, dans le fuseau métier. */
export function formatBookingDeadline(iso: string): string {
  const date = new Date(iso)
  const day = new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    timeZone: BOOKING_BUSINESS_TIMEZONE,
  }).format(date)
  const time = new Intl.DateTimeFormat('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: BOOKING_BUSINESS_TIMEZONE,
  }).format(date)

  return `${day} à ${time}`
}
