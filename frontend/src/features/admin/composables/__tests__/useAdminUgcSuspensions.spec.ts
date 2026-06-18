import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { AdminUgcSuspension } from '../../services/adminUgcSuspensionsApi'

const mockGetSuspensions = vi.fn()
const mockReactivate = vi.fn()
const mockRejectAppeal = vi.fn()

vi.mock('../../services/adminUgcSuspensionsApi', () => ({
  adminUgcSuspensionsApi: {
    getSuspensions: (...args: unknown[]) => mockGetSuspensions(...args),
    reactivate: (...args: unknown[]) => mockReactivate(...args),
    rejectAppeal: (...args: unknown[]) => mockRejectAppeal(...args),
  },
}))

vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorMessage: vi.fn(
    (err: { response?: { data?: { error?: { message?: string } } } }) =>
      err?.response?.data?.error?.message ?? null,
  ),
  getApiErrorDetails: vi.fn(() => ({})),
}))

import { useAdminUgcSuspensions } from '../useAdminUgcSuspensions'

function makeSuspension(overrides: Partial<AdminUgcSuspension> = {}): AdminUgcSuspension {
  return {
    uuid: 'susp-uuid-1',
    reason: 'avis_deadline_missed',
    reason_label: 'Avis non livré dans les délais',
    suspended_at: '2026-06-18T09:00:00+00:00',
    reactivated_at: null,
    appeal_status: 'pending',
    appeal_status_label: 'En attente',
    face: { id: 1, prenom: 'Aïcha', nom: 'Bello' },
    deal: { owner_kind: 'booking', product_name: 'Tenue Shade Fit' },
    ...overrides,
  }
}

