import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import FaceUpsellPage from '../FaceUpsellPage.vue'

// Shared, hoisted mock state so the vi.mock factories below can reference it
// (vi.mock is hoisted above the file body — vi.hoisted is the safe way to share).
const h = vi.hoisted(() => ({
  replace: vi.fn(),
  fetchStatus: vi.fn().mockResolvedValue(undefined),
  state: { emailVerified: true, tier: 'free' as string },
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ replace: h.replace, push: vi.fn() }),
  RouterLink: {
    template:
      '<a :data-to="typeof to === \'string\' ? to : to.name" :data-plan="typeof to === \'object\' && to.query ? to.query.plan : undefined"><slot /></a>',
    props: ['to'],
  },
}))

// Stub the reusable verification banner so its own deps (authApi, toast) aren't pulled in.
vi.mock('@/components/EmailVerificationBanner.vue', () => ({
  default: { template: '<div data-testid="email-verification-banner" />' },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    get isEmailVerified() {
      return h.state.emailVerified
    },
  }),
}))

vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ({
    tier: {
      get value() {
        return h.state.tier
      },
    },
    fetchStatus: h.fetchStatus,
  }),
}))

describe('FaceUpsellPage', () => {
  beforeEach(() => {
    h.state.emailVerified = true
    h.state.tier = 'free'
    h.replace.mockClear()
    h.fetchStatus.mockClear()
  })

  it('renders the three paid tiers with their names', async () => {
    const wrapper = mount(FaceUpsellPage)
    await flushPromises()
    expect(wrapper.find('[data-testid="upsell-tier-starter"]').text()).toContain('Starter')
    expect(wrapper.find('[data-testid="upsell-tier-pro"]').text()).toContain('Pro')
    expect(wrapper.find('[data-testid="upsell-tier-elite"]').text()).toContain('Élite')
  })

  it('each tier CTA deep-links into the pricing payment flow via ?plan= when the email is verified', async () => {
    const wrapper = mount(FaceUpsellPage)
    await flushPromises()
    for (const tier of ['starter', 'pro', 'elite']) {
      const cta = wrapper.find(`[data-testid="upsell-cta-${tier}"]`)
      expect(cta.attributes('data-to')).toBe('pricing')
      expect(cta.attributes('data-plan')).toBe(tier)
    }
    // No verification banner when verified.
    expect(wrapper.find('[data-testid="upsell-verify-email"]').exists()).toBe(false)
  })

  it('exit + compare links point to the dashboard and pricing', async () => {
    const wrapper = mount(FaceUpsellPage)
    await flushPromises()
    expect(wrapper.find('[data-testid="upsell-continue-free"]').attributes('data-to')).toBe(
      'face-dashboard',
    )
    const compare = wrapper.find('[data-testid="upsell-compare-link"]')
    expect(compare.attributes('data-to')).toBe('pricing')
    expect(compare.attributes('data-plan')).toBeUndefined()
  })

  it('shows the verification banner and disables the tier CTAs when the email is not verified (review D1-A)', async () => {
    h.state.emailVerified = false
    const wrapper = mount(FaceUpsellPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="upsell-verify-email"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="email-verification-banner"]').exists()).toBe(true)

    for (const tier of ['starter', 'pro', 'elite']) {
      const cta = wrapper.find(`[data-testid="upsell-cta-${tier}"]`)
      // Now a disabled <span>, not a RouterLink → no deep-link into /pricing.
      expect(cta.attributes('data-to')).toBeUndefined()
      expect(cta.attributes('aria-disabled')).toBe('true')
      expect(cta.text()).toContain('Vérifiez votre email')
    }
  })

  it('redirects a paying Face away from the upsell page to the dashboard (review D2)', async () => {
    h.state.tier = 'pro'
    mount(FaceUpsellPage)
    await flushPromises()
    expect(h.fetchStatus).toHaveBeenCalled()
    expect(h.replace).toHaveBeenCalledWith({ name: 'face-dashboard' })
  })

  it('does not redirect a free Face — the upsell page renders (review D2)', async () => {
    h.state.tier = 'free'
    mount(FaceUpsellPage)
    await flushPromises()
    expect(h.fetchStatus).toHaveBeenCalled()
    expect(h.replace).not.toHaveBeenCalled()
  })
})
