import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock adminArticlesApi
const mockGetArticles = vi.fn()
const mockCreateArticle = vi.fn()
const mockUpdateArticle = vi.fn()
const mockDeleteArticle = vi.fn()
const mockUpdateArticleStatus = vi.fn()
vi.mock('../../services/adminArticlesApi', () => ({
  adminArticlesApi: {
    getArticles: (...args: unknown[]) => mockGetArticles(...args),
    createArticle: (...args: unknown[]) => mockCreateArticle(...args),
    updateArticle: (...args: unknown[]) => mockUpdateArticle(...args),
    deleteArticle: (...args: unknown[]) => mockDeleteArticle(...args),
    updateArticleStatus: (...args: unknown[]) => mockUpdateArticleStatus(...args),
  },
}))

// Mock error utilities
vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorDetails: vi.fn(
    (err: { response?: { data?: { error?: { details?: Record<string, string[]> } } } } ) => {
      return err?.response?.data?.error?.details ?? {}
    },
  ),
  getApiErrorMessage: vi.fn(
    (err: { response?: { data?: { error?: { message?: string } } } }) => {
      return err?.response?.data?.error?.message ?? 'Error'
    },
  ),
}))

import { useAdminArticles } from '../useAdminArticles'

const mockArticle = {
  id: 1,
  title: 'Test Article',
  slug: 'test-article',
  content: '<p>Content here</p>',
  excerpt: 'Test excerpt',
  category: { value: 'conseils-face', label: 'Conseils Face' },
  status: { value: 'draft', label: 'Brouillon' },
  featured_image: null,
  published_at: null,
  created_at: '2026-02-01T00:00:00.000Z',
  updated_at: '2026-02-01T00:00:00.000Z',
  admin: { id: 1, name: 'Admin Test' },
}

describe('useAdminArticles - fetchArticles', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('fetches articles list successfully', async () => {
    const mockResponse = {
      data: [mockArticle, { ...mockArticle, id: 2, title: 'Second Article' }],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
    }
    mockGetArticles.mockResolvedValue(mockResponse)

    const { articles, pagination, fetchArticles } = useAdminArticles()
    await fetchArticles()

    expect(mockGetArticles).toHaveBeenCalledWith(undefined)
    expect(articles.value).toHaveLength(2)
    expect(articles.value[0].title).toBe('Test Article')
    expect(pagination.value?.total).toBe(2)
  })

  it('passes params to API including search and filters', async () => {
    mockGetArticles.mockResolvedValue({
      data: [mockArticle],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })

    const { fetchArticles } = useAdminArticles()
    await fetchArticles({ page: 2, search: 'Test', category: 'actualites', status: 'published' })

    expect(mockGetArticles).toHaveBeenCalledWith({
      page: 2,
      search: 'Test',
      category: 'actualites',
      status: 'published',
    })
  })

  it('sets loading state during fetch', async () => {
    let resolvePromise: (value: unknown) => void
    mockGetArticles.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    const { isLoading, fetchArticles } = useAdminArticles()
    const fetchPromise = fetchArticles()

    expect(isLoading.value).toBe(true)

    resolvePromise!({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    })

    await fetchPromise

    expect(isLoading.value).toBe(false)
  })

  it('handles fetch error', async () => {
    mockGetArticles.mockRejectedValue({
      response: {
        data: {
          error: { message: 'Unauthorized' },
        },
      },
    })

    const { articles, error, fetchArticles } = useAdminArticles()
    await fetchArticles()

    expect(error.value).toBe('Unauthorized')
    expect(articles.value).toHaveLength(0)
  })
})

describe('useAdminArticles - createArticle', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('creates article successfully', async () => {
    mockCreateArticle.mockResolvedValue({
      data: mockArticle,
      message: 'Article créé avec succès',
    })

    const { article, createArticle } = useAdminArticles()
    const result = await createArticle({
      title: 'Test Article',
      content: '<p>Content here</p>',
      category: 'conseils-face',
    })

    expect(result.success).toBe(true)
    expect(result.message).toBe('Article créé avec succès')
    expect(article.value?.title).toBe('Test Article')
  })

  it('returns validation errors on creation failure', async () => {
    mockCreateArticle.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'validation_error',
            message: 'Les données fournies ne sont pas valides',
            details: { title: ['Le titre est requis.'] },
          },
        },
      },
    })

    const { createArticle } = useAdminArticles()
    const result = await createArticle({
      title: '',
      content: '',
      category: 'conseils-face',
    })

    expect(result.success).toBe(false)
    expect(result.message).toBe('Les données fournies ne sont pas valides')
    expect(result.errors?.title?.[0]).toBe('Le titre est requis.')
  })
})

