import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { computed, defineComponent, h, ref, type Ref } from 'vue'
import { useSubscriptionReconciler } from '../useSubscriptionReconciler'

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
  useAuthStore: () => ({ user: ctx.authUser }),
}))

const ALL_CTA = { upgrade_available: true, downgrade_available: true, renew_available: true }
const PENDING_CTA = { upgrade_available: false, downgrade_available: false, renew_available: false }

const DISMISS_KEY = 'face-subscription-failure-dismissed:42'

type StatusName = 'free' | 'pending_payment' | 'failed' | 'active'

function setupStatus(status: StatusName): Ref<Record<string, unknown> | null> {
  const current = ref<Record<string, unknown> | null>(
    status === 'free'
      ? { tier: 'free', plan: null, status: 'free' }
      : { tier: status === 'active' ? 'starter' : 'free', plan: 'starter', status },
  )
  ctx.status.current = current
  // Mirrors useSubscriptionStatus l.77 — the server-derived status value.
  ctx.status.statusValue = computed(() => current.value?.status ?? 'free')
  ctx.status.cta = ref(status === 'pending_payment' ? PENDING_CTA : ALL_CTA)
  ctx.status.fetchStatus = vi.fn().mockResolvedValue(undefined)
  ctx.status.refreshStatus = vi.fn().mockResolvedValue(undefined)
  return current
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
    const current = setupStatus('pending_payment')
    mountHarness()
    await flushPromises()
    expect(ctx.verify).toHaveBeenCalledTimes(1)

    // Payment resolved → CTAs re-enabled; a tab focus must no longer trigger verify.
    current.value = { tier: 'starter', plan: 'starter', status: 'active' }
    ;(ctx.status.cta as Ref<unknown>).value = ALL_CTA
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
    const current = setupStatus('failed')
    mountHarness()
    await flushPromises()
    api!.dismissFailure()
    expect(localStorage.getItem(DISMISS_KEY)).not.toBeNull()

    // The Face retries: every retry goes through pending → the flag is erased.
    current.value = { tier: 'free', plan: 'starter', status: 'pending_payment' }
    ;(ctx.status.cta as Ref<unknown>).value = PENDING_CTA
    await flushPromises()
    expect(localStorage.getItem(DISMISS_KEY)).toBeNull()

    // The retry fails too → the nudge must come back.
    current.value = { tier: 'free', plan: 'starter', status: 'failed' }
    ;(ctx.status.cta as Ref<unknown>).value = ALL_CTA
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
