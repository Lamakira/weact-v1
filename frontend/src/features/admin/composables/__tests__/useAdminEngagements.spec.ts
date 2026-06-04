import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAdminEngagements } from '../useAdminEngagements'
import { adminEngagementsApi } from '../../services/adminEngagementsApi'
import type {
  AdminEngagementData,
  AdminEngagementListResponse,
} from '../../services/adminEngagementsApi'

vi.mock('../../services/adminEngagementsApi', () => ({
  adminEngagementsApi: {
    getEngagements: vi.fn(),
  },
}))

vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorMessage: vi.fn((err: unknown) => (err as { response?: { data?: { message?: unknown } } })?.response?.data?.message ?? null),
}))

function makeEngagement(overrides: Partial<AdminEngagementData> = {}): AdminEngagementData {
  return {
    id: 'booking:uuid-base',
    type: 'booking',
    status: 'paid',
    status_label: 'Payee',
    engaged_since: '2026-06-01T12:00:00Z',
    montant_face_recoit: 90000,
    face: {
      id: 'face-base',
      display_name: 'Awa Traore',
      whatsapp_number: '+229 97 12 34 56',
      has_whatsapp: true,
    },
    producer: { display_name: 'Studio Lumiere' },
    objet: { label: 'Shooting', date: '2026-06-10T08:00:00Z', detail_id: null },
    ...overrides,
  }
}

function deferred<T>(): {
  promise: Promise<T>
  resolve: (value: T) => void
  reject: (reason?: unknown) => void
} {
  let resolve!: (value: T) => void
  let reject!: (reason?: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })

  return { promise, resolve, reject }
}

describe('useAdminEngagements', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches engagements and sets pagination state', async () => {
    vi.mocked(adminEngagementsApi.getEngagements).mockResolvedValue({
      data: [makeEngagement()],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
    })

    const { engagements, pagination, isLoading, error, fetchEngagements } = useAdminEngagements()

    const promise = fetchEngagements({ page: 1, type: 'booking' })
    expect(isLoading.value).toBe(true)

    await promise

    expect(adminEngagementsApi.getEngagements).toHaveBeenCalledWith({ page: 1, type: 'booking' })
    expect(isLoading.value).toBe(false)
    expect(error.value).toBeNull()
    expect(engagements.value).toHaveLength(1)
    expect(pagination.value?.total).toBe(1)
  })

  it('keeps the latest response when overlapping requests resolve out of order', async () => {
    const first = deferred<AdminEngagementListResponse>()
    const second = deferred<AdminEngagementListResponse>()
    vi.mocked(adminEngagementsApi.getEngagements)
      .mockReturnValueOnce(first.promise)
      .mockReturnValueOnce(second.promise)

    const { engagements, pagination, isLoading, fetchEngagements } = useAdminEngagements()

    const firstRequest = fetchEngagements({ page: 1, search: 'old' })
    const secondRequest = fetchEngagements({ page: 1, search: 'new' })

    second.resolve({
      data: [makeEngagement({ id: 'booking:new' })],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
    })
    await secondRequest

    expect(isLoading.value).toBe(false)
    expect(engagements.value[0].id).toBe('booking:new')
    expect(pagination.value?.total).toBe(1)

    first.resolve({
      data: [makeEngagement({ id: 'booking:old' })],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
    })
    await firstRequest

    expect(engagements.value[0].id).toBe('booking:new')
    expect(pagination.value?.total).toBe(1)
  })
})
