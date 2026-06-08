import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useUgcCommissionPayment } from '../useUgcCommissionPayment'
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

const bookingCheckout = (url: string): BookingResponse & { checkout_url: string } =>
  ({ data: { id: 'b1' }, checkout_url: url }) as unknown as BookingResponse & { checkout_url: string }

const missionCheckout = (url: string): MissionResponse & { checkout_url: string } =>
  ({ data: { id: 'm1' }, checkout_url: url }) as unknown as MissionResponse & { checkout_url: string }

const bookingStatus = (status: string): BookingResponse =>
  ({ data: { status } }) as unknown as BookingResponse

const missionStatus = (status: string): MissionResponse =>
  ({ data: { status } }) as unknown as MissionResponse

describe('useUgcCommissionPayment', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('open', vi.fn())
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('initiates a booking commission payment and opens the FedaPay checkout in a new tab', async () => {
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout('https://fedapay.test/x'))

    const { initiate, paymentStatus } = useUgcCommissionPayment()
    const ok = await initiate('booking', 'b1')

    expect(ok).toBe(true)
    expect(bookingApi.payCommission).toHaveBeenCalledWith('b1')
    expect(window.open).toHaveBeenCalledWith(
      'https://fedapay.test/x',
      '_blank',
      'noopener,noreferrer',
    )
    expect(paymentStatus.value).toBe('waiting')
  })

  it('initiates a mission commission payment via the mission API', async () => {
    vi.mocked(missionApi.payCommission).mockResolvedValue(missionCheckout('https://fedapay.test/y'))

    const { initiate } = useUgcCommissionPayment()
    await initiate('mission', 'm1')

    expect(missionApi.payCommission).toHaveBeenCalledWith('m1')
    expect(window.open).toHaveBeenCalledWith(
      'https://fedapay.test/y',
      '_blank',
      'noopener,noreferrer',
    )
  })

  it('confirms when the booking polling sees commission_paid', async () => {
    vi.useFakeTimers()
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout('u'))
    vi.mocked(bookingApi.checkCommissionStatus).mockResolvedValue(bookingStatus('commission_paid'))

    const { initiate, paymentStatus } = useUgcCommissionPayment()
    await initiate('booking', 'b1')
    await vi.advanceTimersByTimeAsync(5000)

    expect(paymentStatus.value).toBe('confirmed')
  })

  it('confirms when the mission polling sees published', async () => {
    vi.useFakeTimers()
    vi.mocked(missionApi.payCommission).mockResolvedValue(missionCheckout('u'))
    vi.mocked(missionApi.getCommissionStatus).mockResolvedValue(missionStatus('published'))

    const { initiate, paymentStatus } = useUgcCommissionPayment()
    await initiate('mission', 'm1')
    await vi.advanceTimersByTimeAsync(5000)

    expect(paymentStatus.value).toBe('confirmed')
  })

  it('keeps waiting while the booking is not settled (still pending)', async () => {
    vi.useFakeTimers()
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout('u'))
    vi.mocked(bookingApi.checkCommissionStatus).mockResolvedValue(bookingStatus('pending'))

    const { initiate, paymentStatus } = useUgcCommissionPayment()
    await initiate('booking', 'b1')
    await vi.advanceTimersByTimeAsync(5000)

    expect(paymentStatus.value).toBe('waiting')
  })

  it('does not detect a booking settlement on the cash "paid" status', async () => {
    vi.useFakeTimers()
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout('u'))
    vi.mocked(bookingApi.checkCommissionStatus).mockResolvedValue(bookingStatus('paid'))

    const { initiate, paymentStatus } = useUgcCommissionPayment()
    await initiate('booking', 'b1')
    await vi.advanceTimersByTimeAsync(5000)

    expect(paymentStatus.value).toBe('waiting')
  })

  it('fails when initiation throws and never opens a tab', async () => {
    vi.mocked(bookingApi.payCommission).mockRejectedValue(new Error('boom'))

    const { initiate, paymentStatus, error } = useUgcCommissionPayment()
    const ok = await initiate('booking', 'b1')

    expect(ok).toBe(false)
    expect(paymentStatus.value).toBe('failed')
    expect(error.value).toBeTruthy()
    expect(window.open).not.toHaveBeenCalled()
  })

  it('fails with the expiry message when polling never settles within the timeout window', async () => {
    vi.useFakeTimers()
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout('u'))
    vi.mocked(bookingApi.checkCommissionStatus).mockResolvedValue(bookingStatus('pending'))

    const { initiate, paymentStatus, error, isPolling } = useUgcCommissionPayment()
    await initiate('booking', 'b1')
    expect(paymentStatus.value).toBe('waiting')

    // POLL_TIMEOUT_MS = 120000 — advance past it; polling keeps seeing 'pending' until the timeout fires.
    await vi.advanceTimersByTimeAsync(120000)

    expect(paymentStatus.value).toBe('failed')
    expect(error.value).toContain('délai')
    expect(isPolling.value).toBe(false)
  })

  it('reset() clears state back to idle', async () => {
    vi.mocked(bookingApi.payCommission).mockResolvedValue(bookingCheckout('u'))

    const { initiate, reset, paymentStatus } = useUgcCommissionPayment()
    await initiate('booking', 'b1')
    expect(paymentStatus.value).toBe('waiting')

    reset()
    expect(paymentStatus.value).toBe('idle')
  })
})
