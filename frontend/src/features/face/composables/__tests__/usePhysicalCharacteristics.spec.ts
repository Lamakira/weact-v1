import { describe, it, expect, vi, beforeEach } from 'vitest'
import { usePhysicalCharacteristics } from '../usePhysicalCharacteristics'
import { faceApi } from '../../services/faceApi'
import type { PhysicalCharacteristicsInfo, PhysicalCharacteristicsResponse } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getPhysicalCharacteristics: vi.fn(),
    updatePhysicalCharacteristics: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('usePhysicalCharacteristics', () => {
  const mockPhysicalCharacteristicsInfo: PhysicalCharacteristicsInfo = {
    taille: 175,
    poids: 70,
  }

  const mockResponse: PhysicalCharacteristicsResponse = {
    data: mockPhysicalCharacteristicsInfo,
    message: 'Profil mis à jour avec succès',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { physicalCharacteristicsInfo, isLoading, isSaving, error } =
        usePhysicalCharacteristics()

      expect(physicalCharacteristicsInfo.value).toBeNull()
      expect(isLoading.value).toBe(false)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })
  })

  describe('fetchPhysicalCharacteristics', () => {
    it('fetches physical characteristics successfully', async () => {
      vi.mocked(faceApi.getPhysicalCharacteristics).mockResolvedValue(mockResponse)

      const { physicalCharacteristicsInfo, isLoading, error, fetchPhysicalCharacteristics } =
        usePhysicalCharacteristics()

      await fetchPhysicalCharacteristics()

      expect(physicalCharacteristicsInfo.value).toEqual(mockPhysicalCharacteristicsInfo)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getPhysicalCharacteristics).toHaveBeenCalledOnce()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: PhysicalCharacteristicsResponse) => void
      const promise = new Promise<PhysicalCharacteristicsResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getPhysicalCharacteristics).mockReturnValue(promise)

      const { isLoading, fetchPhysicalCharacteristics } = usePhysicalCharacteristics()

      const fetchPromise = fetchPhysicalCharacteristics()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getPhysicalCharacteristics).mockRejectedValue(new Error('Network error'))

      const { physicalCharacteristicsInfo, error, fetchPhysicalCharacteristics } =
        usePhysicalCharacteristics()

      await fetchPhysicalCharacteristics()

      expect(physicalCharacteristicsInfo.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('updatePhysicalCharacteristics', () => {
    it('updates taille successfully', async () => {
      vi.mocked(faceApi.updatePhysicalCharacteristics).mockResolvedValue(mockResponse)

      const { physicalCharacteristicsInfo, isSaving, error, updatePhysicalCharacteristics } =
        usePhysicalCharacteristics()

      const result = await updatePhysicalCharacteristics({ taille: 180 })

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockPhysicalCharacteristicsInfo)
      expect(result.message).toBe('Profil mis à jour avec succès')
      expect(physicalCharacteristicsInfo.value).toEqual(mockPhysicalCharacteristicsInfo)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('updates poids successfully', async () => {
      const poidsResponse: PhysicalCharacteristicsResponse = {
        data: {
          taille: null,
          poids: 75,
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updatePhysicalCharacteristics).mockResolvedValue(poidsResponse)

      const { physicalCharacteristicsInfo, updatePhysicalCharacteristics } =
        usePhysicalCharacteristics()

      const result = await updatePhysicalCharacteristics({ poids: 75 })

      expect(result.success).toBe(true)
      expect(physicalCharacteristicsInfo.value?.poids).toBe(75)
    })

    it('updates taille and poids together', async () => {
      vi.mocked(faceApi.updatePhysicalCharacteristics).mockResolvedValue(mockResponse)

      const { updatePhysicalCharacteristics } = usePhysicalCharacteristics()

      const result = await updatePhysicalCharacteristics({
        taille: 175,
        poids: 70,
      })

      expect(result.success).toBe(true)
      expect(faceApi.updatePhysicalCharacteristics).toHaveBeenCalledWith({
        taille: 175,
        poids: 70,
      })
    })

    it('sets saving state during update', async () => {
      let resolvePromise: (value: PhysicalCharacteristicsResponse) => void
      const promise = new Promise<PhysicalCharacteristicsResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.updatePhysicalCharacteristics).mockReturnValue(promise)

      const { isSaving, updatePhysicalCharacteristics } = usePhysicalCharacteristics()

      const updatePromise = updatePhysicalCharacteristics({ taille: 180 })
      expect(isSaving.value).toBe(true)

      resolvePromise!(mockResponse)
      await updatePromise

      expect(isSaving.value).toBe(false)
    })

    it('handles update error', async () => {
      vi.mocked(faceApi.updatePhysicalCharacteristics).mockRejectedValue(new Error('Update failed'))

      const { error, updatePhysicalCharacteristics } = usePhysicalCharacteristics()

      const result = await updatePhysicalCharacteristics({ taille: 180 })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('validateTaille', () => {
    it('validates taille within range', () => {
      const { validateTaille } = usePhysicalCharacteristics()

      const result = validateTaille(175)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates null taille', () => {
      const { validateTaille } = usePhysicalCharacteristics()

      const result = validateTaille(null)

      expect(result.valid).toBe(true)
    })

    it('validates taille at minimum (50)', () => {
      const { validateTaille } = usePhysicalCharacteristics()

      const result = validateTaille(50)

      expect(result.valid).toBe(true)
    })

    it('validates taille at maximum (300)', () => {
      const { validateTaille } = usePhysicalCharacteristics()

      const result = validateTaille(300)

      expect(result.valid).toBe(true)
    })

    it('rejects negative taille', () => {
      const { validateTaille } = usePhysicalCharacteristics()

      const result = validateTaille(-10)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('La valeur doit être positive')
    })

    it('rejects taille below minimum', () => {
      const { validateTaille } = usePhysicalCharacteristics()

      const result = validateTaille(49)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('La taille doit être entre 50 et 300 cm')
    })

    it('rejects taille above maximum', () => {
      const { validateTaille } = usePhysicalCharacteristics()

      const result = validateTaille(301)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('La taille doit être entre 50 et 300 cm')
    })

    it('rejects non-integer taille', () => {
      const { validateTaille } = usePhysicalCharacteristics()

      const result = validateTaille(175.5)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('La taille doit être un nombre entier')
    })

    it('rejects invalid taille before calling API', async () => {
      const { updatePhysicalCharacteristics, error } = usePhysicalCharacteristics()

      const result = await updatePhysicalCharacteristics({ taille: 49 })

      expect(result.success).toBe(false)
      expect(result.message).toBe('La taille doit être entre 50 et 300 cm')
      expect(error.value).toBe('La taille doit être entre 50 et 300 cm')
      expect(faceApi.updatePhysicalCharacteristics).not.toHaveBeenCalled()
    })

    it('rejects negative taille before calling API', async () => {
      const { updatePhysicalCharacteristics, error } = usePhysicalCharacteristics()

      const result = await updatePhysicalCharacteristics({ taille: -10 })

      expect(result.success).toBe(false)
      expect(result.message).toBe('La valeur doit être positive')
      expect(error.value).toBe('La valeur doit être positive')
      expect(faceApi.updatePhysicalCharacteristics).not.toHaveBeenCalled()
    })
  })

  describe('validatePoids', () => {
    it('validates poids within range', () => {
      const { validatePoids } = usePhysicalCharacteristics()

      const result = validatePoids(70)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates null poids', () => {
      const { validatePoids } = usePhysicalCharacteristics()

      const result = validatePoids(null)

      expect(result.valid).toBe(true)
    })

    it('validates poids at minimum (20)', () => {
      const { validatePoids } = usePhysicalCharacteristics()

      const result = validatePoids(20)

      expect(result.valid).toBe(true)
    })

    it('validates poids at maximum (500)', () => {
      const { validatePoids } = usePhysicalCharacteristics()

      const result = validatePoids(500)

      expect(result.valid).toBe(true)
    })

    it('rejects negative poids', () => {
      const { validatePoids } = usePhysicalCharacteristics()

      const result = validatePoids(-5)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('La valeur doit être positive')
    })

    it('rejects poids below minimum', () => {
      const { validatePoids } = usePhysicalCharacteristics()

      const result = validatePoids(19)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le poids doit être entre 20 et 500 kg')
    })

    it('rejects poids above maximum', () => {
      const { validatePoids } = usePhysicalCharacteristics()

      const result = validatePoids(501)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le poids doit être entre 20 et 500 kg')
    })

    it('rejects non-integer poids', () => {
      const { validatePoids } = usePhysicalCharacteristics()

      const result = validatePoids(70.5)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le poids doit être un nombre entier')
    })

    it('rejects invalid poids before calling API', async () => {
      const { updatePhysicalCharacteristics, error } = usePhysicalCharacteristics()

      const result = await updatePhysicalCharacteristics({ poids: 19 })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Le poids doit être entre 20 et 500 kg')
      expect(error.value).toBe('Le poids doit être entre 20 et 500 kg')
      expect(faceApi.updatePhysicalCharacteristics).not.toHaveBeenCalled()
    })

    it('rejects negative poids before calling API', async () => {
      const { updatePhysicalCharacteristics, error } = usePhysicalCharacteristics()

      const result = await updatePhysicalCharacteristics({ poids: -5 })

      expect(result.success).toBe(false)
      expect(result.message).toBe('La valeur doit être positive')
      expect(error.value).toBe('La valeur doit être positive')
      expect(faceApi.updatePhysicalCharacteristics).not.toHaveBeenCalled()
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(faceApi.updatePhysicalCharacteristics).mockRejectedValue(new Error('Error'))

      const { error, updatePhysicalCharacteristics, clearError } = usePhysicalCharacteristics()

      await updatePhysicalCharacteristics({ taille: 180 })
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })

  describe('clearing physical characteristics', () => {
    it('can clear taille by passing null', async () => {
      const clearedResponse: PhysicalCharacteristicsResponse = {
        data: {
          taille: null,
          poids: 70,
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updatePhysicalCharacteristics).mockResolvedValue(clearedResponse)

      const { physicalCharacteristicsInfo, updatePhysicalCharacteristics } =
        usePhysicalCharacteristics()

      const result = await updatePhysicalCharacteristics({ taille: null })

      expect(result.success).toBe(true)
      expect(physicalCharacteristicsInfo.value?.taille).toBeNull()
      expect(faceApi.updatePhysicalCharacteristics).toHaveBeenCalledWith({ taille: null })
    })

    it('can clear poids by passing null', async () => {
      const clearedResponse: PhysicalCharacteristicsResponse = {
        data: {
          taille: 175,
          poids: null,
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updatePhysicalCharacteristics).mockResolvedValue(clearedResponse)

      const { physicalCharacteristicsInfo, updatePhysicalCharacteristics } =
        usePhysicalCharacteristics()

      const result = await updatePhysicalCharacteristics({ poids: null })

      expect(result.success).toBe(true)
      expect(physicalCharacteristicsInfo.value?.poids).toBeNull()
      expect(faceApi.updatePhysicalCharacteristics).toHaveBeenCalledWith({ poids: null })
    })
  })
})
