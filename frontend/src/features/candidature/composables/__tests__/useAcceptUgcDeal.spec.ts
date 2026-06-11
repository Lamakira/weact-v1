import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError, AxiosHeaders } from 'axios'
import { useAcceptUgcDeal } from '../useAcceptUgcDeal'
import { candidatureApi } from '../../services/candidatureApi'
import type { Candidature } from '../../types'

vi.mock('../../services/candidatureApi', () => ({
  candidatureApi: {
    acceptUgcMission: vi.fn(),
  },
}))

const MISSION_ID = 'mission-uuid-under-test'

function makeCandidature(): Candidature {
  return {
    id: 'candidature-1',
    mission_id: MISSION_ID,
    face_id: 'face-1',
    status: 'confirmed',
    status_label: 'Confirmée',
    message_motivation: null,
    created_at: '2026-06-11T10:00:00Z',
    updated_at: '2026-06-11T10:00:00Z',
  }
}

function makeAxiosError(status: number, body: unknown = {}): AxiosError {
  // AxiosError needs a real config; AxiosHeaders + minimal config keep it valid.
  const headers = new AxiosHeaders()
  return new AxiosError(
    `Request failed with status code ${status}`,
    String(status),
    { headers, url: '/x' } as never,
    null,
    {
      data: body,
      status,
      statusText: '',
      headers: {},
      config: { headers, url: '/x' } as never,
    },
  )
}

describe('useAcceptUgcDeal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns the candidature and resets flags on success', async () => {
    vi.mocked(candidatureApi.acceptUgcMission).mockResolvedValueOnce({
      data: makeCandidature(),
      message: 'Mission acceptée — votre engagement est enregistré',
    })

    const { acceptUgcMission, isAccepting, error, errorCode } = useAcceptUgcDeal()
    const result = await acceptUgcMission(MISSION_ID)

    expect(candidatureApi.acceptUgcMission).toHaveBeenCalledWith(MISSION_ID)
    expect(result?.status).toBe('confirmed')
    expect(isAccepting.value).toBe(false)
    expect(error.value).toBeNull()
    expect(errorCode.value).toBeNull()
  })

  it('exposes UGC_SUBSCRIPTION_REQUIRED on a 403 paywall', async () => {
    vi.mocked(candidatureApi.acceptUgcMission).mockRejectedValueOnce(
      makeAxiosError(403, {
        error: {
          code: 'UGC_SUBSCRIPTION_REQUIRED',
          message: "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus).",
        },
      }),
    )

    const { acceptUgcMission, errorCode, error } = useAcceptUgcDeal()
    const result = await acceptUgcMission(MISSION_ID)

    expect(result).toBeNull()
    expect(errorCode.value).toBe('UGC_SUBSCRIPTION_REQUIRED')
    expect(error.value).toBeTruthy()
  })

  it('exposes MISSION_FULL on a 422 capacity error', async () => {
    vi.mocked(candidatureApi.acceptUgcMission).mockRejectedValueOnce(
      makeAxiosError(422, {
        error: {
          code: 'MISSION_FULL',
          message: 'Toutes les places de cette mission sont déjà pourvues.',
        },
      }),
    )

    const { acceptUgcMission, errorCode } = useAcceptUgcDeal()
    const result = await acceptUgcMission(MISSION_ID)

    expect(result).toBeNull()
    expect(errorCode.value).toBe('MISSION_FULL')
  })

  it('keeps errorCode null on a non-axios error but sets a message', async () => {
    vi.mocked(candidatureApi.acceptUgcMission).mockRejectedValueOnce(new Error('boom'))

    const { acceptUgcMission, errorCode, error } = useAcceptUgcDeal()
    const result = await acceptUgcMission(MISSION_ID)

    expect(result).toBeNull()
    expect(errorCode.value).toBeNull()
    expect(error.value).toBeTruthy()
  })

  it('resets isAccepting to false after a failure', async () => {
    vi.mocked(candidatureApi.acceptUgcMission).mockRejectedValueOnce(makeAxiosError(500))

    const { acceptUgcMission, isAccepting } = useAcceptUgcDeal()
    const pending = acceptUgcMission(MISSION_ID)

    expect(isAccepting.value).toBe(true)
    await pending
    expect(isAccepting.value).toBe(false)
  })
})
