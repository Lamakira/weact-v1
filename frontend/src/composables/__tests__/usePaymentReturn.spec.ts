import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { usePaymentReturn, type UsePaymentReturnOptions } from '../usePaymentReturn'
import { bookingApi } from '@/features/booking/services/bookingApi'
import { missionApi } from '@/features/mission/services/missionApi'
import { candidatureApi } from '@/features/candidature/services/candidatureApi'
import { faceApi } from '@/features/face/services/faceApi'

const ctx = vi.hoisted(() => ({
  route: { query: {} as Record<string, unknown> },
  replace: vi.fn().mockResolvedValue(undefined),
  toast: { success: vi.fn(), error: vi.fn() },
  refreshStatus: vi.fn().mockResolvedValue(undefined),
  current: { value: null as { status: string } | null },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ctx.route,
  useRouter: () => ({ replace: ctx.replace }),
}))
vi.mock('@/composables/useToast', () => ({ useToast: () => ctx.toast }))
vi.mock('@/features/booking/services/bookingApi', () => ({
  bookingApi: { checkPaymentStatus: vi.fn(), checkCommissionStatus: vi.fn() },
}))
vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: { getPaymentStatus: vi.fn(), getCommissionStatus: vi.fn() },
}))
vi.mock('@/features/candidature/services/candidatureApi', () => ({
  candidatureApi: { getCandidaturePaymentStatus: vi.fn() },
}))
vi.mock('@/features/face/services/faceApi', () => ({
  faceApi: { verifySubscriptionPayment: vi.fn() },
}))
vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ({ refreshStatus: ctx.refreshStatus, current: ctx.current }),
}))

function mountReturn(options: Partial<UsePaymentReturnOptions> = {}) {
  let api!: ReturnType<typeof usePaymentReturn>
  const wrapper = mount(
    defineComponent({
      setup() {
        api = usePaymentReturn({
          kinds: ['booking', 'booking_commission', 'mission_commission', 'candidature_escrow', 'mission_selection', 'subscription'],
          ids: () => ({ bookingId: 'b-uuid', missionId: 'm-route' }),
          ...options,
        })
        return () => h('div')
      },
    }),
  )
  return { api, wrapper }
}

const bookingRes = (status: string, extra: Record<string, unknown> = {}) =>
  ({ data: { status }, ...extra }) as never

