import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import PendingSubscriptionPaymentBanner from '../PendingSubscriptionPaymentBanner.vue'

const ctx = vi.hoisted(() => ({
  hasPendingPayment: { value: false } as { value: boolean },
  paymentFailed: { value: false } as { value: boolean },
  dismissFailure: vi.fn(),
  routerPush: vi.fn(),
}))

vi.mock('@/features/face/composables/useSubscriptionReconciler', () => ({
  useSubscriptionReconciler: () => ({
    hasPendingPayment: ctx.hasPendingPayment,
    paymentFailed: ctx.paymentFailed,
    dismissFailure: ctx.dismissFailure,
  }),
}))
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: ctx.routerPush }),
}))

describe('PendingSubscriptionPaymentBanner', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    ctx.hasPendingPayment = ref(false)
    ctx.paymentFailed = ref(false)
    ctx.dismissFailure = vi.fn()
  })

  it('renders when a payment is pending and routes to the billing tab', async () => {
    ctx.hasPendingPayment = ref(true)
    const wrapper = mount(PendingSubscriptionPaymentBanner)
    await flushPromises()

    expect(wrapper.find('[data-testid="pending-payment-banner"]').exists()).toBe(true)

    await wrapper.get('[data-testid="pending-payment-banner-cta"]').trigger('click')
    expect(ctx.routerPush).toHaveBeenCalledWith({ name: 'face-billing' })
  })

  it('stays hidden when no payment is pending', async () => {
    ctx.hasPendingPayment = ref(false)
    const wrapper = mount(PendingSubscriptionPaymentBanner)
    await flushPromises()
    expect(wrapper.find('[data-testid="pending-payment-banner"]').exists()).toBe(false)
  })

  it('shows the failure nudge when a payment failed: retry → /pricing, dismiss → dismissFailure', async () => {
    ctx.hasPendingPayment = ref(false)
    ctx.paymentFailed = ref(true)
    const wrapper = mount(PendingSubscriptionPaymentBanner)
    await flushPromises()

    expect(wrapper.find('[data-testid="pending-payment-failed-banner"]').exists()).toBe(true)

    await wrapper.get('[data-testid="pending-payment-failed-retry"]').trigger('click')
    expect(ctx.routerPush).toHaveBeenCalledWith({ name: 'pricing' })

    await wrapper.get('[data-testid="pending-payment-failed-dismiss"]').trigger('click')
    expect(ctx.dismissFailure).toHaveBeenCalled()
  })

  it('prefers the pending nudge over the failure nudge', async () => {
    ctx.hasPendingPayment = ref(true)
    ctx.paymentFailed = ref(true)
    const wrapper = mount(PendingSubscriptionPaymentBanner)
    await flushPromises()

    expect(wrapper.find('[data-testid="pending-payment-banner"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="pending-payment-failed-banner"]').exists()).toBe(false)
  })
})
