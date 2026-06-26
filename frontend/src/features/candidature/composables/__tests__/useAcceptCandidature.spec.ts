import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { candidatureApi } from '../../services/candidatureApi'
import { useAcceptCandidature } from '../useAcceptCandidature'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    acceptCandidature: vi.fn(),
  },
}))

function makeAxiosError(status: number, data: Record<string, unknown>): AxiosError {
  return new AxiosError(
    'Request failed',
    AxiosError.ERR_BAD_REQUEST,
    undefined,
    undefined,
    {
      status,
      data,
      statusText: '',
      headers: {},
      config: {} as never,
    } as AxiosResponse,
  )
}

describe('useAcceptCandidature', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns the candidature data and success message on success', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockResolvedValue({
      data: { id: 'cand-1' } as never,
      message: 'Candidature acceptée',
    })

    const { acceptCandidature, successMessage, error, errorCode } = useAcceptCandidature()
    const result = await acceptCandidature('cand-1')

    expect(result).not.toBeNull()
    expect(successMessage.value).toBe('Candidature acceptée')
    expect(error.value).toBeNull()
    expect(errorCode.value).toBeNull()
  })

  it('exposes errorCode + a capacity message on 422 MISSION_FULL', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockRejectedValue(
      makeAxiosError(422, {
        error: { code: 'MISSION_FULL', message: 'Toutes les places de cette mission sont déjà pourvues.' },
      }),
    )

    const { acceptCandidature, error, errorCode } = useAcceptCandidature()
    const result = await acceptCandidature('cand-1')

    expect(result).toBeNull()
    expect(errorCode.value).toBe('MISSION_FULL')
    expect(error.value).toBe('Toutes les places de cette mission sont déjà pourvues.')
  })

  it('exposes errorCode + a subscription message on 403 UGC_SUBSCRIPTION_REQUIRED', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockRejectedValue(
      makeAxiosError(403, {
        error: { code: 'UGC_SUBSCRIPTION_REQUIRED', message: "Cette Face n'est plus abonnée." },
      }),
    )

    const { acceptCandidature, error, errorCode } = useAcceptCandidature()
    await acceptCandidature('cand-1')

    expect(errorCode.value).toBe('UGC_SUBSCRIPTION_REQUIRED')
    expect(error.value).toBe("Cette Face n'est plus abonnée.")
  })

  it('surfaces the backend message on a plain 403 (ownership)', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockRejectedValue(
      makeAxiosError(403, { error: { message: 'Cette mission ne vous appartient pas.' } }),
    )

    const { acceptCandidature, error, errorCode } = useAcceptCandidature()
    await acceptCandidature('cand-1')

    expect(errorCode.value).toBeNull()
    expect(error.value).toBe('Cette mission ne vous appartient pas.')
  })

  it('falls back to a generic accept message on 422 INVALID_STATUS', async () => {
    vi.mocked(candidatureApi.acceptCandidature).mockRejectedValue(
      makeAxiosError(422, {
        error: { code: 'INVALID_STATUS', message: 'Cette candidature a déjà été traitée.' },
      }),
    )

    const { acceptCandidature, error, errorCode } = useAcceptCandidature()
    await acceptCandidature('cand-1')

    expect(errorCode.value).toBe('INVALID_STATUS')
    expect(error.value).toBe('Cette candidature a déjà été traitée.')
  })
})
