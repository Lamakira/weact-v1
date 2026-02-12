import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { usePaginatedFaces } from '../usePaginatedFaces'
import * as publicFacesApi from '../../services/publicFacesApi'
import type { PublicFacesResponse, PublicFace } from '../../services/publicFacesApi'

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
vi.mock('../../services/publicFacesApi', () => ({
  fetchPublicFaces: vi.fn(),
}))

const mockFaces: PublicFace[] = [
  {
    id: 1,
    username: 'adjoua-dossou',
    prenom: 'Adjoua',
    nom: 'Dossou',
    ville: 'Cotonou',
    categorie: 'acteur',
    categorie_label: 'Acteur',
    is_available: true,
    profile_photo_thumbnail_url: 'https://example.com/photo1.jpg',
    average_rating: 4.5,
  },
  {
    id: 2,
    username: 'koffi-agbangla',
    prenom: 'Koffi',
    nom: 'Agbangla',
    ville: 'Porto-Novo',
    categorie: 'mannequin',
    categorie_label: 'Mannequin',
    is_available: false,
    profile_photo_thumbnail_url: null,
    average_rating: null,
  },
]

const mockResponse: PublicFacesResponse = {
  data: mockFaces,
  meta: {
    current_page: 1,
    last_page: 3,
    per_page: 15,
    total: 35,
  },
  message: 'Faces retrieved successfully',
}

