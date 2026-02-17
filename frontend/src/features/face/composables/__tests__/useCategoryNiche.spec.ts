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
    categorie: 'acteur',
    categorie_label: 'Acteur',
    niche: 'beaute',
    niche_label: 'Beauté',
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
    it('fetches category and niche successfully', async () => {
      vi.mocked(faceApi.getCategoryNiche).mockResolvedValue(mockResponse)

      const { categoryNicheInfo, isLoading, error, fetchCategoryNiche } = useCategoryNiche()

      await fetchCategoryNiche()

      expect(categoryNicheInfo.value).toEqual(mockCategoryNicheInfo)
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
    it('updates categorie only successfully', async () => {
      vi.mocked(faceApi.updateCategoryNiche).mockResolvedValue(mockResponse)

      const { categoryNicheInfo, isSaving, error, updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({ categorie: 'influenceur' })

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockCategoryNicheInfo)
      expect(result.message).toBe('Profil mis à jour avec succès')
      expect(categoryNicheInfo.value).toEqual(mockCategoryNicheInfo)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('updates niche only successfully', async () => {
      const nicheResponse: CategoryNicheResponse = {
        data: {
          categorie: null,
          categorie_label: null,
          niche: 'mode',
          niche_label: 'Mode',
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updateCategoryNiche).mockResolvedValue(nicheResponse)

      const { categoryNicheInfo, updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({ niche: 'mode' })

      expect(result.success).toBe(true)
      expect(categoryNicheInfo.value?.niche).toBe('mode')
    })

    it('updates both categorie and niche together', async () => {
      vi.mocked(faceApi.updateCategoryNiche).mockResolvedValue(mockResponse)

      const { updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({
        categorie: 'acteur',
        niche: 'beaute',
      })

      expect(result.success).toBe(true)
      expect(faceApi.updateCategoryNiche).toHaveBeenCalledWith({
        categorie: 'acteur',
        niche: 'beaute',
      })
    })

    it('sets saving state during update', async () => {
      let resolvePromise: (value: CategoryNicheResponse) => void
      const promise = new Promise<CategoryNicheResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.updateCategoryNiche).mockReturnValue(promise)

      const { isSaving, updateCategoryNiche } = useCategoryNiche()

      const updatePromise = updateCategoryNiche({ categorie: 'mannequin' })
      expect(isSaving.value).toBe(true)

      resolvePromise!(mockResponse)
      await updatePromise

      expect(isSaving.value).toBe(false)
    })

    it('handles update error', async () => {
      vi.mocked(faceApi.updateCategoryNiche).mockRejectedValue(new Error('Update failed'))

      const { error, updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({ categorie: 'acteur' })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('validateCategory', () => {
    it('validates valid category (delegates to backend)', () => {
      const { validateCategory } = useCategoryNiche()

      const result = validateCategory('acteur')

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates null category', () => {
      const { validateCategory } = useCategoryNiche()

      const result = validateCategory(null)

      expect(result.valid).toBe(true)
    })

    it('allows any category value (backend validates)', () => {
      // Frontend delegates enum validation to backend
      const { validateCategory } = useCategoryNiche()

      const result = validateCategory('any_value' as any)

      expect(result.valid).toBe(true)
    })

    it('sends invalid category to backend for validation', async () => {
      // Backend rejects invalid values and returns error message
      vi.mocked(faceApi.updateCategoryNiche).mockRejectedValue(new Error('Invalid category'))

      const { updateCategoryNiche, error } = useCategoryNiche()

      const result = await updateCategoryNiche({ categorie: 'invalid' as any })

      expect(result.success).toBe(false)
      expect(faceApi.updateCategoryNiche).toHaveBeenCalledWith({ categorie: 'invalid' })
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('validateNiche', () => {
    it('validates valid niche (delegates to backend)', () => {
      const { validateNiche } = useCategoryNiche()

      const result = validateNiche('beaute')

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates null niche', () => {
      const { validateNiche } = useCategoryNiche()

      const result = validateNiche(null)

      expect(result.valid).toBe(true)
    })

    it('allows any niche value (backend validates)', () => {
      // Frontend delegates enum validation to backend
      const { validateNiche } = useCategoryNiche()

      const result = validateNiche('any_value' as any)

      expect(result.valid).toBe(true)
    })

    it('sends invalid niche to backend for validation', async () => {
      // Backend rejects invalid values and returns error message
      vi.mocked(faceApi.updateCategoryNiche).mockRejectedValue(new Error('Invalid niche'))

      const { updateCategoryNiche, error } = useCategoryNiche()

      const result = await updateCategoryNiche({ niche: 'invalid' as any })

      expect(result.success).toBe(false)
      expect(faceApi.updateCategoryNiche).toHaveBeenCalledWith({ niche: 'invalid' })
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(faceApi.updateCategoryNiche).mockRejectedValue(new Error('Error'))

      const { error, updateCategoryNiche, clearError } = useCategoryNiche()

      await updateCategoryNiche({ categorie: 'acteur' })
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })

  describe('clearing category and niche', () => {
    it('can clear categorie by passing null', async () => {
      const clearedResponse: CategoryNicheResponse = {
        data: {
          categorie: null,
          categorie_label: null,
          niche: 'beaute',
          niche_label: 'Beauté',
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updateCategoryNiche).mockResolvedValue(clearedResponse)

      const { categoryNicheInfo, updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({ categorie: null })

      expect(result.success).toBe(true)
      expect(categoryNicheInfo.value?.categorie).toBeNull()
      expect(faceApi.updateCategoryNiche).toHaveBeenCalledWith({ categorie: null })
    })

    it('can clear niche by passing null', async () => {
      const clearedResponse: CategoryNicheResponse = {
        data: {
          categorie: 'acteur',
          categorie_label: 'Acteur',
          niche: null,
          niche_label: null,
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updateCategoryNiche).mockResolvedValue(clearedResponse)

      const { categoryNicheInfo, updateCategoryNiche } = useCategoryNiche()

      const result = await updateCategoryNiche({ niche: null })

      expect(result.success).toBe(true)
      expect(categoryNicheInfo.value?.niche).toBeNull()
      expect(faceApi.updateCategoryNiche).toHaveBeenCalledWith({ niche: null })
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
