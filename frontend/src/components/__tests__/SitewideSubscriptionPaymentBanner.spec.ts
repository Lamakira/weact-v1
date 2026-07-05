import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { computed, ref } from 'vue'
import SitewideSubscriptionPaymentBanner from '../SitewideSubscriptionPaymentBanner.vue'

const ctx = vi.hoisted(() => ({
  status: {} as Record<string, unknown>,
  verify: vi.fn(),
  isFace: false,
  authUser: null as { id: number } | null,
  routeName: 'faces' as string | null,
  // Non-empty = resolved route; [] simulates START_LOCATION (initial navigation).
  routeMatched: [{}] as object[],
  routerPush: vi.fn(),
}))

// The wrapper gates BEFORE the banner mounts; when it renders, the REAL banner +
// reconciler run against these mocked status/api layers, so "0 API call" is
// asserted at the service boundary (fetchStatus / verifySubscriptionPayment).
vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ctx.status,
}))
vi.mock('@/features/face/services/faceApi', () => ({
  faceApi: { verifySubscriptionPayment: ctx.verify },
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ isFace: ctx.isFace, user: ctx.authUser }),
}))
vi.mock('vue-router', () => ({
  useRoute: () => ({ name: ctx.routeName, matched: ctx.routeMatched }),
  useRouter: () => ({ push: ctx.routerPush }),
}))

const ALL_CTA = { upgrade_available: true, downgrade_available: true, renew_available: true }
const PENDING_CTA = { upgrade_available: false, downgrade_available: false, renew_available: false }

type StatusName = 'free' | 'pending_payment' | 'failed'

function setupStatus(status: StatusName): void {
  const current = ref<Record<string, unknown> | null>(
    status === 'free'
      ? { tier: 'free', plan: null, status: 'free' }
      : { tier: 'free', plan: 'starter', status },
  )
  ctx.status.current = current
  ctx.status.statusValue = computed(() => current.value?.status ?? 'free')
  ctx.status.cta = ref(status === 'pending_payment' ? PENDING_CTA : ALL_CTA)
  ctx.status.fetchStatus = vi.fn().mockResolvedValue(undefined)
  ctx.status.refreshStatus = vi.fn().mockResolvedValue(undefined)
}

let wrapper: VueWrapper | null = null
function mountBanner(): VueWrapper {
  wrapper = mount(SitewideSubscriptionPaymentBanner)
  return wrapper
}

function expectNoStatusCall(): void {
  expect(ctx.status.fetchStatus).not.toHaveBeenCalled()
  expect(ctx.verify).not.toHaveBeenCalled()
}

describe('SitewideSubscriptionPaymentBanner', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    ctx.verify.mockResolvedValue({ data: { subscription_id: 'sub_1', status: 'active' } })
    ctx.isFace = false
    ctx.authUser = null
    ctx.routeName = 'faces'
    ctx.routeMatched = [{}]
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    localStorage.clear()
  })

  it('renders nothing and makes NO status call for a guest', async () => {
    setupStatus('pending_payment')
    const w = mountBanner()
    await flushPromises()

    expect(w.find('[data-testid="sitewide-subscription-banner-container"]').exists()).toBe(false)
    expect(w.find('div').exists()).toBe(false)
    expectNoStatusCall()
  })

  it('renders nothing and makes NO status call for a Producer', async () => {
    ctx.isFace = false
    ctx.authUser = { id: 9 }
    setupStatus('pending_payment')
    const w = mountBanner()
    await flushPromises()

    expect(w.find('div').exists()).toBe(false)
    expectNoStatusCall()
  })

  it('shows the pending banner for a Face with a pending payment', async () => {
    ctx.isFace = true
    ctx.authUser = { id: 42 }
    setupStatus('pending_payment')
    const w = mountBanner()
    await flushPromises()

    const container = w.find('[data-testid="sitewide-subscription-banner-container"]')
    expect(container.exists()).toBe(true)
    expect(container.classes()).toEqual(
      expect.arrayContaining(['max-w-7xl', 'w-full', 'mx-auto', 'px-4', 'pt-4']),
    )
    expect(w.find('[data-testid="pending-payment-banner"]').exists()).toBe(true)
  })

  it('shows the failure banner for a Face with a failed, non-dismissed payment', async () => {
    ctx.isFace = true
    ctx.authUser = { id: 42 }
    setupStatus('failed')
    const w = mountBanner()
    await flushPromises()

    expect(w.find('[data-testid="pending-payment-failed-banner"]').exists()).toBe(true)
  })

  it('renders nothing on the pricing route even for a Face with a pending payment', async () => {
    ctx.isFace = true
    ctx.authUser = { id: 42 }
    ctx.routeName = 'pricing'
    setupStatus('pending_payment')
    const w = mountBanner()
    await flushPromises()

    expect(w.find('div').exists()).toBe(false)
    expectNoStatusCall()
  })

  it('renders nothing on the face-billing route even for a Face with a pending payment', async () => {
    ctx.isFace = true
    ctx.authUser = { id: 42 }
    ctx.routeName = 'face-billing'
    setupStatus('pending_payment')
    const w = mountBanner()
    await flushPromises()

    expect(w.find('div').exists()).toBe(false)
    expectNoStatusCall()
  })

  it('renders nothing while the initial navigation is unresolved (empty matched), even for a Face', async () => {
    // START_LOCATION: name undefined, matched empty — mounting here would
    // flash the banner and fire a status call even when the destination is
    // an excluded route.
    ctx.isFace = true
    ctx.authUser = { id: 42 }
    ctx.routeName = null
    ctx.routeMatched = []
    setupStatus('pending_payment')
    const w = mountBanner()
    await flushPromises()

    expect(w.find('div').exists()).toBe(false)
    expectNoStatusCall()
  })

  it('collapses to an empty container for a Face with no pending/failed payment (empty:hidden)', async () => {
    ctx.isFace = true
    ctx.authUser = { id: 42 }
    setupStatus('free')
    const w = mountBanner()
    await flushPromises()

    const container = w.find('[data-testid="sitewide-subscription-banner-container"]')
    expect(container.exists()).toBe(true)
    // The inner banner renders only a comment placeholder: no ELEMENT child,
    // which is what CSS :empty (empty:hidden) keys on — no phantom spacer.
    expect(container.element.children.length).toBe(0)
    expect(container.classes()).toContain('empty:hidden')
  })
})
