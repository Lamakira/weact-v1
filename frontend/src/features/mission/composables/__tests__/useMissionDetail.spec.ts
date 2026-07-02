import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError, AxiosHeaders } from 'axios'
import { useMissionDetail } from '../useMissionDetail'
import { faceMissionApi } from '../../services/faceMissionApi'
import type { Mission, MissionCandidature, MissionResponse } from '../../types'

// Mock the face mission API
vi.mock('../../services/faceMissionApi', () => ({
  faceMissionApi: {
    getMissionDetail: vi.fn(),
  },
}))

// Mock getApiErrorMessage
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn((err) => (err as Error)?.message || 'Unknown error'),
}))

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

function createMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'mission-uuid-1',
    titre: 'Test Mission',
    description: 'Description',
    date_tournage: '2026-07-01',
    profil_recherche: 'Créatrices',
    budget: 150000,
    date_limite_candidature: '2026-06-24',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    type_mission_autre: null,
    type_compensation: null,
    type_compensation_label: null,
    nom_produit: null,
    valeur_produit: null,
    nombre_videos: null,
    montant_remuneration: null,
    commission_ugc: null,
    commission_paid_at: null,
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Cotonou',
    duree: '2 jours',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    has_paid_payment: false,
    created_at: '2026-06-01T00:00:00Z',
    updated_at: '2026-06-01T00:00:00Z',
    ...overrides,
  }
}

const CANDIDATURE: MissionCandidature = {
  id: 'candidature-1',
  mission_id: 'mission-uuid-1',
  face_id: 'face-1',
  status: 'pending',
  status_label: 'En attente',
  message_motivation: null,
  created_at: '2026-06-02T00:00:00Z',
  updated_at: '2026-06-02T00:00:00Z',
}

const PAYWALL_MESSAGE = "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus)."

describe('useMissionDetail', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('stores mission and candidature on success', async () => {
    const response: MissionResponse = { data: createMission(), candidature: CANDIDATURE }
    vi.mocked(faceMissionApi.getMissionDetail).mockResolvedValue(response)

    const detail = useMissionDetail()
    await detail.fetchMission('mission-uuid-1')

    expect(detail.mission.value?.id).toBe('mission-uuid-1')
    expect(detail.candidature.value?.id).toBe('candidature-1')
    expect(detail.error.value).toBeNull()
    expect(detail.notFound.value).toBe(false)
    expect(detail.ugcPaywall.value).toBe(false)
  })

  it('sets notFound on a 404', async () => {
    vi.mocked(faceMissionApi.getMissionDetail).mockRejectedValue(makeAxiosError(404))

    const detail = useMissionDetail()
    await detail.fetchMission('missing')

    expect(detail.notFound.value).toBe(true)
    expect(detail.error.value).toBeNull()
    expect(detail.ugcPaywall.value).toBe(false)
  })

  it('sets error on a generic failure', async () => {
    vi.mocked(faceMissionApi.getMissionDetail).mockRejectedValue(new Error('Network down'))

    const detail = useMissionDetail()
    await detail.fetchMission('mission-uuid-1')

    expect(detail.error.value).toBe('Network down')
    expect(detail.notFound.value).toBe(false)
    expect(detail.ugcPaywall.value).toBe(false)
  })

  it('sets ugcPaywall (not error/notFound) on a 403 UGC_SUBSCRIPTION_REQUIRED', async () => {
    vi.mocked(faceMissionApi.getMissionDetail).mockRejectedValue(
      makeAxiosError(403, {
        error: { code: 'UGC_SUBSCRIPTION_REQUIRED', message: PAYWALL_MESSAGE },
      }),
    )

    const detail = useMissionDetail()
    await detail.fetchMission('ugc-mission')

    expect(detail.ugcPaywall.value).toBe(true)
    expect(detail.ugcPaywallMessage.value).toBe(PAYWALL_MESSAGE)
    expect(detail.error.value).toBeNull()
    expect(detail.notFound.value).toBe(false)
  })

  it('treats a 403 with another code as a generic error', async () => {
    vi.mocked(faceMissionApi.getMissionDetail).mockRejectedValue(
      makeAxiosError(403, { error: { code: 'FORBIDDEN', message: 'x' } }),
    )

    const detail = useMissionDetail()
    await detail.fetchMission('mission-uuid-1')

    expect(detail.error.value).not.toBeNull()
    expect(detail.ugcPaywall.value).toBe(false)
    expect(detail.ugcPaywallMessage.value).toBeNull()
  })

  it('resets the paywall flags on a refetch', async () => {
    vi.mocked(faceMissionApi.getMissionDetail).mockRejectedValueOnce(
      makeAxiosError(403, {
        error: { code: 'UGC_SUBSCRIPTION_REQUIRED', message: PAYWALL_MESSAGE },
      }),
    )

    const detail = useMissionDetail()
    await detail.fetchMission('ugc-mission')
    expect(detail.ugcPaywall.value).toBe(true)

    vi.mocked(faceMissionApi.getMissionDetail).mockResolvedValueOnce({
      data: createMission(),
      candidature: null,
    })
    await detail.fetchMission('ugc-mission')

    expect(detail.ugcPaywall.value).toBe(false)
    expect(detail.ugcPaywallMessage.value).toBeNull()
    expect(detail.mission.value?.id).toBe('mission-uuid-1')
  })
})
