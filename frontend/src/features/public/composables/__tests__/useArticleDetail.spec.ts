import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useArticleDetail } from '../useArticleDetail'
import * as publicArticlesApi from '../../services/publicArticlesApi'
import type { PublicArticleDetail, PublicArticleDetailResult } from '../../services/publicArticlesApi'

// Mock vue-router
let mockSlug = 'test-article'
vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: {
      get slug() {
        return mockSlug
      },
    },
  }),
}))

// Mock the API module
vi.mock('../../services/publicArticlesApi', () => ({
  fetchPublicArticleDetail: vi.fn(),
}))

const mockArticleDetail: PublicArticleDetail = {
  id: 1,
  title: 'Conseils pour les auditions',
  slug: 'conseils-pour-les-auditions',
  content: '<p>Voici quelques conseils pour réussir vos auditions.</p>',
  excerpt: 'Conseils utiles pour les auditions',
  category: { value: 'conseils-face', label: 'Conseils Face' },
  featured_image: '/images/audition.jpg',
  published_at: '2026-02-01T00:00:00.000Z',
  created_at: '2026-02-01T00:00:00.000Z',
  author_name: 'Admin Test',
}

describe('useArticleDetail', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockSlug = 'test-article'
  })

  describe('loadArticle', () => {
    it('loads article successfully', async () => {
      const successResult: PublicArticleDetailResult = {
        success: true,
        article: mockArticleDetail,
      }
      vi.mocked(publicArticlesApi.fetchPublicArticleDetail).mockResolvedValue(successResult)

      const { article, error, notFound, loadArticle } = useArticleDetail()
      await loadArticle('conseils-pour-les-auditions')

      expect(publicArticlesApi.fetchPublicArticleDetail).toHaveBeenCalledWith(
        'conseils-pour-les-auditions',
      )
      expect(article.value?.title).toBe('Conseils pour les auditions')
      expect(article.value?.content).toContain('conseils pour réussir')
      expect(error.value).toBeNull()
      expect(notFound.value).toBe(false)
    })

    it('handles not found (404)', async () => {
      const notFoundResult: PublicArticleDetailResult = {
        success: false,
        notFound: true,
        error: 'Article non trouvé',
      }
      vi.mocked(publicArticlesApi.fetchPublicArticleDetail).mockResolvedValue(notFoundResult)

      const { article, error, notFound, loadArticle } = useArticleDetail()
      await loadArticle('nonexistent-slug')

      expect(article.value).toBeNull()
      expect(notFound.value).toBe(true)
      expect(error.value).toBe('Article non trouvé')
    })

    it('handles general error', async () => {
      const errorResult: PublicArticleDetailResult = {
        success: false,
        error: 'Une erreur est survenue. Veuillez réessayer.',
      }
      vi.mocked(publicArticlesApi.fetchPublicArticleDetail).mockResolvedValue(errorResult)

      const { article, error, notFound, loadArticle } = useArticleDetail()
      await loadArticle('test-article')

      expect(article.value).toBeNull()
      expect(notFound.value).toBe(false)
      expect(error.value).toBe('Une erreur est survenue. Veuillez réessayer.')
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: PublicArticleDetailResult) => void
      vi.mocked(publicArticlesApi.fetchPublicArticleDetail).mockReturnValue(
        new Promise((resolve) => {
          resolvePromise = resolve
        }),
      )

      const { isLoading, loadArticle } = useArticleDetail()
      const loadPromise = loadArticle('test-article')

      expect(isLoading.value).toBe(true)

      resolvePromise!({ success: true, article: mockArticleDetail })
      await loadPromise

      expect(isLoading.value).toBe(false)
    })

    it('resets state before loading', async () => {
      // First load - error
      vi.mocked(publicArticlesApi.fetchPublicArticleDetail).mockResolvedValueOnce({
        success: false,
        notFound: true,
        error: 'Article non trouvé',
      })

      const { article, error, notFound, loadArticle } = useArticleDetail()
      await loadArticle('bad-slug')
      expect(notFound.value).toBe(true)
      expect(error.value).toBe('Article non trouvé')

      // Second load - success
      vi.mocked(publicArticlesApi.fetchPublicArticleDetail).mockResolvedValueOnce({
        success: true,
        article: mockArticleDetail,
      })

      await loadArticle('good-slug')
      expect(notFound.value).toBe(false)
      expect(error.value).toBeNull()
      expect(article.value?.title).toBe('Conseils pour les auditions')
    })
  })
})