describe('useAdminArticles - updateArticle', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('updates article successfully', async () => {
    mockUpdateArticle.mockResolvedValue({
      data: { ...mockArticle, title: 'Updated Title' },
      message: 'Article mis à jour avec succès',
    })

    const { article, updateArticle } = useAdminArticles()
    const result = await updateArticle(1, { title: 'Updated Title' })

    expect(result.success).toBe(true)
    expect(result.message).toBe('Article mis à jour avec succès')
    expect(article.value?.title).toBe('Updated Title')
  })

  it('returns validation errors on update failure', async () => {
    mockUpdateArticle.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'validation_error',
            message: 'Les données fournies ne sont pas valides',
            details: { category: ['La catégorie sélectionnée est invalide.'] },
          },
        },
      },
    })

    const { updateArticle } = useAdminArticles()
    const result = await updateArticle(1, { category: 'invalid' })

    expect(result.success).toBe(false)
    expect(result.errors?.category?.[0]).toBe('La catégorie sélectionnée est invalide.')
  })
})

describe('useAdminArticles - deleteArticle', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('deletes article successfully', async () => {
    mockDeleteArticle.mockResolvedValue({
      message: 'Article supprimé avec succès',
    })

    const { deleteArticle } = useAdminArticles()
    const result = await deleteArticle(1)

    expect(result.success).toBe(true)
    expect(result.message).toBe('Article supprimé avec succès')
    expect(mockDeleteArticle).toHaveBeenCalledWith(1)
  })

  it('handles delete error', async () => {
    mockDeleteArticle.mockRejectedValue({
      response: {
        data: {
          error: { message: 'Article non trouvé' },
        },
      },
    })

    const { deleteArticle } = useAdminArticles()
    const result = await deleteArticle(999)

    expect(result.success).toBe(false)
    expect(result.message).toBe('Article non trouvé')
  })

  it('sets loading state during delete', async () => {
    let resolvePromise: (value: unknown) => void
    mockDeleteArticle.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    const { isLoading, deleteArticle } = useAdminArticles()
    const deletePromise = deleteArticle(1)

    expect(isLoading.value).toBe(true)

    resolvePromise!({ message: 'Article supprimé avec succès' })

    await deletePromise

    expect(isLoading.value).toBe(false)
  })
})

describe('useAdminArticles - toggleStatus', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('toggles draft to published', async () => {
    mockUpdateArticleStatus.mockResolvedValue({
      data: { ...mockArticle, status: { value: 'published', label: 'Publié' } },
      message: 'Article publié avec succès',
    })

    const { article, toggleStatus } = useAdminArticles()
    const result = await toggleStatus(1, 'draft')

    expect(result.success).toBe(true)
    expect(mockUpdateArticleStatus).toHaveBeenCalledWith(1, 'published')
    expect(article.value?.status.value).toBe('published')
  })

  it('toggles published to draft', async () => {
    mockUpdateArticleStatus.mockResolvedValue({
      data: { ...mockArticle, status: { value: 'draft', label: 'Brouillon' } },
      message: 'Article dépublié avec succès',
    })

    const { toggleStatus } = useAdminArticles()
    const result = await toggleStatus(1, 'published')

    expect(result.success).toBe(true)
    expect(mockUpdateArticleStatus).toHaveBeenCalledWith(1, 'draft')
  })

  it('updates article in list when toggling status', async () => {
    mockGetArticles.mockResolvedValue({
      data: [{ ...mockArticle, status: { value: 'draft', label: 'Brouillon' } }],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })
    mockUpdateArticleStatus.mockResolvedValue({
      data: { ...mockArticle, status: { value: 'published', label: 'Publié' } },
      message: 'Article publié avec succès',
    })

    const { articles, fetchArticles, toggleStatus } = useAdminArticles()
    await fetchArticles()
    expect(articles.value[0].status.value).toBe('draft')

    await toggleStatus(1, 'draft')
    expect(articles.value[0].status.value).toBe('published')
  })

  it('returns error on toggle failure', async () => {
    mockUpdateArticleStatus.mockRejectedValue({
      response: {
        data: {
          error: { message: 'Impossible de changer le statut' },
        },
      },
    })

    const { toggleStatus } = useAdminArticles()
    const result = await toggleStatus(1, 'draft')

    expect(result.success).toBe(false)
    expect(result.message).toBe('Impossible de changer le statut')
  })
})
