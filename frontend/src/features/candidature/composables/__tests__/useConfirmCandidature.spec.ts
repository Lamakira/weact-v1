import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { candidatureApi } from '../../services/candidatureApi'
import { useConfirmCandidature } from '../useConfirmCandidature'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    confirmCandidature: vi.fn(),
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
  return new AxiosError(
    'Network Error',
    AxiosError.ERR_NETWORK,
    undefined,
    undefined,
    undefined,
  )
}

describe('useConfirmCandidature', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('surfaces the backend error.message on 422 PAYMENT_NOT_CONFIRMED', async () => {
    vi.mocked(candidatureApi.confirmCandidature).mockRejectedValue(
      makeAxiosError(422, {
        error: {
          code: 'PAYMENT_NOT_CONFIRMED',
          message:
            'Le paiement de la mission doit être confirmé avant de pouvoir confirmer votre participation',
        },
      }),
    )

    const { confirmCandidature, error } = useConfirmCandidature()
    const result = await confirmCandidature('cand-1')

    expect(result).toBeNull()
    expect(error.value).toBe(
      'Le paiement de la mission doit être confirmé avant de pouvoir confirmer votre participation',
    )
  })

  it('falls back to a generic 422 message when backend does not include one', async () => {
    vi.mocked(candidatureApi.confirmCandidature).mockRejectedValue(
      makeAxiosError(422, {}),
    )

    const { confirmCandidature, error } = useConfirmCandidature()
    await confirmCandidature('cand-1')

    expect(error.value).toBe(
      'Cette candidature ne peut pas être confirmée dans son état actuel.',
    )
  })

  it('uses the backend message on 400 INVALID_STATUS', async () => {
    vi.mocked(candidatureApi.confirmCandidature).mockRejectedValue(
      makeAxiosError(400, {
        error: {
          code: 'INVALID_STATUS',
          message: 'Seules les candidatures acceptées peuvent être confirmées',
        },
      }),
    )

    const { confirmCandidature, error } = useConfirmCandidature()
    await confirmCandidature('cand-1')

    expect(error.value).toBe('Seules les candidatures acceptées peuvent être confirmées')
  })

  it('shows an authorization message on 403', async () => {
    vi.mocked(candidatureApi.confirmCandidature).mockRejectedValue(
      makeAxiosError(403, { message: 'Cette candidature ne vous appartient pas' }),
    )

    const { confirmCandidature, error } = useConfirmCandidature()
    await confirmCandidature('cand-1')

    expect(error.value).toBe('Cette candidature ne vous appartient pas')
  })

  it('shows a not-found message on 404', async () => {
    vi.mocked(candidatureApi.confirmCandidature).mockRejectedValue(
      makeAxiosError(404, {}),
    )

    const { confirmCandidature, error } = useConfirmCandidature()
    await confirmCandidature('cand-1')

    expect(error.value).toBe('Candidature introuvable')
  })

  it('shows a throttle message on 429', async () => {
    vi.mocked(candidatureApi.confirmCandidature).mockRejectedValue(
      makeAxiosError(429, {}),
    )

    const { confirmCandidature, error } = useConfirmCandidature()
    await confirmCandidature('cand-1')

    expect(error.value).toBe(
      'Trop de tentatives. Veuillez réessayer dans quelques instants.',
    )
  })

  it('shows a server-error message on 500', async () => {
    vi.mocked(candidatureApi.confirmCandidature).mockRejectedValue(
      makeAxiosError(500, {}),
    )

    const { confirmCandidature, error } = useConfirmCandidature()
    await confirmCandidature('cand-1')

    expect(error.value).toBe(
      'Le serveur a rencontré une erreur. Veuillez réessayer plus tard.',
    )
  })

  it('shows a network-error message when the request has no response', async () => {
    vi.mocked(candidatureApi.confirmCandidature).mockRejectedValue(makeNetworkError())

    const { confirmCandidature, error } = useConfirmCandidature()
    await confirmCandidature('cand-1')

    expect(error.value).toBe(
      'Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.',
    )
  })
})
