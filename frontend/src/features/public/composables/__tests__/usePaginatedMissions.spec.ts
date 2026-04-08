import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { usePaginatedMissions } from '../usePaginatedMissions'
import * as publicMissionsApi from '../../services/publicMissionsApi'
import type { PublicMissionsResponse, PublicMission } from '../../services/publicMissionsApi'

const mockPush = vi.fn()
const mockReplace = vi.fn()
let mockQuery: Record<string, string | undefined> = {}

vi.mock('vue-router', () => ({
  useRoute: () => ({
    get query() {
      return mockQuery
    },
  }),
  useRouter: () => ({
    push: mockPush,
    replace: mockReplace,
  }),
}))

vi.mock('../../services/publicMissionsApi', () => ({
  fetchPublicMissions: vi.fn(),
}))

const mockMissions: PublicMission[] = [
  {
    id: 1,
    slug: 'publicite-tv',
    titre: 'Publicité TV',
    description: 'Tournage publicitaire',
    date_tournage: '2025-03-15',
    profil_recherche: null,
    budget: 100000,
    date_limite_candidature: '2025-02-28',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    type_mission_autre: null,
    genre_voulu: 'mixte',
    genre_voulu_label: 'Mixte',
    lieu: 'Cotonou',
    duree: '1 jour',
    status: 'published',
    status_label: 'Publiée',
    created_at: '2025-01-15T10:00:00+00:00',
    producer: {
      id: 1,
      display_name: 'Producteur A',
      profile_photo_thumbnail_url: null,
      average_rating: 4.5,
      ratings_count: 10,
    },
  },
]

const mockResponse: PublicMissionsResponse = {
  data: mockMissions,
  meta: {
    current_page: 1,
    last_page: 3,
    per_page: 15,
    total: 35,
  },
  message: 'Missions retrieved successfully',
}

describe('usePaginatedMissions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockQuery = {}
    vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)
  })

  it('starts loading immediately and defaults to page 1', () => {
    const { missions, isLoading, error, isEmpty, currentPage } = usePaginatedMissions(15)

    expect(missions.value).toEqual([])
    expect(isLoading.value).toBe(true)
    expect(error.value).toBe(null)
    expect(isEmpty.value).toBe(false)
    expect(currentPage.value).toBe(1)
  })

  it('reads page and filters from the URL and auto-loads data', async () => {
    mockQuery = {
      page: '2',
      type_mission: 'publicite',
      lieu: 'Cotonou',
      budget_min: '1000',
      budget_max: '5000',
    }

    const { currentPage, filters } = usePaginatedMissions(15)
    await flushPromises()

    expect(currentPage.value).toBe(2)
    expect(filters.value).toEqual({
      type_mission: 'publicite',
      lieu: 'Cotonou',
      budget_min: 1000,
      budget_max: 5000,
    })
    expect(publicMissionsApi.fetchPublicMissions).toHaveBeenCalledWith(2, 15, {
      type_mission: 'publicite',
      lieu: 'Cotonou',
      budget_min: 1000,
      budget_max: 5000,
    })
  })

  it('fetches the current page directly when loadPage matches the route', async () => {
    const { loadPage } = usePaginatedMissions(15)
    await flushPromises()
    vi.clearAllMocks()

    await loadPage(1)

    expect(publicMissionsApi.fetchPublicMissions).toHaveBeenCalledWith(1, 15, {})
    expect(mockPush).not.toHaveBeenCalled()
  })

  it('updates the URL instead of fetching immediately when changing page', async () => {
    const { loadPage } = usePaginatedMissions(15)
    await flushPromises()
    vi.clearAllMocks()

    await loadPage(2)

    expect(mockPush).toHaveBeenCalledWith({
      query: { page: '2' },
    })
    expect(publicMissionsApi.fetchPublicMissions).not.toHaveBeenCalled()
  })

  it('handles API errors and retries the current page', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissions).mockRejectedValueOnce(new Error('Network error'))

    const { error, missions, retry } = usePaginatedMissions(15)
    await flushPromises()

    expect(error.value).toContain('Une erreur est survenue')
    expect(missions.value).toEqual([])

    vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValueOnce(mockResponse)

    retry()
    await flushPromises()

    expect(error.value).toBe(null)
    expect(missions.value).toEqual(mockMissions)
  })

  it('computes pagination helpers from the response metadata', async () => {
    const { totalPages, totalMissions, hasNextPage, hasPreviousPage, isEmpty } = usePaginatedMissions(15)
    await flushPromises()

    expect(totalPages.value).toBe(3)
    expect(totalMissions.value).toBe(35)
    expect(hasNextPage.value).toBe(true)
    expect(hasPreviousPage.value).toBe(false)
    expect(isEmpty.value).toBe(false)
  })
})
