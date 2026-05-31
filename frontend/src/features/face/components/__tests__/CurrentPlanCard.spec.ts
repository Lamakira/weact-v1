import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import CurrentPlanCard from '../CurrentPlanCard.vue'
import type { SubscriptionCurrent, TierCapabilities } from '@/features/face/types'

const ctx = vi.hoisted(() => ({ status: {} as Record<string, unknown> }))

vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ctx.status,
}))
vi.mock('vue-router', () => ({
  RouterLink: {
    template: '<a :data-to="typeof to === \'string\' ? to : to.name"><slot /></a>',
    props: ['to'],
  },
}))

const CAPS: TierCapabilities = {
  max_album_photos: 1,
  max_presentation_videos: 0,
  max_acting_videos: 0,
  max_ugc_videos: 0,
  ugc_access: false,
  commission_rate: 0.1,
  sort_priority: 4,
  has_elite_badge: false,
}

function makeCurrent(overrides: Partial<SubscriptionCurrent>): SubscriptionCurrent {
  return {
    tier: 'free',
    plan: null,
    status: 'free',
    starts_at: null,
    expires_at: null,
    cancelled_at: null,
    capabilities: CAPS,
    ...overrides,
  }
}

function setStatus(
  current: SubscriptionCurrent | null,
  isLoading = false,
  error: string | null = null,
): void {
  ctx.status.current = ref(current)
  ctx.status.isLoading = ref(isLoading)
  ctx.status.error = ref(error)
  ctx.status.fetchStatus = vi.fn().mockResolvedValue(undefined)
  ctx.status.refreshStatus = vi.fn().mockResolvedValue(undefined)
}

describe('CurrentPlanCard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders the free tier with upsell copy and both links', async () => {
    setStatus(makeCurrent({ tier: 'free', plan: null, status: 'free' }))
    const wrapper = mount(CurrentPlanCard)
    await flushPromises()

    expect(wrapper.find('[data-testid="plan-tier-name"]').text()).toBe('Découverte')
    expect(wrapper.find('[data-testid="plan-upsell-copy"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="plan-billing-cta"]').attributes('data-to')).toBe('face-billing')
    expect(wrapper.find('[data-testid="plan-compare-link"]').attributes('data-to')).toBe('pricing')
  })

  it('renders a paid active tier with its status and both links', async () => {
    setStatus(makeCurrent({ tier: 'pro', plan: 'pro', status: 'active' }))
    const wrapper = mount(CurrentPlanCard)
    await flushPromises()

    expect(wrapper.find('[data-testid="plan-tier-name"]').text()).toBe('Pro')
    expect(wrapper.find('[data-testid="plan-status"]').text()).toBe('Actif')
    expect(wrapper.find('[data-testid="plan-upsell-copy"]').exists()).toBe(false)
    // AC7: both links present AND pointing to the correct route names (for the paid tier too).
    expect(wrapper.find('[data-testid="plan-billing-cta"]').attributes('data-to')).toBe('face-billing')
    expect(wrapper.find('[data-testid="plan-compare-link"]').attributes('data-to')).toBe('pricing')
  })

  it('shows the skeleton while loading with no cached data', async () => {
    setStatus(null, true)
    const wrapper = mount(CurrentPlanCard)
    await flushPromises()

    expect(wrapper.find('[data-testid="plan-skeleton"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="plan-tier-name"]').exists()).toBe(false)
  })

  it('surfaces the lapsed plan name for a cancelled subscription (billing consistency)', async () => {
    setStatus(makeCurrent({ tier: 'free', plan: 'pro', status: 'cancelled' }))
    const wrapper = mount(CurrentPlanCard)
    await flushPromises()

    expect(wrapper.find('[data-testid="plan-tier-name"]').text()).toBe('Pro')
    expect(wrapper.find('[data-testid="plan-status"]').text()).toBe('Annulé')
  })

  it('shows the pending-payment status for a pending subscription', async () => {
    setStatus(makeCurrent({ tier: 'free', plan: 'starter', status: 'pending_payment' }))
    const wrapper = mount(CurrentPlanCard)
    await flushPromises()

    expect(wrapper.find('[data-testid="plan-status"]').text()).toBe('En attente de paiement')
  })

  it('fetches the subscription status on mount (cache-respecting)', async () => {
    setStatus(makeCurrent({ tier: 'free' }))
    mount(CurrentPlanCard)
    await flushPromises()

    expect(ctx.status.fetchStatus).toHaveBeenCalledTimes(1)
  })

  it('shows an error state — not a false free tier — when the fetch failed with no cached data', async () => {
    setStatus(null, false, 'Erreur réseau')
    const wrapper = mount(CurrentPlanCard)
    await flushPromises()

    // Must NOT silently claim the free "Découverte" tier on a failed cold-cache fetch.
    expect(wrapper.find('[data-testid="plan-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="plan-tier-name"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="plan-upsell-copy"]').exists()).toBe(false)
    // Still offers a way to reach billing.
    expect(wrapper.find('[data-testid="plan-billing-cta"]').attributes('data-to')).toBe('face-billing')
  })

  it('keeps showing the cached card (not the error state) when a background refresh errors', async () => {
    // Stale-while-revalidate: cached `current` present + error set → render the card, not the error.
    setStatus(makeCurrent({ tier: 'pro', plan: 'pro', status: 'active' }), false, 'Erreur réseau')
    const wrapper = mount(CurrentPlanCard)
    await flushPromises()

    expect(wrapper.find('[data-testid="plan-error"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="plan-tier-name"]').text()).toBe('Pro')
  })

  it('retries with a forced refresh when the error-state retry button is clicked', async () => {
    setStatus(null, false, 'Erreur réseau')
    const wrapper = mount(CurrentPlanCard)
    await flushPromises()

    await wrapper.find('[data-testid="plan-retry"]').trigger('click')

    expect(ctx.status.refreshStatus).toHaveBeenCalledTimes(1)
  })
})
