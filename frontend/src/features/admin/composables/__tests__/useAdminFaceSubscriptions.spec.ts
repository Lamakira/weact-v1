import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const mockIndex = vi.fn()
const mockList = vi.fn()
const mockStats = vi.fn()
const mockActivate = vi.fn()
const mockExtend = vi.fn()
const mockCancel = vi.fn()
const mockCorrect = vi.fn()
const mockChangeTier = vi.fn()

vi.mock('../../services/adminFaceSubscriptionsApi', () => ({
  adminFaceSubscriptionsApi: {
    index: (...args: unknown[]) => mockIndex(...args),
    list: (...args: unknown[]) => mockList(...args),
    stats: (...args: unknown[]) => mockStats(...args),
    activate: (...args: unknown[]) => mockActivate(...args),
    extend: (...args: unknown[]) => mockExtend(...args),
    cancel: (...args: unknown[]) => mockCancel(...args),
    correct: (...args: unknown[]) => mockCorrect(...args),
    changeTier: (...args: unknown[]) => mockChangeTier(...args),
  },
}))

vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorMessage: vi.fn(
    (err: { response?: { data?: { error?: { message?: string } } } } | undefined) => {
      return err?.response?.data?.error?.message ?? 'Une erreur est survenue'
    },
  ),
  getApiErrorDetails: vi.fn(
    (
      err:
        | { response?: { data?: { error?: { details?: Record<string, string[]> } } } }
        | undefined,
    ) => {
      return err?.response?.data?.error?.details ?? {}
    },
  ),
}))

vi.mock('@/services/errorFormatter', () => ({
  getApiErrorCode: vi.fn(
    (err: { response?: { data?: { error?: { code?: string } } } } | undefined) => {
      return err?.response?.data?.error?.code ?? null
    },
  ),
}))

import { useAdminFaceSubscriptions, useAdminSubscriptionsList } from '../useAdminFaceSubscriptions'

const faceId = 'face-uuid-1'
const subscriptionId = 'sub-uuid-1'

function expectRequestOptions(value: unknown): void {
  expect(value).toEqual(
    expect.objectContaining({
      signal: expect.any(Object),
    }),
  )
}

function makeSubscription(overrides: Record<string, unknown> = {}) {
  return {
    id: 'sub-uuid-1',
    plan: 'pro',
    plan_label: 'Pro',
    status: 'active',
    status_label: 'Active',
    starts_at: '2026-01-01T00:00:00+00:00',
    expires_at: '2027-01-01T00:00:00+00:00',
    cancelled_at: null,
    paid_amount: 50000,
    currency: 'XOF',
    created_at: '2026-01-01T00:00:00+00:00',
    updated_at: '2026-01-01T00:00:00+00:00',
    audits: [],
    ...overrides,
  }
}

