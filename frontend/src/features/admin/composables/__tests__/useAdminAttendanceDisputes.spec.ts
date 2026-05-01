import { describe, it, expect, vi, beforeEach } from 'vitest'

const mockGetDisputes = vi.fn()
const mockResolveDispute = vi.fn()

vi.mock('../../services/adminAttendanceDisputesApi', () => ({
  adminAttendanceDisputesApi: {
    getDisputes: (...args: unknown[]) => mockGetDisputes(...args),
    resolveDispute: (...args: unknown[]) => mockResolveDispute(...args),
  },
}))

vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorMessage: vi.fn(
    (err: { response?: { data?: { error?: { message?: string } } } }) =>
      err?.response?.data?.error?.message ?? null,
  ),
  getApiErrorDetails: vi.fn(() => ({})),
}))

import { useAdminAttendanceDisputes } from '../useAdminAttendanceDisputes'

describe('useAdminAttendanceDisputes', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetchDisputes_success_populates_state', async () => {
    mockGetDisputes.mockResolvedValue({
      data: [
        {
          id: 1,
          mission: null,
          face: null,
          montant_face_recoit: 90000,
          attendance_status: 'disputed',
          escrow_status: 'locked',
          notified_at: null,
          disputed_at: null,
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      message: 'Litiges récupérés avec succès',
    })

    const composable = useAdminAttendanceDisputes()
    await composable.fetchDisputes()

    expect(mockGetDisputes).toHaveBeenCalledWith({ page: 1 })
    expect(composable.disputes.value).toHaveLength(1)
    expect(composable.pagination.value?.total).toBe(1)
    expect(composable.isLoading.value).toBe(false)
    expect(composable.error.value).toBeNull()
  })

  it('fetchDisputes_error_sets_error_message', async () => {
    mockGetDisputes.mockRejectedValue({
      response: { data: { error: { message: 'Boom' } } },
    })

    const composable = useAdminAttendanceDisputes()
    await composable.fetchDisputes()

    expect(composable.disputes.value).toEqual([])
    expect(composable.pagination.value).toBeNull()
    expect(composable.error.value).toBe('Boom')
  })

  it('resolveDispute_success_refetches_list', async () => {
    mockResolveDispute.mockResolvedValue({
      data: { id: 42, mission: null, face: null, montant_face_recoit: 90000, attendance_status: 'disputed', escrow_status: 'released', notified_at: null, disputed_at: null },
      message: 'Litige résolu avec succès',
    })
    mockGetDisputes.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      message: 'Litiges récupérés avec succès',
    })

    const composable = useAdminAttendanceDisputes()
    const result = await composable.resolveDispute(42, 'face', 'Note de test')

    expect(result).toBe(true)
    expect(mockResolveDispute).toHaveBeenCalledWith(42, 'face', 'Note de test')
    expect(mockGetDisputes).toHaveBeenCalledTimes(1)
    expect(composable.resolveSuccess.value).toBe('Litige résolu avec succès')
    expect(composable.resolveError.value).toBeNull()
  })

  it('resolveDispute_error_sets_resolveError', async () => {
    mockResolveDispute.mockRejectedValue({
      response: { data: { error: { message: 'Litige introuvable' } } },
    })
    mockGetDisputes.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      message: 'Litiges récupérés avec succès',
    })

    const composable = useAdminAttendanceDisputes()
    const result = await composable.resolveDispute(42, 'face', 'Note de test')

    expect(result).toBe(false)
    expect(composable.resolveError.value).toBe('Litige introuvable')
    expect(composable.resolveSuccess.value).toBeNull()
    expect(mockGetDisputes).toHaveBeenCalledWith({ page: 1 })
  })

  it('resolveDispute_success_with_refetch_error_still_returns_success', async () => {
    mockResolveDispute.mockResolvedValue({
      data: { id: 42, mission: null, face: null, montant_face_recoit: 90000, attendance_status: 'disputed', escrow_status: 'released', notified_at: null, disputed_at: null },
      message: 'Litige résolu avec succès',
    })
    mockGetDisputes.mockRejectedValue({
      response: { data: { error: { message: 'Refetch impossible' } } },
    })

    const composable = useAdminAttendanceDisputes()
    const result = await composable.resolveDispute(42, 'face', 'Note de test')

    expect(result).toBe(true)
    expect(composable.resolveSuccess.value).toBe('Litige résolu avec succès')
    expect(composable.resolveError.value).toContain('Litige résolu')
  })

  it('resolveDispute_detects_refetch_error_even_when_message_repeats', async () => {
    mockResolveDispute.mockResolvedValue({
      data: { id: 42, mission: null, face: null, montant_face_recoit: 90000, attendance_status: 'disputed', escrow_status: 'released', notified_at: null, disputed_at: null },
      message: 'Litige résolu avec succès',
    })
    mockGetDisputes.mockRejectedValue({
      response: { data: { error: { message: 'Erreur réseau' } } },
    })

    const composable = useAdminAttendanceDisputes()
    await composable.fetchDisputes()
    expect(composable.error.value).toBe('Erreur réseau')

    const result = await composable.resolveDispute(42, 'face', 'Note de test')

    expect(result).toBe(true)
    expect(composable.resolveSuccess.value).toBe('Litige résolu avec succès')
    expect(composable.resolveError.value).toContain('Erreur réseau')
  })

  it('fetchDisputes_clears_stale_resolve_success', async () => {
    mockResolveDispute.mockResolvedValue({
      data: { id: 42, mission: null, face: null, montant_face_recoit: 90000, attendance_status: 'disputed', escrow_status: 'released', notified_at: null, disputed_at: null },
      message: 'Litige résolu avec succès',
    })
    mockGetDisputes.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      message: 'Litiges récupérés avec succès',
    })

    const composable = useAdminAttendanceDisputes()
    await composable.resolveDispute(42, 'face', 'Note de test')
    expect(composable.resolveSuccess.value).toBe('Litige résolu avec succès')

    await composable.fetchDisputes()

    expect(composable.resolveSuccess.value).toBeNull()
  })
})
