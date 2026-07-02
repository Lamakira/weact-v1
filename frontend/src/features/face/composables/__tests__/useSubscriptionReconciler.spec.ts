import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { useSubscriptionReconciler } from '../useSubscriptionReconciler'

const ctx = vi.hoisted(() => ({
  status: {} as Record<string, unknown>,
  verify: vi.fn(),
}))

vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ctx.status,
}))
vi.mock('../../services/faceApi', () => ({
  faceApi: { verifySubscriptionPayment: ctx.verify },
}))

const ALL_CTA = { upgrade_available: true, downgrade_available: true, renew_available: true }
const PENDING_CTA = { upgrade_available: false, downgrade_available: false, renew_available: false }

function setupStatus(opts: { pending: boolean }): void {
  ctx.status.current = ref(
    opts.pending
      ? { tier: 'free', plan: 'starter', status: 'pending_payment' }
      : { tier: 'free', plan: null, status: 'free' },
  )
  ctx.status.cta = ref(opts.pending ? PENDING_CTA : ALL_CTA)
  ctx.status.fetchStatus = vi.fn().mockResolvedValue(undefined)
  ctx.status.refreshStatus = vi.fn().mockResolvedValue(undefined)
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
    ctx.verify.mockResolvedValue({ data: { subscription_id: 'sub_1', status: 'active' } })
    // jsdom defaults visibilityState to 'visible'.
  })

  // Unmount between tests so the global visibilitychange listener is removed
  // (onUnmounted) and does not leak into the next test's dispatch.
  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    api = null
  })

  it('auto-reconciles on mount when a payment is pending (verify + refresh)', async () => {
    setupStatus({ pending: true })
    mountHarness()
    await flushPromises()

    expect(ctx.status.fetchStatus).toHaveBeenCalled()
    expect(ctx.verify).toHaveBeenCalledTimes(1)
    expect(ctx.status.refreshStatus).toHaveBeenCalled()
  })

  it('does NOT call verify on mount when no payment is pending', async () => {
    setupStatus({ pending: false })
    mountHarness()
    await flushPromises()

    expect(ctx.status.fetchStatus).toHaveBeenCalled()
    expect(ctx.verify).not.toHaveBeenCalled()
  })

  it('reconciles again when the tab regains focus (visibilitychange → visible)', async () => {
    setupStatus({ pending: true })
    mountHarness()
    await flushPromises()
    expect(ctx.verify).toHaveBeenCalledTimes(1)

    ctx.verify.mockClear()
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(ctx.verify).toHaveBeenCalledTimes(1)
  })

  it('exposes hasPendingPayment derived from the CTA flags', async () => {
    setupStatus({ pending: true })
    const w = mountHarness()
    await flushPromises()
    expect(w.find('[data-pending="true"]').exists()).toBe(true)
  })

  it('stops reconciling once the payment is no longer pending', async () => {
    setupStatus({ pending: true })
    mountHarness()
    await flushPromises()
    expect(ctx.verify).toHaveBeenCalledTimes(1)

    // Payment resolved → CTAs re-enabled; a tab focus must no longer trigger verify.
    ctx.status.current.value = { tier: 'starter', plan: 'starter', status: 'active' }
    ctx.status.cta.value = ALL_CTA
    await flushPromises()
    ctx.verify.mockClear()

    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()
    expect(ctx.verify).not.toHaveBeenCalled()
  })

  it('surfaces paymentFailed when a watched pending payment resolves to failed', async () => {
    setupStatus({ pending: true })
    mountHarness()
    await flushPromises()
    expect(api!.paymentFailed.value).toBe(false)

    // Fedapay declined: CTAs re-enable + the row flips to failed.
    ctx.status.current.value = { tier: 'free', plan: 'starter', status: 'failed' }
    ctx.status.cta.value = ALL_CTA
    await flushPromises()

    expect(api!.paymentFailed.value).toBe(true)
  })

  it('does NOT set paymentFailed when the payment resolves to active (success)', async () => {
    setupStatus({ pending: true })
    mountHarness()
    await flushPromises()

    ctx.status.current.value = { tier: 'pro', plan: 'pro', status: 'active' }
    ctx.status.cta.value = ALL_CTA
    await flushPromises()

    expect(api!.paymentFailed.value).toBe(false)
  })

  it('dismissFailure clears the failure flag', async () => {
    setupStatus({ pending: true })
    mountHarness()
    await flushPromises()
    ctx.status.current.value = { tier: 'free', plan: 'starter', status: 'failed' }
    ctx.status.cta.value = ALL_CTA
    await flushPromises()
    expect(api!.paymentFailed.value).toBe(true)

    api!.dismissFailure()
    expect(api!.paymentFailed.value).toBe(false)
  })
})