describe('useAdminFaceSubscriptions - fetchSubscriptions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('populates subscriptions and faceDisplay from the API', async () => {
    mockIndex.mockResolvedValue({
      data: {
        face: { id: faceId, display_name: 'Jane Doe' },
        subscriptions: [makeSubscription(), makeSubscription({ id: 'sub-uuid-2' })],
      },
    })

    const { subscriptions, faceDisplay, fetchSubscriptions } = useAdminFaceSubscriptions()
    await fetchSubscriptions(faceId)

    expect(mockIndex).toHaveBeenCalledWith(faceId, expect.any(Object))
    expectRequestOptions(mockIndex.mock.calls[0][1])
    expect(subscriptions.value).toHaveLength(2)
    expect(subscriptions.value[0].id).toBe('sub-uuid-1')
    expect(faceDisplay.value).toEqual({ id: faceId, display_name: 'Jane Doe' })
  })

  it('sets isLoading during the call', async () => {
    let resolvePromise: (value: unknown) => void
    mockIndex.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    const { isLoading, fetchSubscriptions } = useAdminFaceSubscriptions()
    const pending = fetchSubscriptions(faceId)
    expect(isLoading.value).toBe(true)

    resolvePromise!({
      data: { face: { id: faceId, display_name: 'X' }, subscriptions: [] },
    })
    await pending
    expect(isLoading.value).toBe(false)
  })

  it('handles error without clearing existing subscriptions', async () => {
    mockIndex.mockRejectedValue({
      response: { data: { error: { message: 'Ressource introuvable.', code: 'NOT_FOUND' } } },
    })

    const { subscriptions, faceDisplay, error, fetchSubscriptions } = useAdminFaceSubscriptions()
    subscriptions.value = [makeSubscription({ id: 'sub-existing' })]
    faceDisplay.value = { id: faceId, display_name: 'Jane Doe' }

    await fetchSubscriptions(faceId)

    expect(error.value).toBe('Ressource introuvable.')
    expect(subscriptions.value).toHaveLength(1)
    expect(subscriptions.value[0].id).toBe('sub-existing')
    expect(faceDisplay.value).toEqual({ id: faceId, display_name: 'Jane Doe' })
  })

  it('rethrows 401 errors so the admin interceptor owns session expiry', async () => {
    const unauthorizedError = {
      response: { status: 401, data: { error: { message: 'Unauthenticated.' } } },
    }
    mockIndex.mockRejectedValue(unauthorizedError)

    const { fetchSubscriptions } = useAdminFaceSubscriptions()

    await expect(fetchSubscriptions(faceId)).rejects.toBe(unauthorizedError)
  })

  it('ignores stale fetch responses when a newer request resolves first', async () => {
    let resolveFirst: (value: unknown) => void
    let resolveSecond: (value: unknown) => void
    mockIndex
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveFirst = resolve
        }),
      )
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveSecond = resolve
        }),
      )

    const { subscriptions, faceDisplay, isLoading, fetchSubscriptions } = useAdminFaceSubscriptions()
    const firstRequest = fetchSubscriptions('face-old')
    const secondRequest = fetchSubscriptions('face-new')

    resolveSecond!({
      data: {
        face: { id: 'face-new', display_name: 'New Face' },
        subscriptions: [makeSubscription({ id: 'sub-new' })],
      },
    })
    await secondRequest

    resolveFirst!({
      data: {
        face: { id: 'face-old', display_name: 'Old Face' },
        subscriptions: [makeSubscription({ id: 'sub-old' })],
      },
    })
    await firstRequest

    expect(faceDisplay.value).toEqual({ id: 'face-new', display_name: 'New Face' })
    expect(subscriptions.value).toHaveLength(1)
    expect(subscriptions.value[0].id).toBe('sub-new')
    expect(isLoading.value).toBe(false)
  })

  it('aborts in-flight requests and clears loading state', async () => {
    let rejectRequest: (reason: unknown) => void
    mockIndex.mockImplementation(
      (_faceId: string, options: { signal: AbortSignal }) =>
        new Promise((_resolve, reject) => {
          rejectRequest = reject
          options.signal.addEventListener('abort', () => {
            reject({ name: 'CanceledError', code: 'ERR_CANCELED' })
          })
        }),
    )

    const { abortRequests, fetchSubscriptions, isLoading } = useAdminFaceSubscriptions()
    const pending = fetchSubscriptions(faceId)

    expect(isLoading.value).toBe(true)
    abortRequests()

    await expect(pending).rejects.toMatchObject({ code: 'ERR_CANCELED' })
    expect(isLoading.value).toBe(false)
    expect(rejectRequest!).toBeDefined()
  })
})

