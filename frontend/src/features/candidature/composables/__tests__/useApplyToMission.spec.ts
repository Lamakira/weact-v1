import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { candidatureApi } from '../../services/candidatureApi'
import { useApplyToMission } from '../useApplyToMission'

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
  info: vi.fn(),
}

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    applyToMission: vi.fn(),
  },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => mockToast,
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

describe('useApplyToMission', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows a toast with the server message on gender mismatch', async () => {
    vi.mocked(candidatureApi.applyToMission).mockRejectedValue(
      makeAxiosError(422, {
        error: {
          code: 'gender_mismatch',
          message: 'Cette mission recherche un profil Femme. Votre profil ne correspond pas au genre requis.',
        },
      }),
    )

    const { apply, error, errorCode } = useApplyToMission()
    const result = await apply(42)

    expect(result.success).toBe(false)
    expect(error.value).toBe('Cette mission recherche un profil Femme. Votre profil ne correspond pas au genre requis.')
    expect(errorCode.value).toBe('gender_mismatch')
    expect(mockToast.error).toHaveBeenCalledWith(
      'Cette mission recherche un profil Femme. Votre profil ne correspond pas au genre requis.',
    )
  })
})
