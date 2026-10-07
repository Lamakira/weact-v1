import { describe, it, expect, vi, beforeEach } from 'vitest'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockGetCsrfCookie = vi.fn()

vi.mock('../../services/adminApiClient', () => ({
  default: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
  },
  getCsrfCookie: (...args: unknown[]) => mockGetCsrfCookie(...args),
}))

vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorMessage: vi.fn(
    (err: { response?: { data?: { error?: { message?: string } } } }) =>
      err?.response?.data?.error?.message ?? null,
  ),
  getApiErrorDetails: vi.fn(() => ({})),
}))

import { useAdminBookingDisputes } from '../useAdminBookingDisputes'

const payload = {
  data: {
    disputes: [{ id: 'b-1', status: 'no_show' }],
    stale_paid: [{ id: 's-1', is_legacy: true, auto_complete_due_at: null }],
  },
  message: 'Litiges récupérés avec succès',
}

describe('useAdminBookingDisputes', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGetCsrfCookie.mockResolvedValue(undefined)
  })

  it('fetchDisputes hits the booking-disputes endpoint and fills both lists', async () => {
    mockGet.mockResolvedValue({ data: payload })

    const composable = useAdminBookingDisputes()
    await composable.fetchDisputes()

    expect(mockGet).toHaveBeenCalledWith('/admin/booking-disputes')
    expect(composable.disputes.value).toHaveLength(1)
    expect(composable.stalePaid.value).toHaveLength(1)
    expect(composable.isLoading.value).toBe(false)
    expect(composable.error.value).toBeNull()
  })

  it('fetchDisputes failure empties the lists and exposes the error', async () => {
    mockGet.mockRejectedValue({ response: { data: { error: { message: 'Boom' } } } })

    const composable = useAdminBookingDisputes()
    const ok = await composable.fetchDisputes()

    expect(ok).toBe(false)
    expect(composable.disputes.value).toEqual([])
    expect(composable.stalePaid.value).toEqual([])
    expect(composable.error.value).toBe('Boom')
  })

  it('resolveDispute posts outcome and notes on the uuid route, then reloads the lists', async () => {
    mockPost.mockResolvedValue({ data: { data: null, message: 'Litige résolu avec succès' } })
    mockGet.mockResolvedValue({ data: { data: { disputes: [], stale_paid: [] }, message: 'ok' } })

    const composable = useAdminBookingDisputes()
    const ok = await composable.resolveDispute('booking-uuid-7', 'favor_producer', 'Absence avérée')

    expect(ok).toBe(true)
    expect(mockGetCsrfCookie).toHaveBeenCalled()
    expect(mockPost).toHaveBeenCalledWith('/admin/booking-disputes/booking-uuid-7/resolve', {
      outcome: 'favor_producer',
      notes: 'Absence avérée',
    })
    expect(mockGet).toHaveBeenCalledTimes(1)
    expect(composable.resolveSuccess.value).toBe('Litige résolu avec succès')
    expect(composable.resolveError.value).toBeNull()
  })

  it('resolveDispute failure sets resolveError, returns false and still reloads', async () => {
    mockPost.mockRejectedValue({ response: { data: { error: { message: 'Litige introuvable' } } } })
    mockGet.mockResolvedValue({ data: payload })

    const composable = useAdminBookingDisputes()
    const ok = await composable.resolveDispute('booking-uuid-7', 'favor_face', 'Note de test')

    expect(ok).toBe(false)
    expect(composable.resolveError.value).toBe('Litige introuvable')
    expect(composable.resolveSuccess.value).toBeNull()
    expect(mockGet).toHaveBeenCalledTimes(1)
  })

  it('resolveDispute success with a failing reload still succeeds but warns', async () => {
    mockPost.mockResolvedValue({ data: { data: null, message: 'Litige résolu avec succès' } })
    mockGet.mockRejectedValue({ response: { data: { error: { message: 'Refetch impossible' } } } })

    const composable = useAdminBookingDisputes()
    const ok = await composable.resolveDispute('booking-uuid-7', 'favor_face', 'Note de test')

    expect(ok).toBe(true)
    expect(composable.resolveError.value).toContain('Litige résolu')
  })
})