describe('useAdminFaceSubscriptions - activate', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('returns success: true with message on 201', async () => {
    mockActivate.mockResolvedValue({
      data: makeSubscription(),
      message: 'Abonnement activé manuellement',
    })

    const { activate } = useAdminFaceSubscriptions()
    const result = await activate(faceId, { plan: 'pro', notes: 'Manual activation' })

    expect(mockActivate).toHaveBeenCalledWith(
      faceId,
      { plan: 'pro', notes: 'Manual activation' },
      expect.any(Object),
    )
    expectRequestOptions(mockActivate.mock.calls[0][2])
    expect(result.success).toBe(true)
    expect(result.message).toBe('Abonnement activé manuellement')
  })

  it('returns success: false with errors map on 422', async () => {
    mockActivate.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'VALIDATION_ERROR',
            message: 'Les données fournies ne sont pas valides',
            details: { notes: ['Le champ notes doit contenir au moins 5 caractères.'] },
          },
        },
      },
    })

    const { activate } = useAdminFaceSubscriptions()
    const result = await activate(faceId, { plan: 'pro', notes: 'abc' })

    expect(result.success).toBe(false)
    expect(result.code).toBe('VALIDATION_ERROR')
    expect(result.errors?.notes?.[0]).toBe('Le champ notes doit contenir au moins 5 caractères.')
    expect(result.message).toBe('Les données fournies ne sont pas valides')
  })

  it('returns success: false with code: ALREADY_ACTIVE on 409', async () => {
    mockActivate.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'ALREADY_ACTIVE',
            message:
              'Cette Face a déjà un abonnement actif. Utilisez « Étendre » pour prolonger la période en cours.',
          },
        },
      },
    })

    const { activate } = useAdminFaceSubscriptions()
    const result = await activate(faceId, { plan: 'pro', notes: 'duplicate activation attempt' })

    expect(result.success).toBe(false)
    expect(result.code).toBe('ALREADY_ACTIVE')
    expect(result.message).toContain('déjà un abonnement actif')
  })

  it('returns success: false with code: PENDING_PAYMENT_EXISTS on 409', async () => {
    mockActivate.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'PENDING_PAYMENT_EXISTS',
            message:
              'Un paiement est en attente pour cette Face. Annulez-le avant d\'activer manuellement.',
          },
        },
      },
    })

    const { activate } = useAdminFaceSubscriptions()
    const result = await activate(faceId, { plan: 'pro', notes: 'manual activation while payment pending' })

    expect(result.success).toBe(false)
    expect(result.code).toBe('PENDING_PAYMENT_EXISTS')
    expect(result.message).toContain('paiement est en attente')
  })
})

describe('useAdminFaceSubscriptions - extend', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('returns success: true with message on 200', async () => {
    mockExtend.mockResolvedValue({
      data: makeSubscription(),
      message: 'Abonnement étendu',
    })

    const { extend } = useAdminFaceSubscriptions()
    const result = await extend(subscriptionId, {
      notes: 'Extension demandée par support',
      additional_days: 30,
    })

    expect(mockExtend).toHaveBeenCalledWith(
      subscriptionId,
      {
        notes: 'Extension demandée par support',
        additional_days: 30,
      },
      expect.any(Object),
    )
    expectRequestOptions(mockExtend.mock.calls[0][2])
    expect(result.success).toBe(true)
    expect(result.message).toBe('Abonnement étendu')
  })

  it('returns success: false with code: NOT_EXTENDABLE on 409', async () => {
    mockExtend.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'NOT_EXTENDABLE',
            message:
              'Seul un abonnement actif et non expiré peut être prolongé. Utilisez « Activer » pour redémarrer un abonnement terminé.',
          },
        },
      },
    })

    const { extend } = useAdminFaceSubscriptions()
    const result = await extend(subscriptionId, {
      notes: 'Late extension',
      additional_days: 60,
    })

    expect(result.success).toBe(false)
    expect(result.code).toBe('NOT_EXTENDABLE')
    expect(result.message).toContain('Seul un abonnement actif et non expiré peut être prolongé')
  })
})

describe('useAdminFaceSubscriptions - cancel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('returns success: true with message on 200', async () => {
    mockCancel.mockResolvedValue({
      data: makeSubscription({ status: 'cancelled', cancelled_at: '2026-05-20T10:00:00+00:00' }),
      message: 'Abonnement annulé',
    })

    const { cancel } = useAdminFaceSubscriptions()
    const result = await cancel(subscriptionId, {
      notes: 'Annulation à la demande du client.',
    })

    expect(mockCancel).toHaveBeenCalledWith(
      subscriptionId,
      {
        notes: 'Annulation à la demande du client.',
      },
      expect.any(Object),
    )
    expectRequestOptions(mockCancel.mock.calls[0][2])
    expect(result.success).toBe(true)
    expect(result.message).toBe('Abonnement annulé')
  })

  it('returns success: false with code: NOT_CANCELLABLE on 409', async () => {
    mockCancel.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'NOT_CANCELLABLE',
            message: 'Cet abonnement ne peut pas être annulé : il est déjà dans un état terminal.',
          },
        },
      },
    })

    const { cancel } = useAdminFaceSubscriptions()
    const result = await cancel(subscriptionId, { notes: 'Late cancellation attempt' })

    expect(result.success).toBe(false)
    expect(result.code).toBe('NOT_CANCELLABLE')
    expect(result.message).toContain('état terminal')
  })
})

