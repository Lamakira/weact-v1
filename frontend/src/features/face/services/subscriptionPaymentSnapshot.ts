import { AUTH_SESSION_PREFIX } from '@/lib/authScopedSessionStorage'

/**
 * Subscription state saved just before the same-tab redirect to FedaPay.
 *
 * On return, the status endpoint reports the representative row — for a Face who
 * already has an active subscription (renewal / upgrade) that is still the OLD
 * active row even when the payment failed. Only a change compared to this
 * snapshot (tier changed, or expires_at moved forward) proves the new payment
 * was activated. Stored under the auth-scoped prefix so it is purged on logout.
 */
const KEY = `${AUTH_SESSION_PREFIX}subscription-payment-snapshot`

export interface SubscriptionPaymentSnapshot {
  tier: string
  expires_at: string | null
}

export function saveSubscriptionPaymentSnapshot(snapshot: SubscriptionPaymentSnapshot): void {
  try {
    sessionStorage.setItem(KEY, JSON.stringify(snapshot))
  } catch {
    // Storage unavailable: the return flow falls back to verify-payment's own answer.
  }
}

export function readSubscriptionPaymentSnapshot(): SubscriptionPaymentSnapshot | null {
  try {
    const raw = sessionStorage.getItem(KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as Partial<SubscriptionPaymentSnapshot>
    if (typeof parsed.tier !== 'string') return null
    return { tier: parsed.tier, expires_at: typeof parsed.expires_at === 'string' ? parsed.expires_at : null }
  } catch {
    return null
  }
}

export function clearSubscriptionPaymentSnapshot(): void {
  try {
    sessionStorage.removeItem(KEY)
  } catch {
    // Nothing to clear.
  }
}
