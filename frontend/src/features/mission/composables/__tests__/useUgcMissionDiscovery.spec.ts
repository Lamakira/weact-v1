import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useUgcMissionDiscovery } from '../useUgcMissionDiscovery'
import { faceMissionApi } from '../../services/faceMissionApi'
import type {
  Mission,
  UgcMissionTeaser,
  UgcPaywallMeta,
  PaginatedUgcMissionsResponse,
} from '../../types'

// Mock the face mission API
vi.mock('../../services/faceMissionApi', () => ({
  faceMissionApi: {
    getUgcMissions: vi.fn(),
  },
}))

// Mock getApiErrorMessage
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn((err) => (err as Error)?.message || 'Unknown error'),
}))

const PAYWALL: UgcPaywallMeta = {
  code: 'UGC_SUBSCRIPTION_REQUIRED',
  message: "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus).",
  pricing_url: '/pricing',
}

// Factory : mission UGC complète (branche éligible). La réponse réelle ne
// contient pas commission_ugc/commission_paid_at (masqués Face) — cast localisé.
function createEligibleMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'uuid-1',
    titre: 'Test sneakers running · 2 vidéos',
    description: 'Brief complet',
    date_tournage: '2026-07-01',
    profil_recherche: 'Créatrices',
    budget: 0,
    date_limite_candidature: '2026-06-24T00:00:00Z',
    nombre_faces_voulu: 3,
    type_mission: 'autre',
    type_mission_label: 'UGC',
    type_mission_autre: null,
    type_compensation: 'hybrid',
    type_compensation_label: 'Produit + Argent',
    nom_produit: 'Sneakers Shade Fit',
    valeur_produit: 35000,
    nombre_videos: 2,
    montant_remuneration: 10000,
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Cotonou',
    duree: 'Livrables vidéo',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    has_paid_payment: false,
    created_at: '2026-06-09T00:00:00Z',
    updated_at: '2026-06-09T00:00:00Z',
    ...overrides,
  } as Mission
}

function createTeaser(overrides: Partial<UgcMissionTeaser> = {}): UgcMissionTeaser {
  return {
    id: 'uuid-1',
    titre: 'Test sneakers running · 2 vidéos',
    type_compensation: 'product',
    type_compensation_label: 'Produit seul',
    nom_produit: 'Sneakers Shade Fit',
    valeur_produit: 35000,
    nombre_videos: 2,
    lieu: 'Cotonou',
    date_limite_candidature: '2026-06-24T00:00:00Z',
    created_at: '2026-06-09T00:00:00Z',
    ...overrides,
  }
}

function createResponse(
  overrides: Partial<PaginatedUgcMissionsResponse> = {},
): PaginatedUgcMissionsResponse {
  return {
    data: [createEligibleMission()],
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, last_page: 1, per_page: 12, total: 1, can_access_ugc: true },
    ...overrides,
  }
}

describe('useUgcMissionDiscovery', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches eligible missions: items populated, canAccessUgc true, no paywall', async () => {
    vi.mocked(faceMissionApi.getUgcMissions).mockResolvedValueOnce(createResponse())

    const { items, canAccessUgc, paywall, totalCount, fetchMissions } = useUgcMissionDiscovery()

    await fetchMissions(1)

    expect(faceMissionApi.getUgcMissions).toHaveBeenCalledWith(1)
    expect(items.value).toHaveLength(1)
    expect(canAccessUgc.value).toBe(true)
    expect(paywall.value).toBeNull()
    expect(totalCount.value).toBe(1)
  })

  it('fetches free branch: teasers + paywall populated, canAccessUgc false', async () => {
    vi.mocked(faceMissionApi.getUgcMissions).mockResolvedValueOnce(
      createResponse({
        data: [createTeaser()],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 12,
          total: 1,
          can_access_ugc: false,
          paywall: PAYWALL,
        },
      }),
    )

    const { items, canAccessUgc, paywall, fetchMissions } = useUgcMissionDiscovery()

    await fetchMissions(1)

    expect(items.value).toHaveLength(1)
    expect(canAccessUgc.value).toBe(false)
    expect(paywall.value).toEqual(PAYWALL)
  })

  it('sets error message and clears items on fetch failure', async () => {
    vi.mocked(faceMissionApi.getUgcMissions).mockRejectedValueOnce(new Error('Network down'))

    const { items, error, isLoading, fetchMissions } = useUgcMissionDiscovery()

    await fetchMissions(1)

    expect(error.value).toBe('Network down')
    expect(items.value).toHaveLength(0)
    expect(isLoading.value).toBe(false)
  })

  it('nextPage fetches the next page only when one exists', async () => {
    vi.mocked(faceMissionApi.getUgcMissions).mockResolvedValue(
      createResponse({
        meta: { current_page: 1, last_page: 3, per_page: 12, total: 30, can_access_ugc: true },
      }),
    )

    const { fetchMissions, nextPage, hasNextPage } = useUgcMissionDiscovery()

    // Guard: no fetch when state is initial (currentPage=1, lastPage=1)
    expect(hasNextPage.value).toBe(false)
    await nextPage()
    expect(faceMissionApi.getUgcMissions).not.toHaveBeenCalled()

    await fetchMissions(1)
    await nextPage()

    expect(faceMissionApi.getUgcMissions).toHaveBeenLastCalledWith(2)
  })

  it('prevPage fetches the previous page only when not on page 1', async () => {
    vi.mocked(faceMissionApi.getUgcMissions).mockResolvedValue(
      createResponse({
        meta: { current_page: 2, last_page: 3, per_page: 12, total: 30, can_access_ugc: true },
      }),
    )

    const { fetchMissions, prevPage, hasPrevPage } = useUgcMissionDiscovery()

    // Guard: no fetch on page 1
    expect(hasPrevPage.value).toBe(false)
    await prevPage()
    expect(faceMissionApi.getUgcMissions).not.toHaveBeenCalled()

    await fetchMissions(2)
    await prevPage()

    expect(faceMissionApi.getUgcMissions).toHaveBeenLastCalledWith(1)
  })

  it('isEmpty is true only when not loading and no items', async () => {
    vi.mocked(faceMissionApi.getUgcMissions).mockResolvedValueOnce(
      createResponse({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 12, total: 0, can_access_ugc: true },
      }),
    )

    const { isEmpty, fetchMissions } = useUgcMissionDiscovery()

    expect(isEmpty.value).toBe(true)

    await fetchMissions(1)

    expect(isEmpty.value).toBe(true)
  })
})