describe('useAdminFaceSubscriptions - correct', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('returns success: true with message on 200', async () => {
    mockCorrect.mockResolvedValue({
      data: makeSubscription({
        starts_at: '2026-02-01T00:00:00+00:00',
        expires_at: '2027-02-01T00:00:00+00:00',
      }),
      message: 'Dates corrigées',
    })

    const { correct } = useAdminFaceSubscriptions()
    const result = await correct(subscriptionId, {
      notes: 'Correction des dates suite à une erreur de saisie.',
      starts_at: '2026-02-01',
      expires_at: '2027-02-01',
    })

    expect(mockCorrect).toHaveBeenCalledWith(
      subscriptionId,
      {
        notes: 'Correction des dates suite à une erreur de saisie.',
        starts_at: '2026-02-01',
        expires_at: '2027-02-01',
      },
      expect.any(Object),
    )
    expectRequestOptions(mockCorrect.mock.calls[0][2])
    expect(result.success).toBe(true)
    expect(result.message).toBe('Dates corrigées')
  })

  it('returns success: false with errors map on 422 including cross-field error on expires_at', async () => {
    mockCorrect.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'VALIDATION_ERROR',
            message: 'Les données fournies ne sont pas valides',
            details: {
              expires_at: ['expires_at doit être postérieure à starts_at.'],
            },
          },
        },
      },
    })

    const { correct } = useAdminFaceSubscriptions()
    const result = await correct(subscriptionId, {
      notes: 'Tentative invalide',
      starts_at: '2027-01-01',
      expires_at: '2026-01-01',
    })

    expect(result.success).toBe(false)
    expect(result.code).toBe('VALIDATION_ERROR')
    expect(result.errors?.expires_at?.[0]).toBe('expires_at doit être postérieure à starts_at.')
  })
})

describe('useAdminFaceSubscriptions - changeTier', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('returns success: true with message on 200', async () => {
    mockChangeTier.mockResolvedValue({
      data: makeSubscription({ plan: 'elite', plan_label: 'Élite' }),
      message: 'Palier modifié',
    })

    const { changeTier } = useAdminFaceSubscriptions()
    const result = await changeTier(subscriptionId, {
      notes: 'Test notes pour changement',
      new_plan: 'elite',
    })

    expect(mockChangeTier).toHaveBeenCalledWith(
      subscriptionId,
      { notes: 'Test notes pour changement', new_plan: 'elite' },
      expect.any(Object),
    )
    expectRequestOptions(mockChangeTier.mock.calls[0][2])
    expect(result.success).toBe(true)
    expect(result.message).toBe('Palier modifié')
  })

  it('returns success: false with errors map on 422', async () => {
    mockChangeTier.mockRejectedValue({
      response: {
        status: 422,
        data: {
          error: {
            code: 'VALIDATION_ERROR',
            message: 'Les données fournies ne sont pas valides',
            details: { new_plan: ['Le champ palier sélectionné est invalide.'] },
          },
        },
      },
    })

    const { changeTier } = useAdminFaceSubscriptions()
    const result = await changeTier(subscriptionId, {
      notes: 'Test notes',
      new_plan: 'elite',
    })

    expect(result.success).toBe(false)
    expect(result.code).toBe('VALIDATION_ERROR')
    expect(result.errors?.new_plan?.[0]).toBe('Le champ palier sélectionné est invalide.')
    expect(result.message).toBe('Les données fournies ne sont pas valides')
  })

  it('returns success: false with code: NOT_TIER_CHANGEABLE on 409', async () => {
    mockChangeTier.mockRejectedValue({
      response: {
        status: 409,
        data: {
          error: {
            code: 'NOT_TIER_CHANGEABLE',
            message:
              'Seul un abonnement actif et non expiré peut changer de palier. Utilisez « Activer » pour redémarrer un abonnement terminé.',
          },
        },
      },
    })

    const { changeTier } = useAdminFaceSubscriptions()
    const result = await changeTier(subscriptionId, {
      notes: 'Test notes',
      new_plan: 'elite',
    })

    expect(result.success).toBe(false)
    expect(result.code).toBe('NOT_TIER_CHANGEABLE')
    expect(result.message).toContain('peut changer de palier')
  })

  it('returns success: false with code: SAME_TIER on 409', async () => {
    mockChangeTier.mockRejectedValue({
      response: {
        status: 409,
        data: {
          error: {
            code: 'SAME_TIER',
            message: 'Cet abonnement est déjà sur ce palier. Choisissez un palier différent.',
          },
        },
      },
    })

    const { changeTier } = useAdminFaceSubscriptions()
    const result = await changeTier(subscriptionId, {
      notes: 'Test notes',
      new_plan: 'pro',
    })

    expect(result.success).toBe(false)
    expect(result.code).toBe('SAME_TIER')
    expect(result.message).toContain('déjà sur ce palier')
  })

  it('rethrows abort errors so the caller can ignore them silently', async () => {
    mockChangeTier.mockRejectedValue({ name: 'CanceledError' })

    const { changeTier } = useAdminFaceSubscriptions()
    await expect(
      changeTier(subscriptionId, { notes: 'x', new_plan: 'elite' }),
    ).rejects.toMatchObject({ name: 'CanceledError' })
  })
})

