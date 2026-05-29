import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import PendingSubscriptionPaymentBanner from '../PendingSubscriptionPaymentBanner.vue'
import type { SubscriptionCurrent } from '@/features/face/types'

const ctx = vi.hoisted(() => ({
  status: {} as Record<string, unknown>,
  routerPush: vi.fn(),
}))

vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ctx.status,
}))
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: ctx.routerPush }),
}))

const ALL_CTA = { upgrade_available: true, downgrade_available: true, renew_available: true }
const PENDING_CTA = { upgrade_available: false, downgrade_available: false, renew_available: false }

function current(status: SubscriptionCurrent['status']): SubscriptionCurrent {
  return {
    tier: 'free',
    plan: 'pro',
    status,
    starts_at: null,
    expires_at: null,
    cancelled_at: null,
    capabilities: {
      max_album_photos: 1,
      max_presentation_videos: 0,
      max_acting_videos: 0,
      max_ugc_videos: 0,
      ugc_access: false,
      commission_rate: 0.1,
      sort_priority: 4,
      has_elite_badge: false,
    },
  }
}

function setup(opts: { current?: SubscriptionCurrent | null; cta?: typeof ALL_CTA } = {}): void {
  ctx.status.current = ref(opts.current === undefined ? current('pending_payment') : opts.current)
  ctx.status.cta = ref(opts.cta ?? PENDING_CTA)
  ctx.status.fetchStatus = vi.fn().mockResolvedValue(undefined)
}

describe('PendingSubscriptionPaymentBanner', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setup()
  })

  it('renders when a payment is pending (every CTA false) and routes to the billing tab', async () => {
    setup({ current: current('pending_payment'), cta: PENDING_CTA })
    const wrapper = mount(PendingSubscriptionPaymentBanner)
    await flushPromises()

    const banner = wrapper.find('[data-testid="pending-payment-banner"]')
    expect(banner.exists()).toBe(true)

    await wrapper.get('[data-testid="pending-payment-banner-cta"]').trigger('click')
    expect(ctx.routerPush).toHaveBeenCalledWith({ name: 'face-billing' })
  })

  it('stays hidden for a normal free Face (CTAs available)', async () => {
    setup({ current: current('free'), cta: ALL_CTA })
    const wrapper = mount(PendingSubscriptionPaymentBanner)
    await flushPromises()
    expect(wrapper.find('[data-testid="pending-payment-banner"]').exists()).toBe(false)
  })

  it('stays hidden when the status has not loaded yet', async () => {
    setup({ current: null })
    const wrapper = mount(PendingSubscriptionPaymentBanner)
    await flushPromises()
    expect(wrapper.find('[data-testid="pending-payment-banner"]').exists()).toBe(false)
  })

  it('fetches the subscription status on mount', async () => {
    setup()
    mount(PendingSubscriptionPaymentBanner)
    await flushPromises()
    expect(ctx.status.fetchStatus).toHaveBeenCalled()
  })
})