describe('usePaymentReturn', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
    ctx.route.query = {}
    ctx.current.value = null
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('does nothing without ?payment_return', async () => {
    const { api } = mountReturn()

    expect(await api.start()).toBe(false)
    expect(api.state.value).toBe('idle')
    expect(bookingApi.checkPaymentStatus).not.toHaveBeenCalled()
    expect(ctx.replace).not.toHaveBeenCalled()
  })

  it('ignores a payment_return kind the page is not a destination for', async () => {
    ctx.route.query = { payment_return: 'subscription' }
    const { api } = mountReturn({ kinds: ['booking'] })

    expect(await api.start()).toBe(false)
    expect(faceApi.verifySubscriptionPayment).not.toHaveBeenCalled()
    expect(ctx.replace).not.toHaveBeenCalled()
  })

  it('booking: shows the verifying state, confirms, calls onConfirmed, toasts and cleans the URL', async () => {
    ctx.route.query = { payment_return: 'booking', status: 'approved', other: 'keep' }
    vi.mocked(bookingApi.checkPaymentStatus)
      .mockResolvedValueOnce(bookingRes('accepted'))
      .mockResolvedValueOnce(bookingRes('paid'))
    const onConfirmed = vi.fn()
    const { api } = mountReturn({ onConfirmed })

    await api.start()
    expect(api.state.value).toBe('verifying')
    expect(bookingApi.checkPaymentStatus).toHaveBeenCalledWith('b-uuid')

    await vi.advanceTimersByTimeAsync(5000)
    await flushPromises()

    expect(api.state.value).toBe('confirmed')
    expect(onConfirmed).toHaveBeenCalledWith('booking')
    expect(ctx.toast.success).toHaveBeenCalledTimes(1)
    // payment_return removed, unrelated keys preserved; the FedaPay status param is never read.
    expect(ctx.replace).toHaveBeenCalledWith({ query: { status: 'approved', other: 'keep' } })
  })

  it('booking_commission: polls the commission-status endpoint, not the cash one', async () => {
    ctx.route.query = { payment_return: 'booking_commission' }
    vi.mocked(bookingApi.checkCommissionStatus).mockResolvedValue(bookingRes('commission_paid'))
    const { api } = mountReturn()

    await api.start()

    expect(bookingApi.checkCommissionStatus).toHaveBeenCalledWith('b-uuid')
    expect(bookingApi.checkPaymentStatus).not.toHaveBeenCalled()
    expect(api.state.value).toBe('confirmed')
  })

  it('mission_commission: polls the mission commission-status with the ?mission id and strips the companion ids', async () => {
    ctx.route.query = { payment_return: 'mission_commission', mission: 'm-query' }
    vi.mocked(missionApi.getCommissionStatus).mockResolvedValue(bookingRes('published'))
    const { api } = mountReturn()

    await api.start()

    expect(missionApi.getCommissionStatus).toHaveBeenCalledWith('m-query')
    expect(missionApi.getPaymentStatus).not.toHaveBeenCalled()
    expect(api.state.value).toBe('confirmed')
    expect(ctx.replace).toHaveBeenCalledWith({ query: {} })
  })

  it('candidature_escrow: polls the candidature payment-status, never the cash mission one', async () => {
    ctx.route.query = { payment_return: 'candidature_escrow', candidature: 'c-1' }
    vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue({
      data: { candidature_status: 'accepted', payment_status: 'paid', is_trackable: false },
    })
    const { api } = mountReturn()

    await api.start()

    expect(candidatureApi.getCandidaturePaymentStatus).toHaveBeenCalledWith('c-1')
    expect(missionApi.getPaymentStatus).not.toHaveBeenCalled()
    expect(api.state.value).toBe('confirmed')
  })

  it('mission_selection: polls the cash mission payment-status with the route mission id', async () => {
    ctx.route.query = { payment_return: 'mission_selection' }
    vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
      data: { has_payment: true, is_trackable: true, status: 'paid', mission_status: 'closed' },
    })
    const { api } = mountReturn()

    await api.start()

    expect(missionApi.getPaymentStatus).toHaveBeenCalledWith('m-route')
    expect(api.state.value).toBe('confirmed')
  })

  it('subscription: verify-payment then refreshes the status; confirmed on active', async () => {
    ctx.route.query = { payment_return: 'subscription' }
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue({
      data: { subscription_id: 's', status: 'active' },
    } as never)
    const { api } = mountReturn()

    await api.start()

    expect(faceApi.verifySubscriptionPayment).toHaveBeenCalledOnce()
    expect(ctx.refreshStatus).toHaveBeenCalledOnce()
    expect(api.state.value).toBe('confirmed')
    expect(ctx.toast.success).toHaveBeenCalledWith('Votre abonnement est activé.')
  })

  it('subscription: confirmed when the webhook already activated it (verify=free, current=active)', async () => {
    ctx.route.query = { payment_return: 'subscription' }
    ctx.current.value = { status: 'active' }
    vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue({
      data: { subscription_id: null, status: 'free' },
    } as never)
    const { api } = mountReturn()

    await api.start()

    expect(api.state.value).toBe('confirmed')
  })

  it('failed: surfaces the failed state (no toast), keeps polling off, cleans the URL; retry hands over to onRetry', async () => {
    ctx.route.query = { payment_return: 'booking_commission' }
    vi.mocked(bookingApi.checkCommissionStatus).mockResolvedValue(
      bookingRes('pending', { commission_payment_status: 'failed' }),
    )
    const onRetry = vi.fn()
    const { api } = mountReturn({ onRetry })

    await api.start()

    expect(api.state.value).toBe('failed')
    expect(ctx.toast.success).not.toHaveBeenCalled()
    expect(ctx.replace).toHaveBeenCalledOnce()

    await vi.advanceTimersByTimeAsync(30000)
    expect(bookingApi.checkCommissionStatus).toHaveBeenCalledOnce()

    api.retry()
    expect(api.state.value).toBe('idle')
    expect(onRetry).toHaveBeenCalledWith('booking_commission', expect.objectContaining({ bookingId: 'b-uuid' }))
  })

  it('timeout after 120 s is NOT an error: dedicated state, no failure, URL cleaned', async () => {
    ctx.route.query = { payment_return: 'booking' }
    vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes('accepted'))
    const { api } = mountReturn()

    await api.start()
    await vi.advanceTimersByTimeAsync(119000)
    expect(api.state.value).toBe('verifying')

    await vi.advanceTimersByTimeAsync(1000)
    await flushPromises()

    expect(api.state.value).toBe('timeout')
    expect(ctx.toast.success).not.toHaveBeenCalled()
    expect(ctx.toast.error).not.toHaveBeenCalled()
    // immediate check + one per 5 s up to 120 s
    expect(bookingApi.checkPaymentStatus).toHaveBeenCalledTimes(25)
    expect(ctx.replace).toHaveBeenCalledOnce()
  })

  it('keeps polling through transient API errors', async () => {
    ctx.route.query = { payment_return: 'booking' }
    vi.mocked(bookingApi.checkPaymentStatus)
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValueOnce(bookingRes('paid'))
    const { api } = mountReturn()

    await api.start()
    expect(api.state.value).toBe('verifying')
    await vi.advanceTimersByTimeAsync(5000)
    await flushPromises()

    expect(api.state.value).toBe('confirmed')
  })

  it('stops polling when the component unmounts', async () => {
    ctx.route.query = { payment_return: 'booking' }
    vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes('accepted'))
    const { api, wrapper } = mountReturn()

    await api.start()
    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(60000)

    expect(bookingApi.checkPaymentStatus).toHaveBeenCalledOnce()
  })
})
