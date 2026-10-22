/**
 * Modèle d'une alerte de la barre d'alertes du compte (AccountAlertBar).
 * Les alertes sont affichées une à la fois, dans l'ordre fixe d'urgence de
 * {@link ALERT_ORDER}.
 */
export type AccountAlertId = 'ugc-suspension' | 'payment-pending' | 'payment-failed' | 'email-verification'

export type AccountAlertTone = 'danger' | 'warn' | 'info'

export interface AccountAlert {
  id: AccountAlertId
  tone: AccountAlertTone
  /** Phrase d'accroche, en gras */
  title: string
  message: string
  /** Alerte bloquante : suspend le défilement automatique */
  blocking?: boolean
  actionLabel: string
  actionDisabled?: boolean
  onAction: () => void
  /** Action secondaire facultative (ex. « Ignorer » un paiement échoué) */
  secondaryLabel?: string
  onSecondary?: () => void
}

/** Ordre d'urgence fixe : suspension UGC, paiement d'abonnement, e-mail à vérifier. */
export const ALERT_ORDER: readonly AccountAlertId[] = [
  'ugc-suspension',
  'payment-pending',
  'payment-failed',
  'email-verification',
]

export function sortAlerts(alerts: AccountAlert[]): AccountAlert[] {
  return [...alerts].sort((a, b) => ALERT_ORDER.indexOf(a.id) - ALERT_ORDER.indexOf(b.id))
}