describe('usePaginatedFaces', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockQuery = {}
    vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)
  })

  afterEach(() => {
    vi.resetAllMocks()
  })

  describe('Initial state', () => {
    it('has correct initial state before mounting', () => {
      const { faces, isLoading, error, isEmpty } = usePaginatedFaces(15)

      expect(faces.value).toEqual([])
      expect(isLoading.value).toBe(false)
      expect(error.value).toBe(null)
      expect(isEmpty.value).toBe(true)
    })

    it('defaults to page 1 when no query param', () => {
      const { currentPage } = usePaginatedFaces(15)

      expect(currentPage.value).toBe(1)
    })

    it('reads page from URL query param', () => {
      mockQuery = { page: '3' }

      const { currentPage } = usePaginatedFaces(15)

      expect(currentPage.value).toBe(3)
    })

    it('defaults to page 1 for invalid query param', () => {
      mockQuery = { page: '-1' }

      const { currentPage } = usePaginatedFaces(15)

      // Math.max(1, -1) = 1
      expect(currentPage.value).toBe(1)
    })
  })

  describe('loadPage', () => {
    it('calls API with correct page, perPage and filters', async () => {
      const { loadPage } = usePaginatedFaces(15)

      await loadPage(1)

      expect(publicFacesApi.fetchPublicFaces).toHaveBeenCalledWith(1, 15, {
        categorie: undefined,
        niche: undefined,
        ville: undefined,
        search: undefined,
      })
    })

    it('sets loading state during fetch', async () => {
      const { isLoading, loadPage } = usePaginatedFaces(15)

      const promise = loadPage(1)
      // Loading should be true immediately after calling
      expect(isLoading.value).toBe(true)

      await promise
      expect(isLoading.value).toBe(false)
    })

    it('populates faces data on success', async () => {
      const { faces, loadPage } = usePaginatedFaces(15)

      await loadPage(1)

      expect(faces.value).toEqual(mockFaces)
    })

    it('populates meta data on success', async () => {
      const { meta, loadPage } = usePaginatedFaces(15)

      await loadPage(1)

      expect(meta.value).toEqual(mockResponse.meta)
    })

    it('handles API error gracefully', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockRejectedValue(new Error('Network error'))

      const { error, faces, loadPage } = usePaginatedFaces(15)

      await loadPage(1)

      expect(error.value).toContain('Une erreur est survenue')
      expect(faces.value).toEqual([])
    })

    it('clears error before new fetch', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces)
        .mockRejectedValueOnce(new Error('Network error'))
        .mockResolvedValueOnce(mockResponse)

      const { error, loadPage } = usePaginatedFaces(15)

      await loadPage(1)
      expect(error.value).not.toBe(null)

      await loadPage(1)
      expect(error.value).toBe(null)
    })
  })

  describe('Computed properties', () => {
    it('computes totalPages from meta', async () => {
      const { totalPages, loadPage } = usePaginatedFaces(15)

      await loadPage(1)

      expect(totalPages.value).toBe(3)
    })

    it('computes totalItems from meta', async () => {
      const { totalItems, loadPage } = usePaginatedFaces(15)

      await loadPage(1)

      expect(totalItems.value).toBe(35)
    })

    it('computes hasNextPage correctly when not on last page', async () => {
      const { hasNextPage, loadPage } = usePaginatedFaces(15)

      await loadPage(1)
      expect(hasNextPage.value).toBe(true)
    })

    it('computes hasNextPage as false on last page', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue({
        ...mockResponse,
        meta: { ...mockResponse.meta, current_page: 3, last_page: 3 },
      })
      mockQuery = { page: '3' }

      const { hasNextPage, loadPage } = usePaginatedFaces(15)

      await loadPage(3)
      expect(hasNextPage.value).toBe(false)
    })

    it('computes hasPreviousPage as false on page 1', async () => {
      const { hasPreviousPage, loadPage } = usePaginatedFaces(15)

      await loadPage(1)
      expect(hasPreviousPage.value).toBe(false)
    })

    it('computes hasPreviousPage as true on page > 1', async () => {
      mockQuery = { page: '2' }
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue({
        ...mockResponse,
        meta: { ...mockResponse.meta, current_page: 2 },
      })

      const { hasPreviousPage, loadPage } = usePaginatedFaces(15)

      await loadPage(2)
      expect(hasPreviousPage.value).toBe(true)
    })

    it('computes isEmpty correctly when no data', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue({
        ...mockResponse,
        data: [],
        meta: { ...mockResponse.meta, total: 0 },
      })

      const { isEmpty, loadPage } = usePaginatedFaces(15)

      await loadPage(1)

      expect(isEmpty.value).toBe(true)
    })

    it('computes isEmpty as false when data exists', async () => {
      const { isEmpty, loadPage } = usePaginatedFaces(15)

      await loadPage(1)

      expect(isEmpty.value).toBe(false)
    })
  })

  describe('URL sync', () => {
    it('updates URL when navigating to different page', async () => {
      const { loadPage } = usePaginatedFaces(15)

      // Current page is 1 (from empty query)
      // Try to navigate to page 2
      await loadPage(2)

      expect(mockPush).toHaveBeenCalledWith({
        query: { page: '2' },
      })
    })

    it('removes page param from URL when going to page 1', async () => {
      mockQuery = { page: '2' }
      const { loadPage } = usePaginatedFaces(15)

      await loadPage(1)

      expect(mockPush).toHaveBeenCalledWith({
        query: { page: undefined },
      })
    })

    it('does not push to router when page matches current', async () => {
      // Current page is 1
      const { loadPage } = usePaginatedFaces(15)

      await loadPage(1)

      // Should call API but not push to router (page already matches)
      expect(publicFacesApi.fetchPublicFaces).toHaveBeenCalled()
      expect(mockPush).not.toHaveBeenCalled()
    })
  })

  describe('retry', () => {
    it('retries loading current page', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces)
        .mockRejectedValueOnce(new Error('Network error'))
        .mockResolvedValueOnce(mockResponse)

      const { retry, error, faces, loadPage } = usePaginatedFaces(15)

      await loadPage(1)
      expect(error.value).not.toBe(null)

      await retry()
      expect(error.value).toBe(null)
      expect(faces.value).toEqual(mockFaces)
    })
  })

  describe('Navigation methods', () => {
    it('nextPage calls loadPage with next page number', async () => {
      const { nextPage, loadPage } = usePaginatedFaces(15)

      await loadPage(1)
      mockPush.mockClear()

      nextPage()

      expect(mockPush).toHaveBeenCalledWith({
        query: { page: '2' },
      })
    })

    it('nextPage does nothing on last page', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue({
        ...mockResponse,
        meta: { ...mockResponse.meta, current_page: 3, last_page: 3 },
      })
      mockQuery = { page: '3' }

      const { nextPage, loadPage } = usePaginatedFaces(15)

      await loadPage(3)
      mockPush.mockClear()

      nextPage()

      expect(mockPush).not.toHaveBeenCalled()
    })

    it('previousPage does nothing on page 1', async () => {
      const { previousPage, loadPage } = usePaginatedFaces(15)

      await loadPage(1)
      mockPush.mockClear()

      previousPage()

      expect(mockPush).not.toHaveBeenCalled()
    })
  })

  // ─── Search Tests (Task 9) ────────────────────────────────────────

  describe('Search support', () => {
    it('reads search param from URL on mount', () => {
      mockQuery = { search: 'Adjoua' }

      const { filters } = usePaginatedFaces(15)

      expect(filters.value.search).toBe('Adjoua')
    })

    it('passes search to API call', async () => {
      mockQuery = { search: 'Adjoua' }

      const { loadPage } = usePaginatedFaces(15)
      await loadPage(1)

      expect(publicFacesApi.fetchPublicFaces).toHaveBeenCalledWith(1, 15, {
        categorie: undefined,
        niche: undefined,
        ville: undefined,
        search: 'Adjoua',
      })
    })

    it('page resets to 1 when search changes via updateFilters', async () => {
      mockQuery = { page: '3', categorie: 'acteur' }

      const { updateFilters } = usePaginatedFaces(15)
      await updateFilters({ search: 'Kofi' })

      // updateFilters resets page to 1 by omitting page param
      expect(mockPush).toHaveBeenCalledWith({
        query: { search: 'Kofi' },
      })
    })

    it('search combined with filters in API call', async () => {
      mockQuery = { search: 'Adjoua', categorie: 'acteur', ville: 'Cotonou' }

      const { loadPage } = usePaginatedFaces(15)
      await loadPage(1)

      expect(publicFacesApi.fetchPublicFaces).toHaveBeenCalledWith(1, 15, {
        categorie: 'acteur',
        niche: undefined,
        ville: 'Cotonou',
        search: 'Adjoua',
      })
    })

    it('hasActiveFilters is true when search is present', () => {
      mockQuery = { search: 'Adjoua' }

      const { hasActiveFilters } = usePaginatedFaces(15)

      expect(hasActiveFilters.value).toBe(true)
    })

    it('clearFilters clears search along with other filters', async () => {
      mockQuery = { search: 'Adjoua', categorie: 'acteur' }

      const { clearFilters } = usePaginatedFaces(15)
      await clearFilters()

      expect(mockPush).toHaveBeenCalledWith({ query: {} })
    })
  })
})
