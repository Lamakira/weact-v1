import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useUgcCommissionPayment } from '../useUgcCommissionPayment'
import { bookingApi } from '@/features/booking/services/bookingApi'
import { missionApi } from '@/features/mission/services/missionApi'
import { redirectToCheckout } from '@/lib/redirectToCheckout'
import type { BookingResponse } from '@/features/booking/types'
import type { MissionResponse } from '@/features/mission/types'

vi.mock('@/features/booking/services/bookingApi', () => ({
  bookingApi: { payCommission: vi.fn(), checkCommissionStatus: vi.fn() },
}))
vi.mock('@/lib/redirectToCheckout', () => ({ redirectToCheckout: vi.fn() }))
vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: { payCommission: vi.fn(), getCommissionStatus: vi.fn() },
}))

const bookingCheckout = (url: string): BookingResponse & { checkout_url: string } =>
  ({ data: { id: 'b1' }, checkout_url: url }) as unknown as BookingResponse & { checkout_url: string }

const missionCheckout = (url: string): MissionResponse & { checkout_url: string } =>
  ({ data: { id: 'm1' }, checkout_url: url }) as unknown as MissionResponse & { checkout_url: string }

function restoreFromBfcache(persisted: boolean): void {
  const event = new Event('pageshow') as Event & { persisted: boolean }
  Object.defineProperty(event, 'persisted', { value: persisted })
  window.dispatchEvent(event)
}

describe('useUgcCommissionPayment', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('open', vi.fn())
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('initiates a booking commission payment and redirects the same tab to the FedaPay checkout', async () => {
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout('https://fedapay.test/x'))

    const { initiate, paymentStatus } = useUgcCommissionPayment()
    const ok = await initiate('booking', 'b1')

    expect(ok).toBe(true)
    expect(bookingApi.payCommission).toHaveBeenCalledWith('b1')
    expect(redirectToCheckout).toHaveBeenCalledWith('https://fedapay.test/x')
    expect(window.open).not.toHaveBeenCalled()
    expect(paymentStatus.value).toBe('waiting')
  })

  it('initiates a mission commission payment via the mission API', async () => {
    vi.mocked(missionApi.payCommission).mockResolvedValue(missionCheckout('https://fedapay.test/y'))

    const { initiate } = useUgcCommissionPayment()
    await initiate('mission', 'm1')

    expect(missionApi.payCommission).toHaveBeenCalledWith('m1')
    expect(redirectToCheckout).toHaveBeenCalledWith('https://fedapay.test/y')
    expect(window.open).not.toHaveBeenCalled()
  })

  it('does not poll after redirecting (the page is left; usePaymentReturn verifies on return)', async () => {
    vi.useFakeTimers()
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout('u'))

    const { initiate } = useUgcCommissionPayment()
    await initiate('booking', 'b1')
    await vi.advanceTimersByTimeAsync(130000)

    expect(bookingApi.checkCommissionStatus).not.toHaveBeenCalled()
  })

  it('fails when initiation throws and never redirects', async () => {
    vi.mocked(bookingApi.payCommission).mockRejectedValue(new Error('boom'))

    const { initiate, paymentStatus, error } = useUgcCommissionPayment()
    const ok = await initiate('booking', 'b1')

    expect(ok).toBe(false)
    expect(paymentStatus.value).toBe('failed')
    expect(error.value).toBeTruthy()
    expect(redirectToCheckout).not.toHaveBeenCalled()
  })

  it('reset() clears state back to idle', async () => {
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout('u'))

    const { initiate, reset, paymentStatus } = useUgcCommissionPayment()
    await initiate('booking', 'b1')
    expect(paymentStatus.value).toBe('waiting')

    reset()
    expect(paymentStatus.value).toBe('idle')
  })

  it('resets the redirecting state on a bfcache restore (persisted pageshow) but not otherwise', async () => {
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout('u'))
    const { initiate, paymentStatus } = useUgcCommissionPayment()
    await initiate('booking', 'b1')

    restoreFromBfcache(false)
    expect(paymentStatus.value).toBe('waiting')

    restoreFromBfcache(true)
    expect(paymentStatus.value).toBe('idle')
  })
})
