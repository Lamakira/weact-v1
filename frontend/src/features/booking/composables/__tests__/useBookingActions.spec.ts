import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useBookingActions } from '../useBookingActions'
import { bookingApi } from '../../services/bookingApi'
import { captureAnalyticsEvent } from '@/lib/analytics'

vi.mock('../../services/bookingApi', () => ({
  bookingApi: { acceptBooking: vi.fn() },
}))
vi.mock('@/lib/analytics', () => ({ captureAnalyticsEvent: vi.fn() }))
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn(() => 'Erreur'),
  getApiErrorDetails: vi.fn(() => undefined),
}))

describe('useBookingActions.accept — mesure d\'usage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('envoie booking_acceptee cash pour un booking non UGC', async () => {
    vi.mocked(bookingApi.acceptBooking).mockResolvedValue({ data: { id: 'b1', type_contenu: 'Publicité' } } as never)

    const booking = await useBookingActions().accept('b1')

    expect(booking).toEqual({ id: 'b1', type_contenu: 'Publicité' })
    expect(captureAnalyticsEvent).toHaveBeenCalledTimes(1)
    expect(captureAnalyticsEvent).toHaveBeenCalledWith('booking_acceptee', { type: 'cash' })
  })

  it('envoie booking_acceptee ugc pour un booking UGC', async () => {
    vi.mocked(bookingApi.acceptBooking).mockResolvedValue({ data: { id: 'b2', type_contenu: 'UGC' } } as never)

    await useBookingActions().accept('b2')

    expect(captureAnalyticsEvent).toHaveBeenCalledWith('booking_acceptee', { type: 'ugc' })
  })

  it('n\'envoie rien quand l\'acceptation échoue', async () => {
    vi.mocked(bookingApi.acceptBooking).mockRejectedValue(new Error('boom'))

    const booking = await useBookingActions().accept('b3')

    expect(booking).toBeNull()
    expect(captureAnalyticsEvent).not.toHaveBeenCalled()
  })
})
