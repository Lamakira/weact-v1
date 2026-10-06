import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import PaymentReturnView from '../PaymentReturnView.vue'

const ctx = vi.hoisted(() => ({
  route: { query: {} as Record<string, unknown> },
  push: vi.fn(),
  userType: 'Face' as string,
}))

vi.mock('vue-router', () => ({
  useRoute: () => ctx.route,
  useRouter: () => ({ push: ctx.push }),
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: { userable_type: ctx.userType } }),
}))

describe('PaymentReturnView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    ctx.route.query = {}
    ctx.userType = 'Face'
  })

  it.each(['canceled', 'declined'])('fedapay_status=%s → cancelled/refused message', (status) => {
    ctx.route.query = { fedapay_status: status }
    const wrapper = mount(PaymentReturnView)

    expect(wrapper.get('[data-testid="payment-return-message"]').text()).toBe(
      'Votre paiement a été annulé ou refusé. Vous pouvez réessayer depuis la page concernée.',
    )
  })

  it('fedapay_status=approved → received message', () => {
    ctx.route.query = { fedapay_status: 'approved' }
    const wrapper = mount(PaymentReturnView)

    expect(wrapper.get('[data-testid="payment-return-message"]').text()).toBe(
      'Paiement reçu, sa confirmation peut prendre quelques instants.',
    )
  })

  it.each([{}, { fedapay_status: 'whatever' }])('absent or unknown status (%o) → cannot identify', (query) => {
    ctx.route.query = query
    const wrapper = mount(PaymentReturnView)

    expect(wrapper.get('[data-testid="payment-return-message"]').text()).toBe(
      'Nous n\'avons pas pu identifier ce paiement.',
    )
  })

  it('the button goes to the Face dashboard for a Face', async () => {
    const wrapper = mount(PaymentReturnView)
    await wrapper.get('[data-testid="payment-return-dashboard"]').trigger('click')

    expect(ctx.push).toHaveBeenCalledWith({ name: 'face-dashboard' })
  })

  it('the button goes to the Producer dashboard for a Producer', async () => {
    ctx.userType = 'Producer'
    const wrapper = mount(PaymentReturnView)
    await wrapper.get('[data-testid="payment-return-dashboard"]').trigger('click')

    expect(ctx.push).toHaveBeenCalledWith({ name: 'producer-dashboard' })
  })
})
