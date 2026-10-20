/**
 * Retour après abonnement : mémorise la page Face d'où le paywall a été ouvert
 * (mission, booking…) pour y revenir une fois l'abonnement activé.
 *
 * FedaPay revient par l'URL du backend (→ /face/billing), donc l'origine survit
 * dans `sessionStorage`, sous le préfixe auth-scopé purgé à la déconnexion.
 */
import { AUTH_SESSION_PREFIX } from '@/lib/authScopedSessionStorage'

export const SUBSCRIPTION_RETURN_KEY = `${AUTH_SESSION_PREFIX}subscriptionReturn`

/**
 * Accepte uniquement un chemin interne Face (`/face/...`) : jamais d'URL absolue,
 * de schéma, de `//` (protocol-relative), d'antislash, de caractère de contrôle
 * ni de segment `..`. Renvoie le chemin validé ou null.
 */
export function sanitizeFaceRedirect(value: unknown): string | null {
  if (typeof value !== 'string') return null
  if (!value.startsWith('/face/')) return null
  if (value.includes('//') || value.includes('\\')) return null
  // eslint-disable-next-line no-control-regex
  if (/[\u0000-\u001f\u007f]/.test(value)) return null
  if ((value.split(/[?#]/)[0] ?? '').split('/').includes('..')) return null
  return value
}

/** Mémorise (ou efface si invalide/absent) l'origine avant le départ vers le checkout. */
export function storeSubscriptionReturn(value: unknown): void {
  try {
    const safe = sanitizeFaceRedirect(value)
    if (safe === null) sessionStorage.removeItem(SUBSCRIPTION_RETURN_KEY)
    else sessionStorage.setItem(SUBSCRIPTION_RETURN_KEY, safe)
  } catch {
    // Storage indisponible : on perd seulement le retour automatique.
  }
}

/** Lit puis efface l'origine mémorisée (revalidée à la lecture). */
export function consumeSubscriptionReturn(): string | null {
  try {
    const stored = sessionStorage.getItem(SUBSCRIPTION_RETURN_KEY)
    sessionStorage.removeItem(SUBSCRIPTION_RETURN_KEY)
    return sanitizeFaceRedirect(stored)
  } catch {
    return null
  }
}
