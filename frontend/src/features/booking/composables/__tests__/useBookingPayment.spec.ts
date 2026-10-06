import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useBookingPayment } from '../useBookingPayment'
import { bookingApi } from '../../services/bookingApi'
import { redirectToCheckout } from '@/lib/redirectToCheckout'

vi.mock('../../services/bookingApi', () => ({
  bookingApi: { payBooking: vi.fn(), checkPaymentStatus: vi.fn() },
}))
vi.mock('@/lib/redirectToCheckout', () => ({ redirectToCheckout: vi.fn() }))
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn(() => 'Erreur de paiement'),
}))

describe('useBookingPayment', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('open', vi.fn())
  })

  it('redirects the same tab to the FedaPay checkout and never calls window.open', async () => {
    vi.mocked(bookingApi.payBooking).mockResolvedValue({
      data: { id: 'b1' },
      checkout_url: 'https://checkout.fedapay.test/b1',
    } as never)

    const { initiatePayment, paymentStatus, error } = useBookingPayment()
    const booking = await initiatePayment('b1')

    expect(bookingApi.payBooking).toHaveBeenCalledWith('b1')
    expect(redirectToCheckout).toHaveBeenCalledWith('https://checkout.fedapay.test/b1')
    expect(window.open).not.toHaveBeenCalled()
    expect(booking).toEqual({ id: 'b1' })
    expect(paymentStatus.value).toBe('waiting')
    expect(error.value).toBeNull()
    expect(bookingApi.checkPaymentStatus).not.toHaveBeenCalled()
  })

  it('does not redirect and reports the error when the API call fails', async () => {
    vi.mocked(bookingApi.payBooking).mockRejectedValue(new Error('boom'))

    const { initiatePayment, paymentStatus, error } = useBookingPayment()
    const booking = await initiatePayment('b1')

    expect(booking).toBeNull()
    expect(redirectToCheckout).not.toHaveBeenCalled()
    expect(paymentStatus.value).toBe('failed')
    expect(error.value).toBe('Erreur de paiement')
  })
})