describe('useAdminUgcSuspensions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetchSuspensions_success_populates_state', async () => {
    mockGetSuspensions.mockResolvedValue({
      data: [makeSuspension()],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      message: 'Appels récupérés avec succès',
    })

    const composable = useAdminUgcSuspensions()
    await composable.fetchSuspensions()

    expect(mockGetSuspensions).toHaveBeenCalledWith({ page: 1 })
    expect(composable.suspensions.value).toHaveLength(1)
    expect(composable.suspensions.value[0]).toEqual({
      uuid: 'susp-uuid-1',
      reason: 'avis_deadline_missed',
      reason_label: 'Avis non livré dans les délais',
      suspended_at: '2026-06-18T09:00:00+00:00',
      reactivated_at: null,
      appeal_status: 'pending',
      appeal_status_label: 'En attente',
      face: { id: 1, prenom: 'Aïcha', nom: 'Bello' },
      deal: { owner_kind: 'booking', product_name: 'Tenue Shade Fit' },
    })
    expect(composable.pagination.value?.total).toBe(1)
    expect(composable.isLoading.value).toBe(false)
    expect(composable.error.value).toBeNull()
  })

  it('fetchSuspensions_error_sets_error_message', async () => {
    mockGetSuspensions.mockRejectedValue({
      response: { data: { error: { message: 'Boom' } } },
    })

    const composable = useAdminUgcSuspensions()
    await composable.fetchSuspensions()

    expect(composable.suspensions.value).toEqual([])
    expect(composable.pagination.value).toBeNull()
    expect(composable.error.value).toBe('Boom')
  })

  it('reactivate_success_refetches_list', async () => {
    mockReactivate.mockResolvedValue({
      data: makeSuspension({ appeal_status: 'accepted' }),
      message: 'Compte Face réactivé.',
    })
    mockGetSuspensions.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      message: 'Appels récupérés avec succès',
    })

    const composable = useAdminUgcSuspensions()
    const result = await composable.reactivate('susp-uuid-1')

    expect(result).toBe(true)
    expect(mockReactivate).toHaveBeenCalledWith('susp-uuid-1')
    expect(mockGetSuspensions).toHaveBeenCalledTimes(1)
    expect(composable.actionSuccess.value).toBe('Compte Face réactivé.')
    expect(composable.actionError.value).toBeNull()
  })

  it('reactivate_error_sets_actionError', async () => {
    mockReactivate.mockRejectedValue({
      response: { data: { error: { message: 'Compte déjà réactivé.' } } },
    })
    mockGetSuspensions.mockResolvedValue({
      data: [makeSuspension()],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      message: 'Appels récupérés avec succès',
    })

    const composable = useAdminUgcSuspensions()
    const result = await composable.reactivate('susp-uuid-1')

    expect(result).toBe(false)
    expect(composable.actionError.value).toBe('Compte déjà réactivé.')
    expect(composable.actionSuccess.value).toBeNull()
    // error path still refetches the current page
    expect(mockGetSuspensions).toHaveBeenCalledWith({ page: 1 })
  })

  it('rejectAppeal_success_refetches_list', async () => {
    mockRejectAppeal.mockResolvedValue({
      data: makeSuspension({ appeal_status: 'rejected' }),
      message: 'Appel rejeté — la Face reste suspendue.',
    })
    mockGetSuspensions.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      message: 'Appels récupérés avec succès',
    })

    const composable = useAdminUgcSuspensions()
    const result = await composable.rejectAppeal('susp-uuid-1')

    expect(result).toBe(true)
    expect(mockRejectAppeal).toHaveBeenCalledWith('susp-uuid-1')
    expect(mockGetSuspensions).toHaveBeenCalledTimes(1)
    expect(composable.actionSuccess.value).toBe('Appel rejeté — la Face reste suspendue.')
    expect(composable.actionError.value).toBeNull()
  })

  it('reactivate_on_emptied_last_page_steps_back_one_page', async () => {
    mockReactivate.mockResolvedValue({
      data: makeSuspension({ appeal_status: 'accepted' }),
      message: 'Compte Face réactivé.',
    })
    // 1) initial load of page 2 (one remaining row)
    mockGetSuspensions.mockResolvedValueOnce({
      data: [makeSuspension({ uuid: 'last-row' })],
      meta: { current_page: 2, last_page: 2, per_page: 20, total: 21 },
      message: 'Appels récupérés avec succès',
    })
    // 2) post-action refetch of page 2 → now empty
    mockGetSuspensions.mockResolvedValueOnce({
      data: [],
      meta: { current_page: 2, last_page: 1, per_page: 20, total: 20 },
      message: 'Appels récupérés avec succès',
    })
    // 3) step-back refetch of page 1 → populated
    mockGetSuspensions.mockResolvedValueOnce({
      data: [makeSuspension({ uuid: 'page-1-row' })],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 20 },
      message: 'Appels récupérés avec succès',
    })

    const composable = useAdminUgcSuspensions()
    await composable.fetchSuspensions(2)
    expect(composable.currentPage.value).toBe(2)

    const result = await composable.reactivate('last-row')

    expect(result).toBe(true)
    // refetched the (now empty) current page, then stepped back one page
    expect(mockGetSuspensions).toHaveBeenNthCalledWith(2, { page: 2 })
    expect(mockGetSuspensions).toHaveBeenNthCalledWith(3, { page: 1 })
    expect(composable.currentPage.value).toBe(1)
    expect(composable.suspensions.value).toHaveLength(1)
    expect(composable.suspensions.value[0].uuid).toBe('page-1-row')
  })

  it('reactivate_success_but_refetch_failure_sets_actionError_and_returns_true', async () => {
    mockReactivate.mockResolvedValue({
      data: makeSuspension({ appeal_status: 'accepted' }),
      message: 'Compte Face réactivé.',
    })
    // post-action refetch fails (network) — fetchSuspensions swallows + returns false
    mockGetSuspensions.mockRejectedValueOnce({
      response: { data: { error: { message: 'Réseau indisponible' } } },
    })

    const composable = useAdminUgcSuspensions()
    const result = await composable.reactivate('susp-uuid-1')

    // action itself succeeded → true, success message kept, but list-refresh warning surfaced
    expect(result).toBe(true)
    expect(composable.actionSuccess.value).toBe('Compte Face réactivé.')
    expect(composable.actionError.value).toContain("la liste n'a pas pu être rafraîchie")
    expect(composable.actionError.value).toContain('Réseau indisponible')
  })
})
