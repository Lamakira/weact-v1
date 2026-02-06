import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { usePaginatedMissions } from '../usePaginatedMissions'
import * as publicMissionsApi from '../../services/publicMissionsApi'
import type { PublicMissionsResponse, PublicMission } from '../../services/publicMissionsApi'

// Mock vue-router - use reactive object like real vue-router does
const mockPush = vi.fn()
let mockQuery: Record<string, string | undefined> = {}

vi.mock('vue-router', () => ({
  useRoute: () => ({
    get query() {
      return mockQuery
    },
  }),
  useRouter: () => ({
    push: mockPush,
  }),
}))

// Mock the API module
vi.mock('../../services/publicMissionsApi', () => ({
  fetchPublicMissions: vi.fn(),
}))

const mockMissions: PublicMission[] = [
  {
    id: 1,
    titre: 'Publicité TV',
    description: 'Tournage publicitaire',
    date_tournage: '2025-03-15',
    profil_recherche: null,
    budget: 100000,
    date_limite_candidature: '2025-02-28',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
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
  {
    id: 2,
    titre: 'Court-métrage',
    description: 'Film court',
    date_tournage: '2025-04-01',
    profil_recherche: 'Jeune femme',
    budget: 200000,
    date_limite_candidature: '2025-03-15',
    nombre_faces_voulu: 1,
    type_mission: 'court_metrage',
    type_mission_label: 'Court-métrage',
    genre_voulu: 'feminin',
    genre_voulu_label: 'Féminin',
    lieu: 'Porto-Novo',
    duree: '3 jours',
    status: 'published',
    status_label: 'Publiée',
    created_at: '2025-01-14T10:00:00+00:00',
    producer: null,
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

  afterEach(() => {
    vi.resetAllMocks()
  })

  describe('Initial state', () => {
    it('has correct initial state before mounting', () => {
      const { missions, isLoading, error, isEmpty } = usePaginatedMissions(15)

      expect(missions.value).toEqual([])
      expect(isLoading.value).toBe(false)
      expect(error.value).toBe(null)
      expect(isEmpty.value).toBe(true)
    })

    it('defaults to page 1 when no query param', () => {
      const { currentPage } = usePaginatedMissions(15)

      expect(currentPage.value).toBe(1)
    })

    it('reads page from URL query param', () => {
      mockQuery = { page: '3' }

      const { currentPage } = usePaginatedMissions(15)

      expect(currentPage.value).toBe(3)
    })

    it('defaults to page 1 for invalid query param', () => {
      mockQuery = { page: '-1' }

      const { currentPage } = usePaginatedMissions(15)

      // Math.max(1, -1) = 1
      expect(currentPage.value).toBe(1)
    })
  })

  describe('loadPage', () => {
    it('calls API with correct page and perPage', async () => {
      const { loadPage } = usePaginatedMissions(15)

      await loadPage(1)

      expect(publicMissionsApi.fetchPublicMissions).toHaveBeenCalledWith(1, 15)
    })

    it('sets loading state during fetch', async () => {
      const { isLoading, loadPage } = usePaginatedMissions(15)

      const promise = loadPage(1)
      expect(isLoading.value).toBe(true)

      await promise
      expect(isLoading.value).toBe(false)
    })

    it('populates missions data on success', async () => {
      const { missions, loadPage } = usePaginatedMissions(15)

      await loadPage(1)

      expect(missions.value).toEqual(mockMissions)
    })

    it('populates meta data on success', async () => {
      const { meta, loadPage } = usePaginatedMissions(15)

      await loadPage(1)

      expect(meta.value).toEqual(mockResponse.meta)
    })

    it('handles API error gracefully', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockRejectedValue(new Error('Network error'))

      const { error, missions, loadPage } = usePaginatedMissions(15)

      await loadPage(1)

      expect(error.value).toContain('Une erreur est survenue')
      expect(missions.value).toEqual([])
    })

    it('clears error before new fetch', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions)
        .mockRejectedValueOnce(new Error('Network error'))
        .mockResolvedValueOnce(mockResponse)

      const { error, loadPage } = usePaginatedMissions(15)

      await loadPage(1)
      expect(error.value).not.toBe(null)

      await loadPage(1)
      expect(error.value).toBe(null)
    })
  })

  describe('Computed properties', () => {
    it('computes totalPages from meta', async () => {
      const { totalPages, loadPage } = usePaginatedMissions(15)

      await loadPage(1)

      expect(totalPages.value).toBe(3)
    })

    it('computes totalMissions from meta', async () => {
      const { totalMissions, loadPage } = usePaginatedMissions(15)

      await loadPage(1)

      expect(totalMissions.value).toBe(35)
    })

    it('computes hasNextPage correctly when not on last page', async () => {
      const { hasNextPage, loadPage } = usePaginatedMissions(15)

      await loadPage(1)
      expect(hasNextPage.value).toBe(true)
    })

    it('computes hasNextPage as false on last page', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue({
        ...mockResponse,
        meta: { ...mockResponse.meta, current_page: 3, last_page: 3 },
      })
      mockQuery = { page: '3' }

      const { hasNextPage, loadPage } = usePaginatedMissions(15)

      await loadPage(3)
      expect(hasNextPage.value).toBe(false)
    })

    it('computes hasPreviousPage as false on page 1', async () => {
      const { hasPreviousPage, loadPage } = usePaginatedMissions(15)

      await loadPage(1)
      expect(hasPreviousPage.value).toBe(false)
    })

    it('computes hasPreviousPage as true on page > 1', async () => {
      mockQuery = { page: '2' }
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue({
        ...mockResponse,
        meta: { ...mockResponse.meta, current_page: 2 },
      })

      const { hasPreviousPage, loadPage } = usePaginatedMissions(15)

      await loadPage(2)
      expect(hasPreviousPage.value).toBe(true)
    })

    it('computes isEmpty correctly when no data', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue({
        ...mockResponse,
        data: [],
        meta: { ...mockResponse.meta, total: 0 },
      })

      const { isEmpty, loadPage } = usePaginatedMissions(15)

      await loadPage(1)

      expect(isEmpty.value).toBe(true)
    })

    it('computes isEmpty as false when data exists', async () => {
      const { isEmpty, loadPage } = usePaginatedMissions(15)

      await loadPage(1)

      expect(isEmpty.value).toBe(false)
    })
  })

  describe('URL sync', () => {
    it('updates URL when navigating to different page', async () => {
      const { loadPage } = usePaginatedMissions(15)

      // Current page is 1 (from empty query)
      // Try to navigate to page 2
      await loadPage(2)

      expect(mockPush).toHaveBeenCalledWith({
        query: { page: '2' },
      })
    })

    it('removes page param from URL when going to page 1', async () => {
      mockQuery = { page: '2' }
      const { loadPage } = usePaginatedMissions(15)

      await loadPage(1)

      expect(mockPush).toHaveBeenCalledWith({
        query: { page: undefined },
      })
    })

    it('does not push to router when page matches current', async () => {
      // Current page is 1
      const { loadPage } = usePaginatedMissions(15)

      await loadPage(1)

      // Should call API but not push to router (page already matches)
      expect(publicMissionsApi.fetchPublicMissions).toHaveBeenCalled()
      expect(mockPush).not.toHaveBeenCalled()
    })
  })

  describe('retry', () => {
    it('retries loading current page', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions)
        .mockRejectedValueOnce(new Error('Network error'))
        .mockResolvedValueOnce(mockResponse)

      const { retry, error, missions, loadPage } = usePaginatedMissions(15)

      await loadPage(1)
      expect(error.value).not.toBe(null)

      await retry()
      expect(error.value).toBe(null)
      expect(missions.value).toEqual(mockMissions)
    })
  })

  describe('Navigation methods', () => {
    it('nextPage calls loadPage with next page number', async () => {
      const { nextPage, loadPage } = usePaginatedMissions(15)

      await loadPage(1)
      mockPush.mockClear()

      nextPage()

      expect(mockPush).toHaveBeenCalledWith({
        query: { page: '2' },
      })
    })

    it('nextPage does nothing on last page', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue({
        ...mockResponse,
        meta: { ...mockResponse.meta, current_page: 3, last_page: 3 },
      })
      mockQuery = { page: '3' }

      const { nextPage, loadPage } = usePaginatedMissions(15)

      await loadPage(3)
      mockPush.mockClear()

      nextPage()

      expect(mockPush).not.toHaveBeenCalled()
    })

    it('previousPage does nothing on page 1', async () => {
      const { previousPage, loadPage } = usePaginatedMissions(15)

      await loadPage(1)
      mockPush.mockClear()

      previousPage()

      expect(mockPush).not.toHaveBeenCalled()
    })
  })
})
