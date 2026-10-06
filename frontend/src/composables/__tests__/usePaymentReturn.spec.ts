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

  describe('subscription snapshot guard (renewal / upgrade of an already active Face)', () => {
    const snapshotKey = 'weact.auth.subscription-payment-snapshot'
    const verify = (status: string) =>
      vi.mocked(faceApi.verifySubscriptionPayment).mockResolvedValue({
        data: { subscription_id: 's', status },
      } as never)

    beforeEach(() => {
      sessionStorage.clear()
      ctx.route.query = { payment_return: 'subscription' }
    })

    it('does NOT confirm when the status still reports the OLD active row (verify=free, nothing changed vs the snapshot)', async () => {
      sessionStorage.setItem(snapshotKey, JSON.stringify({ tier: 'pro', expires_at: '2027-01-01T00:00:00Z' }))
      ctx.current.value = { status: 'active', tier: 'pro', expires_at: '2027-01-01T00:00:00Z' } as never
      verify('free')
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('verifying')
      expect(ctx.toast.success).not.toHaveBeenCalled()
    })

    it('confirms when the tier changed vs the snapshot', async () => {
      sessionStorage.setItem(snapshotKey, JSON.stringify({ tier: 'pro', expires_at: '2027-01-01T00:00:00Z' }))
      ctx.current.value = { status: 'active', tier: 'elite', expires_at: '2027-01-01T00:00:00Z' } as never
      verify('free')
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('confirmed')
    })

    it('confirms when expires_at moved forward vs the snapshot (same tier renewal)', async () => {
      sessionStorage.setItem(snapshotKey, JSON.stringify({ tier: 'pro', expires_at: '2027-01-01T00:00:00Z' }))
      ctx.current.value = { status: 'active', tier: 'pro', expires_at: '2028-01-01T00:00:00Z' } as never
      verify('active')
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('confirmed')
    })

    it('does NOT confirm when expires_at is unchanged even if verify says active (snapshot is the stricter proof)', async () => {
      sessionStorage.setItem(snapshotKey, JSON.stringify({ tier: 'pro', expires_at: '2027-01-01T00:00:00Z' }))
      ctx.current.value = { status: 'active', tier: 'pro', expires_at: '2027-01-01T00:00:00Z' } as never
      verify('active')
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('verifying')
    })

    it('without a snapshot, falls back to verify-payment only: free + active status is NOT a confirmation', async () => {
      ctx.current.value = { status: 'active', tier: 'pro', expires_at: '2027-01-01T00:00:00Z' } as never
      verify('free')
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('verifying')
    })

    it('without a snapshot, verify=active confirms', async () => {
      verify('active')
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('confirmed')
    })

    it('clears the snapshot once the flow is over', async () => {
      sessionStorage.setItem(snapshotKey, JSON.stringify({ tier: 'free', expires_at: null }))
      ctx.current.value = { status: 'active', tier: 'pro', expires_at: '2027-01-01T00:00:00Z' } as never
      verify('active')
      const { api } = mountReturn()

      await api.start()

      expect(sessionStorage.getItem(snapshotKey)).toBeNull()
    })
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

  it.each(['canceled', 'declined'])(
    'fedapay_status=%s asks the server ONCE, then fails right away without polling (hint only)',
    async (hint) => {
      ctx.route.query = { payment_return: 'booking', fedapay_status: hint }
      vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes('accepted'))
      const onRetry = vi.fn()
      const { api } = mountReturn({ onRetry })

      await api.start()

      expect(api.state.value).toBe('failed')
      expect(bookingApi.checkPaymentStatus).toHaveBeenCalledOnce()
      expect(ctx.toast.success).not.toHaveBeenCalled()
      expect(ctx.replace).toHaveBeenCalledWith({ query: {} })

      await vi.advanceTimersByTimeAsync(30000)
      expect(bookingApi.checkPaymentStatus).toHaveBeenCalledOnce()

      api.retry()
      expect(onRetry).toHaveBeenCalledWith('booking', expect.anything())
    },
  )

  it('a crafted ?fedapay_status=canceled on a payment the server says is settled shows the success, not a false failure', async () => {
    ctx.route.query = { payment_return: 'booking', fedapay_status: 'canceled' }
    vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes('paid'))
    const { api } = mountReturn()

    await api.start()

    expect(api.state.value).toBe('confirmed')
    expect(ctx.toast.success).toHaveBeenCalledOnce()
  })

  it('fedapay_status=approved keeps the normal polling', async () => {
    ctx.route.query = { payment_return: 'booking', fedapay_status: 'approved' }
    vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes('accepted'))
    const { api } = mountReturn()

    await api.start()

    expect(api.state.value).toBe('verifying')
    expect(bookingApi.checkPaymentStatus).toHaveBeenCalledOnce()
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

  describe('outcome mapping (each branch is load-bearing)', () => {
    it.each(['refused', 'expired', 'cancelled_by_producer', 'cancelled_by_face'])(
      'booking: dead status %s is a failure, not a success',
      async (status) => {
        ctx.route.query = { payment_return: 'booking' }
        vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes(status))
        const { api } = mountReturn()

        await api.start()

        expect(api.state.value).toBe('failed')
      },
    )

    it('booking: a later status (in_progress) is settled', async () => {
      ctx.route.query = { payment_return: 'booking' }
      vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes('in_progress'))
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('confirmed')
    })

    it('booking_commission: still pending keeps polling (never settled)', async () => {
      ctx.route.query = { payment_return: 'booking_commission' }
      vi.mocked(bookingApi.checkCommissionStatus).mockResolvedValue(bookingRes('pending'))
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('verifying')
    })

    it('booking_commission: a later status (accepted by the Face) is settled', async () => {
      ctx.route.query = { payment_return: 'booking_commission' }
      vi.mocked(bookingApi.checkCommissionStatus).mockResolvedValue(bookingRes('accepted'))
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('confirmed')
    })

    it('booking_commission: dead booking status is a failure', async () => {
      ctx.route.query = { payment_return: 'booking_commission' }
      vi.mocked(bookingApi.checkCommissionStatus).mockResolvedValue(bookingRes('expired'))
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('failed')
    })

    it('mission_commission: commission_payment_status=failed is a failure', async () => {
      ctx.route.query = { payment_return: 'mission_commission', mission: 'm-1' }
      vi.mocked(missionApi.getCommissionStatus).mockResolvedValue(
        bookingRes('pending_payment', { commission_payment_status: 'failed' }),
      )
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('failed')
    })

    it('mission_commission: still pending_payment keeps polling', async () => {
      ctx.route.query = { payment_return: 'mission_commission', mission: 'm-1' }
      vi.mocked(missionApi.getCommissionStatus).mockResolvedValue(bookingRes('pending_payment'))
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('verifying')
    })

    it('candidature_escrow: payment_status=failed is a failure', async () => {
      ctx.route.query = { payment_return: 'candidature_escrow', candidature: 'c-1' }
      vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue({
        data: { candidature_status: 'pending', payment_status: 'failed', is_trackable: true },
      })
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('failed')
    })

    it('candidature_escrow: pending but NOT trackable (webhook already removed the entry) is a failure', async () => {
      ctx.route.query = { payment_return: 'candidature_escrow', candidature: 'c-1' }
      vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue({
        data: { candidature_status: 'pending', payment_status: 'pending', is_trackable: false },
      })
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('failed')
    })

    it('candidature_escrow: pending and trackable keeps polling', async () => {
      ctx.route.query = { payment_return: 'candidature_escrow', candidature: 'c-1' }
      vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue({
        data: { candidature_status: 'pending', payment_status: 'pending', is_trackable: true },
      })
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('verifying')
    })

    it.each([
      ['failed', { has_payment: true, is_trackable: true, status: 'failed' }],
      ['refunded', { has_payment: true, is_trackable: true, status: 'refunded' }],
      ['not trackable (transaction released server-side)', { has_payment: true, is_trackable: false, status: 'pending' }],
      ['no payment row', { has_payment: false, is_trackable: false, status: undefined }],
    ])('mission_selection: %s is a failure', async (_label, data) => {
      ctx.route.query = { payment_return: 'mission_selection' }
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { ...data, mission_status: 'pending_payment' },
      } as never)
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('failed')
    })

    it('mission_selection: pending and trackable keeps polling', async () => {
      ctx.route.query = { payment_return: 'mission_selection' }
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { has_payment: true, is_trackable: true, status: 'pending', mission_status: 'pending_payment' },
      })
      const { api } = mountReturn()

      await api.start()

      expect(api.state.value).toBe('verifying')
    })
  })

  describe('robustness', () => {
    it('onFinished is called after the cleanup with the outcome', async () => {
      ctx.route.query = { payment_return: 'booking' }
      vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes('paid'))
      const onFinished = vi.fn()
      const { api } = mountReturn({ onFinished })

      await api.start()

      expect(onFinished).toHaveBeenCalledWith('booking', 'confirmed')
    })

    it('does not strip the query of another route when the user already navigated away', async () => {
      ctx.route = { ...ctx.route, path: '/producer/missions', query: { payment_return: 'booking' } } as never
      vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes('paid'))
      const { api } = mountReturn()

      const started = api.start()
      ctx.route.path = '/somewhere/else'
      await started

      expect(ctx.replace).not.toHaveBeenCalled()
      delete (ctx.route as { path?: string }).path
    })

    it('no success toast when the page was torn down while onConfirmed was running', async () => {
      ctx.route.query = { payment_return: 'booking' }
      vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes('paid'))
      const holder: { unmount?: () => void } = {}
      const { api, wrapper } = mountReturn({
        onConfirmed: async () => {
          holder.unmount?.()
        },
      })
      holder.unmount = () => wrapper.unmount()

      await api.start()

      expect(ctx.toast.success).not.toHaveBeenCalled()
      expect(ctx.replace).not.toHaveBeenCalled()
    })

    it('stops polling and goes back to idle when the page is deactivated (keep-alive)', async () => {
      ctx.route.query = { payment_return: 'booking' }
      vi.mocked(bookingApi.checkPaymentStatus).mockResolvedValue(bookingRes('accepted'))
      const { api, wrapper } = mountReturn()

      await api.start()
      expect(api.state.value).toBe('verifying')

      // KeepAlive deactivation hook of the owning component instance.
      const instance = wrapper.vm.$ as unknown as { da?: Array<() => void> }
      instance.da?.forEach((hook) => hook())
      await vi.advanceTimersByTimeAsync(60000)

      expect(api.state.value).toBe('idle')
      expect(bookingApi.checkPaymentStatus).toHaveBeenCalledOnce()
    })
  })
})
