import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { useSubscriptionReconciler } from '../useSubscriptionReconciler'
import {
  ALL_CTA,
  PENDING_CTA,
  setupSubscriptionStatusMock,
  type StatusName,
  type SubscriptionStatusMockRefs,
} from './subscriptionStatusTestUtils'

const ctx = vi.hoisted(() => ({
  status: {} as Record<string, unknown>,
  verify: vi.fn(),
  authUser: { id: 42 } as { id: number } | null,
}))

vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ctx.status,
}))
vi.mock('../../services/faceApi', () => ({
  faceApi: { verifySubscriptionPayment: ctx.verify },
}))
vi.mock('@/stores/auth', () => ({
  // Getter: the composable reads authStore.user at call time (Pinia refs are
  // live) — the mock must reflect a mid-test ctx.authUser change the same way.
  useAuthStore: () => ({
    get user() {
      return ctx.authUser
    },
  }),
}))

const DISMISS_KEY = 'face-subscription-failure-dismissed:42'

function setupStatus(status: StatusName): SubscriptionStatusMockRefs {
  return setupSubscriptionStatusMock(ctx.status, status)
}

let api: ReturnType<typeof useSubscriptionReconciler> | null = null
const Harness = defineComponent({
  setup() {
    api = useSubscriptionReconciler()
    return () =>
      h('div', {
        'data-pending': String(api!.hasPendingPayment.value),
        'data-failed': String(api!.paymentFailed.value),
      })
  },
})

let wrapper: VueWrapper | null = null
function mountHarness(): VueWrapper {
  wrapper = mount(Harness)
  return wrapper
}

