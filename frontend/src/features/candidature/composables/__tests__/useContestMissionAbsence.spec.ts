import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { candidatureApi } from '../../services/candidatureApi'
import { useContestMissionAbsence } from '../useContestMissionAbsence'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    contestMissionAbsence: vi.fn(),
  },
}))

function makeAxiosError(status: number, data: Record<string, unknown>): AxiosError {
  return new AxiosError('Request failed', AxiosError.ERR_BAD_REQUEST, undefined, undefined, {
    status,
    data,
    statusText: '',
    headers: {},
    config: {} as never,
  } as AxiosResponse)
}

describe('useContestMissionAbsence', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('calls the dispute endpoint with the mission id and the message, and returns true', async () => {
    vi.mocked(candidatureApi.contestMissionAbsence).mockResolvedValue({ message: 'ok' })

    const { contestAbsence, error, isContesting } = useContestMissionAbsence()
    const ok = await contestAbsence('mission-1', 'J\'étais présente sur place.')

    expect(ok).toBe(true)
    expect(candidatureApi.contestMissionAbsence).toHaveBeenCalledWith('mission-1', 'J\'étais présente sur place.')
    expect(error.value).toBeNull()
    expect(isContesting.value).toBe(false)
  })

  it('surfaces the backend message (e.g. window expired) and returns false', async () => {
    vi.mocked(candidatureApi.contestMissionAbsence).mockRejectedValue(
      makeAxiosError(422, { error: { message: 'Le délai de contestation (72h) est dépassé.' } }),
    )

    const { contestAbsence, error } = useContestMissionAbsence()
    const ok = await contestAbsence('mission-1', 'J\'étais présente sur place.')

    expect(ok).toBe(false)
    expect(error.value).toBe('Le délai de contestation (72h) est dépassé.')
  })

  it('surfaces the server validation error on the message field', async () => {
    vi.mocked(candidatureApi.contestMissionAbsence).mockRejectedValue(
      makeAxiosError(422, {
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Les données fournies ne sont pas valides',
          details: { message: ['Expliquez en quelques mots pourquoi vous contestez (10 caractères minimum).'] },
        },
      }),
    )

    const { contestAbsence, error } = useContestMissionAbsence()
    const ok = await contestAbsence('mission-1', 'court')

    expect(ok).toBe(false)
    expect(error.value).toBe('Expliquez en quelques mots pourquoi vous contestez (10 caractères minimum).')
  })
})
