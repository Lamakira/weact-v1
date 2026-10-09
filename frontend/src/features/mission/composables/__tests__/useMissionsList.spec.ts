import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useMissionsList } from '../useMissionsList'
import { missionApi } from '../../services/missionApi'
import type { Mission, MissionListParams, PaginatedMissionsResponse } from '../../types'

vi.mock('../../services/missionApi', () => ({
  missionApi: {
    getMissionsPage: vi.fn(),
  },
}))

vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn((err) => err?.message || 'Unknown error'),
}))

function createMockMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'm-1',
    titre: 'Test Mission',
    description: 'Test description',
    date_tournage: '2026-03-01',
    profil_recherche: 'Looking for talent',
    budget: 500,
    date_limite_candidature: '2026-02-15',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Paris',
    duree: '1 jour',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  } as Mission
}

function pageOf(
  missions: Mission[],
  meta: Partial<PaginatedMissionsResponse['meta']> = {},
): PaginatedMissionsResponse {
  return {
    data: missions,
    message: 'Success',
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, last_page: 1, per_page: 15, total: missions.length, ...meta },
  }
}

const params: MissionListParams = { page: 1, perPage: 15 }

describe('useMissionsList (server-driven)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches a page with the given params and exposes data + pagination meta', async () => {
    vi.mocked(missionApi.getMissionsPage).mockResolvedValueOnce(
      pageOf([createMockMission({ id: 'a' }), createMockMission({ id: 'b' })], {
        current_page: 2,
        last_page: 4,
        per_page: 10,
        total: 33,
      }),
    )

    const list = useMissionsList()
    await list.fetchMissions({ ...params, page: 2, perPage: 10, sort: 'status', direction: 'desc' })

    expect(missionApi.getMissionsPage).toHaveBeenCalledWith({
      page: 2,
      perPage: 10,
      sort: 'status',
      direction: 'desc',
    })
    expect(list.missions.value.map((m) => m.id)).toEqual(['a', 'b'])
    expect(list.currentPage.value).toBe(2)
    expect(list.lastPage.value).toBe(4)
    expect(list.total.value).toBe(33)
    expect(list.hasLoaded.value).toBe(true)
    expect(list.error.value).toBeNull()
  })

  it('refreshMissions replays the last params', async () => {
    vi.mocked(missionApi.getMissionsPage).mockResolvedValue(pageOf([]))
    const list = useMissionsList()
    await list.fetchMissions({ ...params, status: 'closed' })
    await list.refreshMissions()

    expect(missionApi.getMissionsPage).toHaveBeenCalledTimes(2)
    expect(vi.mocked(missionApi.getMissionsPage).mock.calls[1]![0]).toEqual({ ...params, status: 'closed' })
  })

  it('exposes the error and clears the rows on failure', async () => {
    vi.mocked(missionApi.getMissionsPage).mockResolvedValueOnce(pageOf([createMockMission()]))
    const list = useMissionsList()
    await list.fetchMissions(params)

    vi.mocked(missionApi.getMissionsPage).mockRejectedValueOnce(new Error('Boom'))
    await list.fetchMissions(params)

    expect(list.error.value).toBe('Boom')
    expect(list.missions.value).toEqual([])
    expect(list.isLoading.value).toBe(false)
  })

  it('ignores a stale response when a newer request was issued', async () => {
    let resolveFirst!: (value: PaginatedMissionsResponse) => void
    vi.mocked(missionApi.getMissionsPage)
      .mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve }))
      .mockResolvedValueOnce(pageOf([createMockMission({ id: 'new' })]))

    const list = useMissionsList()
    const first = list.fetchMissions({ ...params, status: 'closed' })
    await list.fetchMissions({ ...params, status: 'published' })
    resolveFirst(pageOf([createMockMission({ id: 'stale' })]))
    await first

    expect(list.missions.value.map((m) => m.id)).toEqual(['new'])
  })

  it('shares one request between identical concurrent calls', async () => {
    let resolveIt!: (value: PaginatedMissionsResponse) => void
    vi.mocked(missionApi.getMissionsPage).mockImplementation(
      () => new Promise((resolve) => { resolveIt = resolve }),
    )

    const list = useMissionsList()
    const a = list.fetchMissions(params)
    const b = list.fetchMissions({ ...params })
    resolveIt(pageOf([createMockMission()]))
    await Promise.all([a, b])

    expect(missionApi.getMissionsPage).toHaveBeenCalledTimes(1)
  })

  it('removeMissionFromList drops the row locally', async () => {
    vi.mocked(missionApi.getMissionsPage).mockResolvedValueOnce(
      pageOf([createMockMission({ id: 'a' }), createMockMission({ id: 'b' })]),
    )
    const list = useMissionsList()
    await list.fetchMissions(params)
    list.removeMissionFromList('a')
    expect(list.missions.value.map((m) => m.id)).toEqual(['b'])
  })

  it('reset clears all state', async () => {
    vi.mocked(missionApi.getMissionsPage).mockResolvedValueOnce(pageOf([createMockMission()]))
    const list = useMissionsList()
    await list.fetchMissions(params)
    list.reset()

    expect(list.missions.value).toEqual([])
    expect(list.total.value).toBe(0)
    expect(list.hasLoaded.value).toBe(false)
    expect(list.error.value).toBeNull()
  })
})