describe('useSubscriptionReconciler', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    ctx.authUser = { id: 42 }
    ctx.verify.mockResolvedValue({ data: { subscription_id: 'sub_1', status: 'active' } })
    // jsdom defaults visibilityState to 'visible'.
  })

  // Unmount between tests so the global visibilitychange listener is removed
  // (onUnmounted) and does not leak into the next test's dispatch.
  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    api = null
    localStorage.clear()
  })

  it('auto-reconciles on mount when a payment is pending (verify + refresh)', async () => {
    setupStatus('pending_payment')
    mountHarness()
    await flushPromises()

    expect(ctx.status.fetchStatus).toHaveBeenCalled()
    expect(ctx.verify).toHaveBeenCalledTimes(1)
    expect(ctx.status.refreshStatus).toHaveBeenCalled()
  })

  it('does NOT call verify on mount when no payment is pending', async () => {
    setupStatus('free')
    mountHarness()
    await flushPromises()

    expect(ctx.status.fetchStatus).toHaveBeenCalled()
    expect(ctx.verify).not.toHaveBeenCalled()
  })

  it('skips the forced status refresh while verify still reports pending (no change to fetch)', async () => {
    // Steady-state poll tick: the payment has not moved — re-downloading the
    // identical status payload every 6s would be pure waste.
    ctx.verify.mockResolvedValue({ data: { subscription_id: 'sub_1', status: 'pending_payment' } })
    setupStatus('pending_payment')
    mountHarness()
    await flushPromises()

    expect(ctx.verify).toHaveBeenCalledTimes(1)
    expect(ctx.status.refreshStatus).not.toHaveBeenCalled()
  })

  it('reconciles again when the tab regains focus (visibilitychange → visible)', async () => {
    setupStatus('pending_payment')
    mountHarness()
    await flushPromises()
    expect(ctx.verify).toHaveBeenCalledTimes(1)

    ctx.verify.mockClear()
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(ctx.verify).toHaveBeenCalledTimes(1)
  })

  it('exposes hasPendingPayment derived from the CTA flags', async () => {
    setupStatus('pending_payment')
    const w = mountHarness()
    await flushPromises()
    expect(w.find('[data-pending="true"]').exists()).toBe(true)
  })

  it('stops reconciling once the payment is no longer pending', async () => {
    const { current, cta } = setupStatus('pending_payment')
    mountHarness()
    await flushPromises()
    expect(ctx.verify).toHaveBeenCalledTimes(1)

    // Payment resolved → CTAs re-enabled; a tab focus must no longer trigger verify.
    current.value = { tier: 'starter', plan: 'starter', status: 'active' }
    cta.value = ALL_CTA
    await flushPromises()
    ctx.verify.mockClear()

    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()
    expect(ctx.verify).not.toHaveBeenCalled()
  })

  it('derives paymentFailed from the server failed status at mount (no transition needed)', async () => {
    setupStatus('failed')
    mountHarness()
    await flushPromises()

    expect(api!.paymentFailed.value).toBe(true)
  })

  it('keeps paymentFailed false for a non-failed server status (active)', async () => {
    setupStatus('active')
    mountHarness()
    await flushPromises()

    expect(api!.paymentFailed.value).toBe(false)
  })

  it('dismissFailure hides the nudge and persists it across re-mounts (per-user key)', async () => {
    setupStatus('failed')
    mountHarness()
    await flushPromises()
    expect(api!.paymentFailed.value).toBe(true)

    api!.dismissFailure()
    expect(api!.paymentFailed.value).toBe(false)
    expect(localStorage.getItem(DISMISS_KEY)).not.toBeNull()

    // Navigation / reload = new component instance: the flag must survive.
    wrapper!.unmount()
    mountHarness()
    await flushPromises()
    expect(api!.paymentFailed.value).toBe(false)
  })

  it('clears the dismiss flag when a new pending attempt is observed, so a new failure re-surfaces', async () => {
    const { current, cta } = setupStatus('failed')
    mountHarness()
    await flushPromises()
    api!.dismissFailure()
    expect(localStorage.getItem(DISMISS_KEY)).not.toBeNull()

    // The Face retries: every retry goes through pending → the flag is erased.
    current.value = { tier: 'free', plan: 'starter', status: 'pending_payment' }
    cta.value = PENDING_CTA
    await flushPromises()
    expect(localStorage.getItem(DISMISS_KEY)).toBeNull()

    // The retry fails too → the nudge must come back.
    current.value = { tier: 'free', plan: 'starter', status: 'failed' }
    cta.value = ALL_CTA
    await flushPromises()
    expect(api!.paymentFailed.value).toBe(true)
  })

  it('clears the dismiss flag even when mounted while the payment is ALREADY pending (immediate watch)', async () => {
    // SPA navigation during an in-flight retry: the shared cache already says
    // pending when this instance mounts — no false→true transition happens,
    // the immediate watch is the only thing that erases the stale flag.
    localStorage.setItem(DISMISS_KEY, '1')
    setupStatus('pending_payment')
    mountHarness()
    await flushPromises()

    expect(localStorage.getItem(DISMISS_KEY)).toBeNull()
  })

  it('does not fire verify nor arm the poll when unmounted during the initial status fetch', async () => {
    vi.useFakeTimers()
    try {
      // Deferred fetchStatus: the component unmounts while it is in flight.
      let resolveFetch: () => void = () => {}
      setupStatus('pending_payment')
      ctx.status.fetchStatus = vi.fn(
        () => new Promise<void>((resolve) => (resolveFetch = resolve)),
      )

      mountHarness()
      wrapper!.unmount()
      wrapper = null

      resolveFetch()
      await Promise.resolve()
      await Promise.resolve()

      expect(ctx.verify).not.toHaveBeenCalled()
      // No orphan interval left behind either.
      await vi.advanceTimersByTimeAsync(20_000)
      expect(ctx.verify).not.toHaveBeenCalled()
    } finally {
      vi.useRealTimers()
    }
  })

  it('does not force a status refresh when verify resolves after the component unmounted', async () => {
    // Route change to an excluded route while the verify POST is in flight:
    // the continuation must not fire a forced GET on a dead component.
    let resolveVerify: (value: unknown) => void = () => {}
    ctx.verify.mockImplementation(() => new Promise((resolve) => (resolveVerify = resolve)))
    setupStatus('pending_payment')
    mountHarness()
    await flushPromises()
    expect(ctx.verify).toHaveBeenCalledTimes(1)

    wrapper!.unmount()
    wrapper = null

    resolveVerify({ data: { subscription_id: 'sub_1', status: 'active' } })
    await flushPromises()

    expect(ctx.status.refreshStatus).not.toHaveBeenCalled()
  })

  it('does not force a status refresh when the user logged out during verify (no token-less GET)', async () => {
    // Voluntary logout mid-tick: clearAuth removed the token while the verify
    // POST was in flight — the forced GET would 401 and bounce the user onto
    // login?message=session-expired.
    let resolveVerify: (value: unknown) => void = () => {}
    ctx.verify.mockImplementation(() => new Promise((resolve) => (resolveVerify = resolve)))
    setupStatus('pending_payment')
    mountHarness()
    await flushPromises()
    expect(ctx.verify).toHaveBeenCalledTimes(1)

    ctx.authUser = null
    resolveVerify({ data: { subscription_id: 'sub_1', status: 'active' } })
    await flushPromises()

    expect(ctx.status.refreshStatus).not.toHaveBeenCalled()
  })

  it('scopes the dismiss flag per user — another user on the same browser still sees the failure', async () => {
    setupStatus('failed')
    mountHarness()
    await flushPromises()
    api!.dismissFailure()
    wrapper!.unmount()

    // A different Face logs in on the same browser with her own failed payment.
    ctx.authUser = { id: 7 }
    setupStatus('failed')
    mountHarness()
    await flushPromises()

    expect(api!.paymentFailed.value).toBe(true)
  })

  it('handles a null user without crashing (no key written, in-memory dismiss only)', async () => {
    ctx.authUser = null
    setupStatus('failed')
    mountHarness()
    await flushPromises()
    expect(api!.paymentFailed.value).toBe(true)

    api!.dismissFailure()
    expect(api!.paymentFailed.value).toBe(false)
    expect(localStorage.length).toBe(0)
  })
})
