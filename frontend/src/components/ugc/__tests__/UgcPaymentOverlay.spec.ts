import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import UgcPaymentOverlay from '../UgcPaymentOverlay.vue'
import { bookingApi } from '@/features/booking/services/bookingApi'
import { missionApi } from '@/features/mission/services/missionApi'
import type { BookingResponse } from '@/features/booking/types'
import type { MissionResponse } from '@/features/mission/types'

vi.mock('@/features/booking/services/bookingApi', () => ({
  bookingApi: { payCommission: vi.fn(), checkCommissionStatus: vi.fn() },
}))
vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: { payCommission: vi.fn(), getCommissionStatus: vi.fn() },
}))

const bookingCheckout = (): BookingResponse & { checkout_url: string } =>
  ({ data: { id: 'b1' }, checkout_url: 'https://fedapay.test/x' }) as unknown as BookingResponse & {
    checkout_url: string
  }
const missionCheckout = (): MissionResponse & { checkout_url: string } =>
  ({ data: { id: 'm1' }, checkout_url: 'https://fedapay.test/y' }) as unknown as MissionResponse & {
    checkout_url: string
  }
const bookingStatus = (status: string): BookingResponse =>
  ({ data: { status } }) as unknown as BookingResponse
const missionStatus = (status: string): MissionResponse =>
  ({ data: { status } }) as unknown as MissionResponse

type OverlayProps = {
  modelValue?: boolean
  kind?: 'booking' | 'mission'
  ownerId?: string
  amount?: number
  reference?: string
}

function mountOverlay(props: OverlayProps = {}) {
  return mount(UgcPaymentOverlay, {
    props: {
      modelValue: true,
      kind: 'booking',
      ownerId: 'b1',
      amount: 4500,
      reference: 'abcd1234efgh',
      ...props,
    },
    global: { stubs: { teleport: true } },
  })
}

describe('UgcPaymentOverlay', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('open', vi.fn())
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('renders the booking select step with the règlement title, amount, escrow copy and 3 pay tiles', () => {
    const wrapper = mountOverlay({ amount: 4500 })
    expect(wrapper.text()).toContain('Paiement du règlement')
    expect(wrapper.text().replace(/\s/g, ' ')).toContain('4 500 FCFA')
    expect(wrapper.text()).toContain('séquestrée par WeAct')
    expect(wrapper.findAll('[data-testid="pay-tile"]')).toHaveLength(3)
    expect(wrapper.text()).toContain('Payer via FedaPay')
  })

  it('renders the mission select step with the commission title and commission copy (unchanged)', () => {
    const wrapper = mountOverlay({ kind: 'mission', ownerId: 'm1', amount: 4500 })
    expect(wrapper.text()).toContain('Paiement de la commission')
    expect(wrapper.text()).toContain("La commission n'est encaissée")
  })

  it('pays then confirms on commission_paid and emits settled (booking)', async () => {
    vi.useFakeTimers()
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout())
    vi.mocked(bookingApi.checkCommissionStatus).mockResolvedValue(bookingStatus('commission_paid'))

    const wrapper = mountOverlay({ kind: 'booking', ownerId: 'b1', reference: 'abcd1234efgh' })
    await wrapper.find('[data-testid="ugc-pay-button"]').trigger('click')
    await flushPromises()

    expect(bookingApi.payCommission).toHaveBeenCalledWith('b1')
    expect(window.open).toHaveBeenCalledWith(
      'https://fedapay.test/x',
      '_blank',
      'noopener,noreferrer',
    )
    expect(wrapper.text()).toContain('En attente de votre paiement')

    await vi.advanceTimersByTimeAsync(5000)
    await flushPromises()

    expect(wrapper.text()).toContain('Commission payée')
    expect(wrapper.text()).toContain('Votre demande a été envoyée à la Face.')
    expect(wrapper.text()).toContain('Réf. ABCD1234')

    await wrapper.find('[data-testid="ugc-done-button"]').trigger('click')
    expect(wrapper.emitted('settled')).toBeTruthy()
  })

  it('shows the mission-specific success subtitle after publishing', async () => {
    vi.useFakeTimers()
    vi.mocked(missionApi.payCommission).mockResolvedValue(missionCheckout())
    vi.mocked(missionApi.getCommissionStatus).mockResolvedValue(missionStatus('published'))

    const wrapper = mountOverlay({ kind: 'mission', ownerId: 'm1' })
    await wrapper.find('[data-testid="ugc-pay-button"]').trigger('click')
    await flushPromises()
    await vi.advanceTimersByTimeAsync(5000)
    await flushPromises()

    expect(missionApi.payCommission).toHaveBeenCalledWith('m1')
    expect(wrapper.text()).toContain('Votre mission est maintenant publiée.')
  })

  it('shows the failed step with a retry button when initiation throws', async () => {
    vi.mocked(bookingApi.payCommission).mockRejectedValue(new Error('boom'))

    const wrapper = mountOverlay()
    await wrapper.find('[data-testid="ugc-pay-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Paiement échoué')
    expect(wrapper.find('[data-testid="ugc-retry-button"]').exists()).toBe(true)
  })

  it('renders nothing when modelValue is false', () => {
    const wrapper = mountOverlay({ modelValue: false })
    expect(wrapper.find('[data-testid="ugc-payment-overlay"]').exists()).toBe(false)
  })
})
