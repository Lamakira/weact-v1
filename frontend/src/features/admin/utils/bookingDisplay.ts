/**
 * Shared display helpers for the admin booking views (list + detail).
 */

/**
 * Tailwind badge classes for a booking status, grouped by lifecycle meaning.
 */
export function getBookingStatusClass(status: string): string {
  switch (status) {
    case 'pending':
      return 'bg-amber-100 text-amber-800'
    case 'accepted':
    case 'in_progress':
    case 'confirmed_by_face':
    case 'confirmed_by_producer':
      return 'bg-blue-100 text-blue-800'
    case 'paid':
    case 'commission_paid':
      return 'bg-indigo-100 text-indigo-800'
    case 'completed':
      return 'bg-green-100 text-green-800'
    case 'refused':
    case 'cancelled_by_producer':
    case 'cancelled_by_face':
    case 'no_show':
      return 'bg-red-100 text-red-800'
    case 'expired':
      return 'bg-gray-100 text-gray-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

/**
 * Money amount as `120 000 FCFA`, or `—` when null/undefined.
 */
export function formatBookingAmount(amount: number | null | undefined): string {
  if (amount === null || amount === undefined) return '—'
  return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA'
}
