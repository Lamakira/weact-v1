import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useUgcSuspension } from '../useUgcSuspension'
import { faceApi } from '@/features/face/services/faceApi'
import type { UgcSuspensionStatusResponse } from '@/components/ugc'

// Mock the face API
vi.mock('@/features/face/services/faceApi', () => ({
  faceApi: {
    getUgcSuspensionStatus: vi.fn(),
  },
}))

// Mock getApiErrorMessage
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn((err) => (err as Error)?.message || 'Unknown error'),
}))

const suspendedResponse: UgcSuspensionStatusResponse = {
  data: {
    is_suspended: true,
    suspension: {
      reason: 'avis_deadline_missed',
      reason_label: 'Avis non livré dans les délais',
      suspended_at: '2026-06-18T09:00:00+00:00',
      reactivation_deadline: '2026-07-18T09:00:00+00:00',
      appeal_status: 'none',
      deal: {
        owner_kind: 'booking',
        owner_uuid: 'booking-uuid-1',
        product_name: 'Sneakers Shade Fit',
        missed_deadline_at: '2026-06-18T03:00:00+00:00',
      },
    },
  },
}

const cleanResponse: UgcSuspensionStatusResponse = {
  data: { is_suspended: false, suspension: null },
}

describe('useUgcSuspension', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('populates isSuspended + suspension on a suspended fetch', async () => {
    vi.mocked(faceApi.getUgcSuspensionStatus).mockResolvedValueOnce(suspendedResponse)

    const { isSuspended, suspension, error, fetchStatus } = useUgcSuspension()

    await fetchStatus()

    expect(faceApi.getUgcSuspensionStatus).toHaveBeenCalledOnce()
    expect(isSuspended.value).toBe(true)
    expect(suspension.value).toEqual(suspendedResponse.data.suspension)
    expect(error.value).toBeNull()
  })

  it('clears suspension on a clean fetch', async () => {
    vi.mocked(faceApi.getUgcSuspensionStatus).mockResolvedValueOnce(cleanResponse)

    const { isSuspended, suspension, fetchStatus } = useUgcSuspension()

    await fetchStatus()

    expect(isSuspended.value).toBe(false)
    expect(suspension.value).toBeNull()
  })

  it('sets error message and resets state on fetch failure', async () => {
    vi.mocked(faceApi.getUgcSuspensionStatus).mockRejectedValueOnce(new Error('Network down'))

    const { isSuspended, suspension, error, fetchStatus } = useUgcSuspension()

    await fetchStatus()

    expect(error.value).toBe('Network down')
    expect(isSuspended.value).toBe(false)
    expect(suspension.value).toBeNull()
  })

  it('toggles isLoading around the request', async () => {
    let resolve!: (value: UgcSuspensionStatusResponse) => void
    vi.mocked(faceApi.getUgcSuspensionStatus).mockReturnValueOnce(
      new Promise<UgcSuspensionStatusResponse>((r) => {
        resolve = r
      }),
    )

    const { isLoading, fetchStatus } = useUgcSuspension()

    const pending = fetchStatus()
    expect(isLoading.value).toBe(true)

    resolve(cleanResponse)
    await pending
    expect(isLoading.value).toBe(false)
  })
})
