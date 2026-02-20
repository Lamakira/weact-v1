import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useCategoryNiche } from '../useCategoryNiche'
import { faceApi } from '../../services/faceApi'
import type { CategoryNicheInfo, CategoryNicheResponse, CategoryOption, NicheOption } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getCategoryNiche: vi.fn(),
    updateCategoryNiche: vi.fn(),
    getCategoryOptions: vi.fn(),
    getNicheOptions: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('useCategoryNiche', () => {
  const mockCategoryNicheInfo: CategoryNicheInfo = {
    categories: [
      { value: 'acteur', label: 'Acteur' },
      { value: 'mannequin', label: 'Mannequin' },
    ],
    niches: [{ value: 'beaute', label: 'Beauté' }],
  }

  const mockResponse: CategoryNicheResponse = {
    data: mockCategoryNicheInfo,
    message: 'Profil mis à jour avec succès',
  }

  const mockCategoryOptions: CategoryOption[] = [
    { value: 'acteur', label: 'Acteur' },
    { value: 'influenceur', label: 'Influenceur' },
    { value: 'createur', label: 'Créateur de contenu' },
    { value: 'mannequin', label: 'Mannequin' },
    { value: 'figurant', label: 'Figurant' },
    { value: 'modele_photo', label: 'Modèle Photo' },
    { value: 'egerie', label: 'Égérie' },
  ]

  const mockNicheOptions: NicheOption[] = [
    { value: 'beaute', label: 'Beauté' },
    { value: 'nourriture', label: 'Nourriture' },
    { value: 'decouverte', label: 'Découverte' },
    { value: 'mode', label: 'Mode' },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const {
        categoryNicheInfo,
        categoryOptions,
        nicheOptions,
        isLoading,
        isSaving,
        error,
      } = useCategoryNiche()

      expect(categoryNicheInfo.value).toBeNull()
      expect(categoryOptions.value).toEqual([])
      expect(nicheOptions.value).toEqual([])
      expect(isLoading.value).toBe(false)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })
  })

  describe('fetchCategoryNiche', () => {
    it('fetches categories and niches successfully', async () => {
      vi.mocked(faceApi.getCategoryNiche).mockResolvedValue(mockResponse)

      const { categoryNicheInfo, isLoading, error, fetchCategoryNiche } = useCategoryNiche()

      await fetchCategoryNiche()

      expect(categoryNicheInfo.value).toEqual(mockCategoryNicheInfo)
      expect(categoryNicheInfo.value!.categories).toHaveLength(2)
      expect(categoryNicheInfo.value!.niches).toHaveLength(1)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getCategoryNiche).toHaveBeenCalledOnce()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: CategoryNicheResponse) => void
      const promise = new Promise<CategoryNicheResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getCategoryNiche).mockReturnValue(promise)

      const { isLoading, fetchCategoryNiche } = useCategoryNiche()

      const fetchPromise = fetchCategoryNiche()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getCategoryNiche).mockRejectedValue(new Error('Network error'))

      const { categoryNicheInfo, error, fetchCategoryNiche } = useCategoryNiche()

      await fetchCategoryNiche()

      expect(categoryNicheInfo.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('updateCategoryNiche', () => {
    it('updates categories array successfully', async () => {
      vi.mocked(faceApi.updateCategoryNiche).mockResolvedValue(mockResponse)

      const { categoryNicheInfo, isSaving, error, updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({ categories: ['acteur', 'mannequin'] })

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockCategoryNicheInfo)
      expect(result.message).toBe('Profil mis à jour avec succès')
      expect(categoryNicheInfo.value).toEqual(mockCategoryNicheInfo)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('updates niches array successfully', async () => {
      const nichesResponse: CategoryNicheResponse = {
        data: {
          categories: [],
          niches: [
            { value: 'mode', label: 'Mode' },
            { value: 'beaute', label: 'Beauté' },
          ],
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updateCategoryNiche).mockResolvedValue(nichesResponse)

      const { categoryNicheInfo, updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({ niches: ['mode', 'beaute'] })

      expect(result.success).toBe(true)
      expect(categoryNicheInfo.value?.niches).toHaveLength(2)
    })

    it('updates both categories and niches together', async () => {
      vi.mocked(faceApi.updateCategoryNiche).mockResolvedValue(mockResponse)

      const { updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({
        categories: ['acteur', 'mannequin'],
        niches: ['beaute'],
      })

      expect(result.success).toBe(true)
      expect(faceApi.updateCategoryNiche).toHaveBeenCalledWith({
        categories: ['acteur', 'mannequin'],
        niches: ['beaute'],
      })
    })

    it('sets saving state during update', async () => {
      let resolvePromise: (value: CategoryNicheResponse) => void
      const promise = new Promise<CategoryNicheResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.updateCategoryNiche).mockReturnValue(promise)

      const { isSaving, updateCategoryNiche } = useCategoryNiche()

      const updatePromise = updateCategoryNiche({ categories: ['mannequin'] })
      expect(isSaving.value).toBe(true)

      resolvePromise!(mockResponse)
      await updatePromise

      expect(isSaving.value).toBe(false)
    })

    it('handles update error', async () => {
      vi.mocked(faceApi.updateCategoryNiche).mockRejectedValue(new Error('Update failed'))

      const { error, updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({ categories: ['acteur'] })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })

    it('can clear categories by passing null', async () => {
      const clearedResponse: CategoryNicheResponse = {
        data: {
          categories: [],
          niches: [{ value: 'beaute', label: 'Beauté' }],
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updateCategoryNiche).mockResolvedValue(clearedResponse)

      const { categoryNicheInfo, updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({ categories: null })

      expect(result.success).toBe(true)
      expect(categoryNicheInfo.value?.categories).toEqual([])
      expect(faceApi.updateCategoryNiche).toHaveBeenCalledWith({ categories: null })
    })

    it('can clear niches by passing null', async () => {
      const clearedResponse: CategoryNicheResponse = {
        data: {
          categories: [{ value: 'acteur', label: 'Acteur' }],
          niches: [],
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updateCategoryNiche).mockResolvedValue(clearedResponse)

      const { categoryNicheInfo, updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({ niches: null })

      expect(result.success).toBe(true)
      expect(categoryNicheInfo.value?.niches).toEqual([])
      expect(faceApi.updateCategoryNiche).toHaveBeenCalledWith({ niches: null })
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(faceApi.updateCategoryNiche).mockRejectedValue(new Error('Error'))

      const { error, updateCategoryNiche, clearError } = useCategoryNiche()

      await updateCategoryNiche({ categories: ['acteur'] })
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })

  describe('fetchCategoryOptions', () => {
    it('fetches category options successfully', async () => {
      vi.mocked(faceApi.getCategoryOptions).mockResolvedValue({ data: mockCategoryOptions })

      const { categoryOptions, fetchCategoryOptions } = useCategoryNiche()

      await fetchCategoryOptions()

      expect(categoryOptions.value).toEqual(mockCategoryOptions)
      expect(faceApi.getCategoryOptions).toHaveBeenCalledOnce()
    })

    it('handles fetch options error', async () => {
      vi.mocked(faceApi.getCategoryOptions).mockRejectedValue(new Error('Network error'))

      const { error, fetchCategoryOptions } = useCategoryNiche()

      await fetchCategoryOptions()

      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('fetchNicheOptions', () => {
    it('fetches niche options successfully', async () => {
      vi.mocked(faceApi.getNicheOptions).mockResolvedValue({ data: mockNicheOptions })

      const { nicheOptions, fetchNicheOptions } = useCategoryNiche()

      await fetchNicheOptions()

      expect(nicheOptions.value).toEqual(mockNicheOptions)
      expect(faceApi.getNicheOptions).toHaveBeenCalledOnce()
    })

    it('handles fetch options error', async () => {
      vi.mocked(faceApi.getNicheOptions).mockRejectedValue(new Error('Network error'))

      const { error, fetchNicheOptions } = useCategoryNiche()

      await fetchNicheOptions()

      expect(error.value).toBe('Une erreur est survenue')
    })
  })
})
