import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { candidatureApi } from '../../services/candidatureApi'
import { useReleaseCandidature } from '../useReleaseCandidature'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    releaseCandidature: vi.fn(),
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

describe('useReleaseCandidature', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns the release data and success message on success', async () => {
    vi.mocked(candidatureApi.releaseCandidature).mockResolvedValue({
      data: { candidature_status: 'cancelled', message: 'La place a été libérée et le règlement remboursé.' },
    })

    const { releaseCandidature, successMessage, error } = useReleaseCandidature()
    const result = await releaseCandidature('cand-1')

    expect(result).not.toBeNull()
    expect(successMessage.value).toBe('La place a été libérée et le règlement remboursé.')
    expect(error.value).toBeNull()
  })

  it('surfaces a release-specific message on 400 INVALID_STATUS', async () => {
    vi.mocked(candidatureApi.releaseCandidature).mockRejectedValue(
      makeAxiosError(400, {
        error: { code: 'INVALID_STATUS', message: 'Seule une candidature acceptée peut être libérée.' },
      }),
    )

    const { releaseCandidature, error } = useReleaseCandidature()
    const result = await releaseCandidature('cand-1')

    expect(result).toBeNull()
    expect(error.value).toBe('Seule une candidature acceptée peut être libérée.')
  })

  it('surfaces the backend message on a 403 (ownership)', async () => {
    vi.mocked(candidatureApi.releaseCandidature).mockRejectedValue(
      makeAxiosError(403, { error: { message: 'Cette candidature ne concerne pas une de vos missions' } }),
    )

    const { releaseCandidature, error } = useReleaseCandidature()
    const result = await releaseCandidature('cand-1')

    expect(result).toBeNull()
    expect(error.value).toBe('Cette candidature ne concerne pas une de vos missions')
  })

  it('falls back to "Candidature introuvable" on 404', async () => {
    vi.mocked(candidatureApi.releaseCandidature).mockRejectedValue(
      makeAxiosError(404, { error: { message: 'Not found' } }),
    )

    const { releaseCandidature, error } = useReleaseCandidature()
    const result = await releaseCandidature('cand-1')

    expect(result).toBeNull()
    expect(error.value).toBe('Candidature introuvable')
  })

  it('surfaces a server-error message on 500 (generic axios else branch)', async () => {
    vi.mocked(candidatureApi.releaseCandidature).mockRejectedValue(makeAxiosError(500, {}))

    const { releaseCandidature, error } = useReleaseCandidature()
    const result = await releaseCandidature('cand-1')

    expect(result).toBeNull()
    expect(error.value).toBe('Le serveur est temporairement indisponible. Veuillez réessayer.')
  })

  it('falls back to a generic message on a non-Axios error (outer else branch)', async () => {
    vi.mocked(candidatureApi.releaseCandidature).mockRejectedValue(new Error('boom'))

    const { releaseCandidature, error } = useReleaseCandidature()
    const result = await releaseCandidature('cand-1')

    expect(result).toBeNull()
    expect(error.value).toBe('Une erreur est survenue. Veuillez réessayer.')
  })
})
