import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { usePaginatedArticles } from '../usePaginatedArticles'
import * as publicArticlesApi from '../../services/publicArticlesApi'
import type { PublicArticlesResponse, PublicArticle } from '../../services/publicArticlesApi'

// Mock vue-router
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
vi.mock('../../services/publicArticlesApi', () => ({
  fetchPublicArticles: vi.fn(),
}))

const mockArticles: PublicArticle[] = [
  {
    id: 1,
    title: 'Conseils pour les auditions',
    slug: 'conseils-pour-les-auditions',
    excerpt: 'Quelques conseils utiles',
    category: { value: 'conseils-face', label: 'Conseils Face' },
    featured_image: null,
    published_at: '2026-02-01T00:00:00.000Z',
    created_at: '2026-02-01T00:00:00.000Z',
    author_name: 'Admin Test',
  },
  {
    id: 2,
    title: 'Guide du producteur',
    slug: 'guide-du-producteur',
    excerpt: 'Guide complet',
    category: { value: 'guide-producteur', label: 'Guide Producteur' },
    featured_image: '/images/guide.jpg',
    published_at: '2026-01-28T00:00:00.000Z',
    created_at: '2026-01-28T00:00:00.000Z',
    author_name: null,
  },
]

const mockResponse: PublicArticlesResponse = {
  data: mockArticles,
  meta: {
    current_page: 1,
    last_page: 3,
    per_page: 15,
    total: 45,
  },
  message: 'Articles récupérés avec succès',
}

describe('usePaginatedArticles', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockQuery = {}
    mockPush.mockResolvedValue(undefined)
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('initial state', () => {
    it('has correct default values', () => {
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockResolvedValue(mockResponse)

      const { articles, isLoading, error, currentPage, currentCategory, totalPages, isEmpty } =
        usePaginatedArticles()

      expect(articles.value).toEqual([])
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(currentPage.value).toBe(1)
      expect(currentCategory.value).toBeUndefined()
      expect(totalPages.value).toBe(1)
      expect(isEmpty.value).toBe(true)
    })
  })

  describe('loadPage', () => {
    it('loads articles and sets state on success', async () => {
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockResolvedValue(mockResponse)

      const { articles, totalPages, totalArticles, loadPage } = usePaginatedArticles()
      await loadPage(1)

      expect(publicArticlesApi.fetchPublicArticles).toHaveBeenCalledWith(1, 15, undefined)
      expect(articles.value).toHaveLength(2)
      expect(articles.value[0].title).toBe('Conseils pour les auditions')
      expect(totalPages.value).toBe(3)
      expect(totalArticles.value).toBe(45)
    })

    it('passes category filter to API', async () => {
      mockQuery = { category: 'actualites' }
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockResolvedValue(mockResponse)

      const { loadPage } = usePaginatedArticles()
      await loadPage(1, 'actualites')

      expect(publicArticlesApi.fetchPublicArticles).toHaveBeenCalledWith(1, 15, 'actualites')
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: PublicArticlesResponse) => void
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockReturnValue(
        new Promise((resolve) => {
          resolvePromise = resolve
        }),
      )

      const { isLoading, loadPage } = usePaginatedArticles()
      const fetchPromise = loadPage(1)

      expect(isLoading.value).toBe(true)

      resolvePromise!(mockResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockRejectedValue(
        new Error('Network error'),
      )

      const { articles, error, loadPage } = usePaginatedArticles()
      await loadPage(1)

      expect(error.value).toContain('Une erreur est survenue')
      expect(articles.value).toHaveLength(0)
    })

    it('enforces minimum page of 1', async () => {
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockResolvedValue(mockResponse)

      const { loadPage } = usePaginatedArticles()
      await loadPage(-5)

      expect(publicArticlesApi.fetchPublicArticles).toHaveBeenCalledWith(1, 15, undefined)
    })
  })

  describe('filterByCategory', () => {
    it('pushes router with category and page 1', async () => {
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockResolvedValue(mockResponse)

      const { filterByCategory } = usePaginatedArticles()
      await filterByCategory('conseils-face')

      expect(mockPush).toHaveBeenCalledWith({
        query: expect.objectContaining({ category: 'conseils-face' }),
      })
    })

    it('removes category when filtering with undefined', async () => {
      mockQuery = { category: 'conseils-face' }
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockResolvedValue(mockResponse)

      const { filterByCategory } = usePaginatedArticles()
      await filterByCategory(undefined)

      expect(mockPush).toHaveBeenCalledWith({
        query: expect.objectContaining({ category: undefined }),
      })
    })
  })

  describe('computed properties', () => {
    it('reads page from URL query', () => {
      mockQuery = { page: '3' }
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockResolvedValue(mockResponse)

      const { currentPage } = usePaginatedArticles()
      expect(currentPage.value).toBe(3)
    })

    it('reads category from URL query', () => {
      mockQuery = { category: 'actualites' }
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockResolvedValue(mockResponse)

      const { currentCategory } = usePaginatedArticles()
      expect(currentCategory.value).toBe('actualites')
    })

    it('isEmpty is true when no articles and not loading', async () => {
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockResolvedValue({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        message: 'Articles récupérés avec succès',
      })

      const { isEmpty, loadPage } = usePaginatedArticles()
      await loadPage(1)

      expect(isEmpty.value).toBe(true)
    })
  })

  describe('retry', () => {
    it('reloads current page and category', async () => {
      vi.mocked(publicArticlesApi.fetchPublicArticles).mockResolvedValue(mockResponse)

      const { retry } = usePaginatedArticles()
      await retry()

      expect(publicArticlesApi.fetchPublicArticles).toHaveBeenCalledWith(1, 15, undefined)
    })
  })
})
