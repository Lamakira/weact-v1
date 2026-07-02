import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useUgcSuspension } from '../useUgcSuspension'
import { faceApi } from '@/features/face/services/faceApi'
import type { UgcSuspensionStatusResponse } from '@/components/ugc'

// Mock the face API
vi.mock('@/features/face/services/faceApi', () => ({
  faceApi: {
    getUgcSuspensionStatus: vi.fn(),
    resumeUgcSuspension: vi.fn(),
    appealUgcSuspension: vi.fn(),
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

  // --- [5.4] actions resume / appeal ---

  it('resume() returns true on success without refetching status', async () => {
    vi.mocked(faceApi.resumeUgcSuspension).mockResolvedValueOnce({ message: 'ok' })

    const { resume, actionError, isActing } = useUgcSuspension()

    const result = await resume()

    expect(result).toBe(true)
    expect(faceApi.resumeUgcSuspension).toHaveBeenCalledOnce()
    // resume() does NOT refetch (the page navigates instead)
    expect(faceApi.getUgcSuspensionStatus).not.toHaveBeenCalled()
    expect(actionError.value).toBeNull()
    expect(isActing.value).toBe(false)
  })

  it('resume() populates actionError and returns false on failure', async () => {
    vi.mocked(faceApi.resumeUgcSuspension).mockRejectedValueOnce(
      new Error('La fenêtre de régularisation (30 jours) est dépassée.'),
    )

    const { resume, actionError, isActing } = useUgcSuspension()

    const result = await resume()

    expect(result).toBe(false)
    expect(actionError.value).toBe('La fenêtre de régularisation (30 jours) est dépassée.')
    expect(isActing.value).toBe(false)
  })

  it('appeal() refetches status and returns true on success', async () => {
    vi.mocked(faceApi.appealUgcSuspension).mockResolvedValueOnce({ message: 'ok' })
    vi.mocked(faceApi.getUgcSuspensionStatus).mockResolvedValueOnce(suspendedResponse)

    const { appeal, actionError } = useUgcSuspension()

    const result = await appeal()

    expect(result).toBe(true)
    expect(faceApi.appealUgcSuspension).toHaveBeenCalledOnce()
    // appeal() refetches via fetchStatus()
    expect(faceApi.getUgcSuspensionStatus).toHaveBeenCalledOnce()
    expect(actionError.value).toBeNull()
  })

  it('appeal() populates actionError and returns false on failure', async () => {
    vi.mocked(faceApi.appealUgcSuspension).mockRejectedValueOnce(
      new Error('Un appel est déjà enregistré pour cette suspension.'),
    )

    const { appeal, actionError } = useUgcSuspension()

    const result = await appeal()

    expect(result).toBe(false)
    expect(actionError.value).toBe('Un appel est déjà enregistré pour cette suspension.')
    expect(faceApi.getUgcSuspensionStatus).not.toHaveBeenCalled()
  })
})
