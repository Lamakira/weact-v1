import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import SitewideSubscriptionPaymentBanner from '../SitewideSubscriptionPaymentBanner.vue'
import {
  setupSubscriptionStatusMock,
  type StatusName,
} from '@/features/face/composables/__tests__/subscriptionStatusTestUtils'

const ctx = vi.hoisted(() => ({
  status: {} as Record<string, unknown>,
  verify: vi.fn(),
  isFace: false,
  authUser: null as { id: number } | null,
  routeName: 'faces' as string | null,
  // Non-empty = resolved route; [] simulates START_LOCATION (initial navigation).
  routeMatched: [{}] as object[],
  // Mirrors the router: routes owning their subscription surface (pricing,
  // face-billing) declare meta.ownSubscriptionSurface = true.
  routeMeta: {} as Record<string, unknown>,
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
  useRoute: () => ({ name: ctx.routeName, matched: ctx.routeMatched, meta: ctx.routeMeta }),
  useRouter: () => ({ push: ctx.routerPush }),
}))

function setupStatus(status: StatusName): void {
  setupSubscriptionStatusMock(ctx.status, status)
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

// The inner banner is a defineAsyncComponent: dynamicImportSettled waits for
// the dynamic import to resolve, the flush lets the loaded component mount
// and settle its own fetches.
async function flushAsyncBanner(): Promise<void> {
  await vi.dynamicImportSettled()
  await flushPromises()
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
    ctx.routeMeta = {}
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
    expectNoStatusCall()
  })

  it('renders nothing and makes NO status call for a Producer', async () => {
    ctx.isFace = false
    ctx.authUser = { id: 9 }
    setupStatus('pending_payment')
    const w = mountBanner()
    await flushPromises()

    expect(w.find('[data-testid="sitewide-subscription-banner-container"]').exists()).toBe(false)
    expectNoStatusCall()
  })

  it('shows the pending banner for a Face with a pending payment', async () => {
    ctx.isFace = true
    ctx.authUser = { id: 42 }
    setupStatus('pending_payment')
    const w = mountBanner()
    await flushAsyncBanner()

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
    await flushAsyncBanner()

    expect(w.find('[data-testid="pending-payment-failed-banner"]').exists()).toBe(true)
  })

  it('renders nothing on a route that owns its subscription surface (meta.ownSubscriptionSurface, e.g. pricing)', async () => {
    ctx.isFace = true
    ctx.authUser = { id: 42 }
    ctx.routeName = 'pricing'
    ctx.routeMeta = { ownSubscriptionSurface: true }
    setupStatus('pending_payment')
    const w = mountBanner()
    await flushPromises()

    expect(w.find('[data-testid="sitewide-subscription-banner-container"]').exists()).toBe(false)
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

    expect(w.find('[data-testid="sitewide-subscription-banner-container"]').exists()).toBe(false)
    expectNoStatusCall()
  })

  it('collapses to an empty container for a Face with no pending/failed payment (empty:hidden)', async () => {
    ctx.isFace = true
    ctx.authUser = { id: 42 }
    setupStatus('free')
    const w = mountBanner()
    await flushAsyncBanner()

    const container = w.find('[data-testid="sitewide-subscription-banner-container"]')
    expect(container.exists()).toBe(true)
    // The inner banner renders only a comment placeholder. CSS :empty (what
    // empty:hidden keys on) is defeated by ANY text node, even whitespace —
    // assert the real selector, not just the element-children count.
    expect(container.element.matches(':empty')).toBe(true)
    expect(container.classes()).toContain('empty:hidden')
  })
})