describe('useAdminSubscriptionsList - fetchStats latest-wins', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  function makeStats(total: number) {
    return {
      active_by_plan: { starter: 2, pro: 5, elite: 1, total },
      revenue: { current_month: 150000, total: 1200000, currency: 'XOF' },
      expiring_within_30_days: 3,
      pending_payment_count: 4,
      failed_count: 2,
    }
  }

  it('ignores a stale stats response that resolves after a newer one', async () => {
    let resolveFirst: (value: unknown) => void
    let resolveSecond: (value: unknown) => void
    mockStats
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveFirst = resolve
        }),
      )
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveSecond = resolve
        }),
      )

    const { stats, statsError, isStatsLoading, fetchStats } = useAdminSubscriptionsList()
    const firstRequest = fetchStats()
    const secondRequest = fetchStats()

    resolveSecond!({ data: makeStats(42), message: 'OK' })
    await secondRequest

    resolveFirst!({ data: makeStats(8), message: 'OK' })
    await firstRequest

    expect(stats.value?.active_by_plan.total).toBe(42)
    expect(statsError.value).toBeNull()
    expect(isStatsLoading.value).toBe(false)
  })

  it('does not let a late-failing stale stats request null out fresh stats', async () => {
    let rejectFirst: (reason: unknown) => void
    let resolveSecond: (value: unknown) => void
    mockStats
      .mockReturnValueOnce(
        new Promise((_resolve, reject) => {
          rejectFirst = reject
        }),
      )
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveSecond = resolve
        }),
      )

    const { stats, statsError, isStatsLoading, fetchStats } = useAdminSubscriptionsList()
    const firstRequest = fetchStats()
    const secondRequest = fetchStats()

    resolveSecond!({ data: makeStats(42), message: 'OK' })
    await secondRequest

    rejectFirst!({ response: { data: { error: { message: 'Trop de requêtes.' } } } })
    await firstRequest

    expect(stats.value?.active_by_plan.total).toBe(42)
    expect(statsError.value).toBeNull()
    expect(isStatsLoading.value).toBe(false)
  })
})

