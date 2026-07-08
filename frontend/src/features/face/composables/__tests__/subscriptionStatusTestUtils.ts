import { vi } from 'vitest'
import { computed, ref, type Ref } from 'vue'

/**
 * Shared mock scaffolding for the useSubscriptionStatus contract, consumed by
 * useSubscriptionReconciler.spec.ts and SitewideSubscriptionPaymentBanner.spec.ts.
 *
 * Single mirror to maintain: when the composable's shape changes (statusValue,
 * cta, fetch/refresh), fix it HERE once — both suites pick it up, instead of
 * two hand-rolled copies silently drifting apart.
 */

export interface SubscriptionCta {
  upgrade_available: boolean
  downgrade_available: boolean
  renew_available: boolean
}

export const ALL_CTA: SubscriptionCta = {
  upgrade_available: true,
  downgrade_available: true,
  renew_available: true,
}
export const PENDING_CTA: SubscriptionCta = {
  upgrade_available: false,
  downgrade_available: false,
  renew_available: false,
}

export type StatusName = 'free' | 'pending_payment' | 'failed' | 'active'

export interface SubscriptionStatusMockRefs {
  current: Ref<Record<string, unknown> | null>
  cta: Ref<SubscriptionCta>
}

/**
 * (Re)populate `target` — the object returned by the mocked useSubscriptionStatus —
 * for the given server status. Returns the typed refs so tests can mutate the
 * status/CTAs mid-test without untyped casts.
 */
export function setupSubscriptionStatusMock(
  target: Record<string, unknown>,
  status: StatusName,
): SubscriptionStatusMockRefs {
  const current = ref<Record<string, unknown> | null>(
    status === 'free'
      ? { tier: 'free', plan: null, status: 'free' }
      : { tier: status === 'active' ? 'starter' : 'free', plan: 'starter', status },
  )
  const cta = ref<SubscriptionCta>(status === 'pending_payment' ? PENDING_CTA : ALL_CTA)
  target.current = current
  // Mirrors useSubscriptionStatus l.77 — the server-derived status value.
  target.statusValue = computed(() => current.value?.status ?? 'free')
  target.cta = cta
  target.fetchStatus = vi.fn().mockResolvedValue(undefined)
  target.refreshStatus = vi.fn().mockResolvedValue(undefined)
  return { current, cta }
}
