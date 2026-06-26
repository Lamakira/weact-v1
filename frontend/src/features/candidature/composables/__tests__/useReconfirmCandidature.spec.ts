import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { candidatureApi } from '../../services/candidatureApi'
import { useReconfirmCandidature } from '../useReconfirmCandidature'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    reconfirmCandidature: vi.fn(),
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

function makeNetworkError(): AxiosError {
  return new AxiosError('Network Error', AxiosError.ERR_NETWORK, undefined, undefined, undefined)
}

describe('useReconfirmCandidature', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns the candidature data and success message on success', async () => {
    vi.mocked(candidatureApi.reconfirmCandidature).mockResolvedValue({
      data: { id: 'cand-1' } as never,
      message: 'Participation reconfirmée',
    })

    const { reconfirmCandidature, successMessage, error } = useReconfirmCandidature()
    const result = await reconfirmCandidature('cand-1')

    expect(result).not.toBeNull()
    expect(successMessage.value).toBe('Participation reconfirmée')
    expect(error.value).toBeNull()
  })

  it('surfaces the backend error.message on 422 INVALID_STATUS', async () => {
    vi.mocked(candidatureApi.reconfirmCandidature).mockRejectedValue(
      makeAxiosError(422, {
        error: {
          code: 'INVALID_STATUS',
          message: 'Seules les candidatures acceptées peuvent être reconfirmées',
        },
      }),
    )

    const { reconfirmCandidature, error } = useReconfirmCandidature()
    const result = await reconfirmCandidature('cand-1')

    expect(result).toBeNull()
    expect(error.value).toBe('Seules les candidatures acceptées peuvent être reconfirmées')
  })

  it('falls back to a generic 422 message when backend does not include one', async () => {
    vi.mocked(candidatureApi.reconfirmCandidature).mockRejectedValue(makeAxiosError(422, {}))

    const { reconfirmCandidature, error } = useReconfirmCandidature()
    await reconfirmCandidature('cand-1')

    expect(error.value).toBe('Cette participation ne peut pas être reconfirmée dans son état actuel.')
  })

  it('falls back to the local 422 message when backend returns the generic standardized validation message', async () => {
    vi.mocked(candidatureApi.reconfirmCandidature).mockRejectedValue(
      makeAxiosError(422, { error: { message: 'Les données fournies ne sont pas valides' } }),
    )

    const { reconfirmCandidature, error } = useReconfirmCandidature()
    await reconfirmCandidature('cand-1')

    expect(error.value).toBe('Cette participation ne peut pas être reconfirmée dans son état actuel.')
  })

  it('shows an authorization message on 403', async () => {
    vi.mocked(candidatureApi.reconfirmCandidature).mockRejectedValue(
      makeAxiosError(403, { message: 'Cette candidature ne vous appartient pas' }),
    )

    const { reconfirmCandidature, error } = useReconfirmCandidature()
    await reconfirmCandidature('cand-1')

    expect(error.value).toBe('Cette candidature ne vous appartient pas')
  })

  it('shows a not-found message on 404', async () => {
    vi.mocked(candidatureApi.reconfirmCandidature).mockRejectedValue(makeAxiosError(404, {}))

    const { reconfirmCandidature, error } = useReconfirmCandidature()
    await reconfirmCandidature('cand-1')

    expect(error.value).toBe('Candidature introuvable')
  })

  it('shows a server-error message on 500', async () => {
    vi.mocked(candidatureApi.reconfirmCandidature).mockRejectedValue(makeAxiosError(500, {}))

    const { reconfirmCandidature, error } = useReconfirmCandidature()
    await reconfirmCandidature('cand-1')

    expect(error.value).toBe('Le serveur a rencontré une erreur. Veuillez réessayer plus tard.')
  })

  it('shows a network-error message when the request has no response', async () => {
    vi.mocked(candidatureApi.reconfirmCandidature).mockRejectedValue(makeNetworkError())

    const { reconfirmCandidature, error } = useReconfirmCandidature()
    await reconfirmCandidature('cand-1')

    expect(error.value).toBe('Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.')
  })
})