describe('useAdminSubscriptionsList - fetchSubscriptions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  function makeMeta(overrides: Record<string, unknown> = {}) {
    return { current_page: 1, last_page: 3, per_page: 15, total: 31, ...overrides }
  }

  it('mappe response.data dans subscriptions et response.meta dans pagination', async () => {
    mockList.mockResolvedValue({
      data: [makeSubscription({ id: 'sub-list-1' })],
      meta: makeMeta({ current_page: 2, last_page: 4, total: 50 }),
    })

    const { subscriptions, pagination, error, isLoading, fetchSubscriptions } =
      useAdminSubscriptionsList()
    await fetchSubscriptions({ page: 2 })

    expect(mockList).toHaveBeenCalledWith({ page: 2 })
    expect(subscriptions.value).toHaveLength(1)
    expect(subscriptions.value[0].id).toBe('sub-list-1')
    expect(pagination.value).toEqual({
      current_page: 2,
      last_page: 4,
      per_page: 15,
      total: 50,
    })
    expect(error.value).toBeNull()
    expect(isLoading.value).toBe(false)
  })

  it('en erreur : message posé, subscriptions vidées et pagination remise à null', async () => {
    mockList
      .mockResolvedValueOnce({
        data: [makeSubscription({ id: 'sub-seed' })],
        meta: makeMeta(),
      })
      .mockRejectedValueOnce({
        response: { data: { error: { message: 'Accès refusé.' } } },
      })

    const { subscriptions, pagination, error, isLoading, fetchSubscriptions } =
      useAdminSubscriptionsList()

    await fetchSubscriptions()
    expect(subscriptions.value).toHaveLength(1)
    expect(pagination.value).not.toBeNull()

    await fetchSubscriptions()

    expect(error.value).toBe('Accès refusé.')
    expect(subscriptions.value).toEqual([])
    expect(pagination.value).toBeNull()
    expect(isLoading.value).toBe(false)
  })

  it('latest-wins : une réponse périmée résolue pendant que la requête plus récente est en vol n\'écrit rien et ne coupe pas isLoading', async () => {
    let resolveFirst: (value: unknown) => void
    let resolveSecond: (value: unknown) => void
    mockList
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveFirst = resolve
        }),
      )
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveSecond = resolve
        }),
      )

    const { subscriptions, pagination, isLoading, fetchSubscriptions } =
      useAdminSubscriptionsList()
    const firstRequest = fetchSubscriptions({ page: 1 })
    const secondRequest = fetchSubscriptions({ page: 2 })

    resolveFirst!({
      data: [makeSubscription({ id: 'sub-stale' })],
      meta: makeMeta({ current_page: 1 }),
    })
    await firstRequest

    // La réponse périmée ne doit ni écrire les données ni couper le chargement
    // pendant que la requête la plus récente est encore en vol.
    expect(subscriptions.value).toEqual([])
    expect(pagination.value).toBeNull()
    expect(isLoading.value).toBe(true)

    resolveSecond!({
      data: [makeSubscription({ id: 'sub-fresh' })],
      meta: makeMeta({ current_page: 2 }),
    })
    await secondRequest

    expect(subscriptions.value).toHaveLength(1)
    expect(subscriptions.value[0].id).toBe('sub-fresh')
    expect(pagination.value?.current_page).toBe(2)
    expect(isLoading.value).toBe(false)
  })

  it('latest-wins : une réponse périmée résolue après la plus récente n\'écrase pas les données fraîches', async () => {
    let resolveFirst: (value: unknown) => void
    let resolveSecond: (value: unknown) => void
    mockList
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveFirst = resolve
        }),
      )
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveSecond = resolve
        }),
      )

    const { subscriptions, pagination, error, isLoading, fetchSubscriptions } =
      useAdminSubscriptionsList()
    const firstRequest = fetchSubscriptions({ page: 1 })
    const secondRequest = fetchSubscriptions({ page: 2 })

    resolveSecond!({
      data: [makeSubscription({ id: 'sub-fresh' })],
      meta: makeMeta({ current_page: 2 }),
    })
    await secondRequest

    resolveFirst!({
      data: [makeSubscription({ id: 'sub-stale' })],
      meta: makeMeta({ current_page: 1 }),
    })
    await firstRequest

    expect(subscriptions.value).toHaveLength(1)
    expect(subscriptions.value[0].id).toBe('sub-fresh')
    expect(pagination.value?.current_page).toBe(2)
    expect(error.value).toBeNull()
    expect(isLoading.value).toBe(false)
  })
})
