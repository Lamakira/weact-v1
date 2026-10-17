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

  it('calls the dispute endpoint with the mission id and returns true', async () => {
    vi.mocked(candidatureApi.contestMissionAbsence).mockResolvedValue({ message: 'ok' })

    const { contestAbsence, error, isContesting } = useContestMissionAbsence()
    const ok = await contestAbsence('mission-1')

    expect(ok).toBe(true)
    expect(candidatureApi.contestMissionAbsence).toHaveBeenCalledWith('mission-1')
    expect(error.value).toBeNull()
    expect(isContesting.value).toBe(false)
  })

  it('surfaces the backend message (e.g. window expired) and returns false', async () => {
    vi.mocked(candidatureApi.contestMissionAbsence).mockRejectedValue(
      makeAxiosError(422, { error: { message: 'Le délai de contestation (72h) est dépassé.' } }),
    )

    const { contestAbsence, error } = useContestMissionAbsence()
    const ok = await contestAbsence('mission-1')

    expect(ok).toBe(false)
    expect(error.value).toBe('Le délai de contestation (72h) est dépassé.')
  })
})
