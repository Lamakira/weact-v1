import type { AdminSubscriptionStatus } from '../services/adminFaceSubscriptionsApi'

const FALLBACK_BADGE_CLASS = 'bg-gray-100 text-gray-600'

export const STATUS_BADGE_CLASS: Record<AdminSubscriptionStatus, string> = {
  active: 'bg-green-100 text-green-700',
  pending_payment: 'bg-amber-100 text-amber-700',
  expired: 'bg-red-100 text-red-700',
  cancelled: 'bg-gray-100 text-gray-600',
  failed: 'bg-red-100 text-red-700',
}

export function statusBadgeClass(status: AdminSubscriptionStatus | null): string {
  if (!status) return FALLBACK_BADGE_CLASS
  return STATUS_BADGE_CLASS[status] ?? FALLBACK_BADGE_CLASS
}

/**
 * Date fr-FR au jour près. Le style du mois est paramétrable pour préserver le
 * rendu de chaque consommateur : liste transversale = 'short' (« 05 juil. 2026 »),
 * fiche Face = 'long' (« 05 juillet 2026 »).
 */
export function formatSubscriptionDate(
  iso: string | null,
  monthStyle: 'short' | 'long' = 'short',
): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: monthStyle,
    year: 'numeric',
  })
}

export function formatAmount(
  amount: number | null | undefined,
  currency: string = 'XOF',
): string {
  if (amount === null || amount === undefined || !Number.isFinite(amount)) return '—'
  try {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount)
  } catch {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} ${currency || 'XOF'}`
  }
}

/** Vue minimale d'un abonnement pour les gardes de cycle de vie (extend/cancel). */
export interface SubscriptionLifecycleSnapshot {
  status: AdminSubscriptionStatus | null
  expires_at: string | null
}

export function isExtendable(sub: SubscriptionLifecycleSnapshot): boolean {
  return (
    sub.status === 'active' &&
    sub.expires_at !== null &&
    new Date(sub.expires_at).getTime() > Date.now()
  )
}

export function isCancellable(sub: SubscriptionLifecycleSnapshot): boolean {
  return sub.status === 'active' || sub.status === 'pending_payment'
}
