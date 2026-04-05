import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useBioLocation } from '../useBioLocation'
import { faceApi } from '../../services/faceApi'
import type { BioLocationInfo, BioLocationResponse } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getBioLocation: vi.fn(),
    updateBioLocation: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('useBioLocation', () => {
  const mockBioLocationInfo: BioLocationInfo = {
    bio: 'Je suis acteur professionnel',
    ville: 'Cotonou',
    pays: 'Bénin',
    formatted_location: 'Cotonou, Bénin',
  }

  const mockResponse: BioLocationResponse = {
    data: mockBioLocationInfo,
    message: 'Profil mis à jour avec succès',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { bioLocationInfo, isLoading, isSaving, error, bioCharactersRemaining } =
        useBioLocation()

      expect(bioLocationInfo.value).toBeNull()
      expect(isLoading.value).toBe(false)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
      expect(bioCharactersRemaining.value).toBe(500)
    })
  })

  describe('fetchBioLocation', () => {
    it('fetches bio/location info successfully', async () => {
      vi.mocked(faceApi.getBioLocation).mockResolvedValue(mockResponse)

      const { bioLocationInfo, isLoading, error, fetchBioLocation } = useBioLocation()

      await fetchBioLocation()

      expect(bioLocationInfo.value).toEqual(mockBioLocationInfo)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getBioLocation).toHaveBeenCalledOnce()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: BioLocationResponse) => void
      const promise = new Promise<BioLocationResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getBioLocation).mockReturnValue(promise)

      const { isLoading, fetchBioLocation } = useBioLocation()

      const fetchPromise = fetchBioLocation()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getBioLocation).mockRejectedValue(new Error('Network error'))

      const { bioLocationInfo, error, fetchBioLocation } = useBioLocation()

      await fetchBioLocation()

      expect(bioLocationInfo.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('updateBioLocation', () => {
    it('updates bio successfully', async () => {
      vi.mocked(faceApi.updateBioLocation).mockResolvedValue(mockResponse)

      const { bioLocationInfo, isSaving, error, updateBioLocation } = useBioLocation()

      const result = await updateBioLocation({ bio: 'Nouvelle bio' })

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockBioLocationInfo)
      expect(result.message).toBe('Profil mis à jour avec succès')
      expect(bioLocationInfo.value).toEqual(mockBioLocationInfo)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('updates location successfully', async () => {
      const locationResponse: BioLocationResponse = {
        data: {
          bio: null,
          ville: 'Porto-Novo',
          pays: 'Bénin',
          formatted_location: 'Porto-Novo, Bénin',
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updateBioLocation).mockResolvedValue(locationResponse)

      const { bioLocationInfo, updateBioLocation } = useBioLocation()

      const result = await updateBioLocation({
        ville: 'Porto-Novo',
        pays: 'Bénin',
      })

      expect(result.success).toBe(true)
      expect(bioLocationInfo.value?.ville).toBe('Porto-Novo')
      expect(bioLocationInfo.value?.formatted_location).toBe('Porto-Novo, Bénin')
    })

    it('updates bio and location together', async () => {
      vi.mocked(faceApi.updateBioLocation).mockResolvedValue(mockResponse)

      const { updateBioLocation } = useBioLocation()

      const result = await updateBioLocation({
        bio: 'Ma nouvelle bio',
        ville: 'Cotonou',
        pays: 'Bénin',
      })

      expect(result.success).toBe(true)
      expect(faceApi.updateBioLocation).toHaveBeenCalledWith({
        bio: 'Ma nouvelle bio',
        ville: 'Cotonou',
        pays: 'Bénin',
      })
    })

    it('sets saving state during update', async () => {
      let resolvePromise: (value: BioLocationResponse) => void
      const promise = new Promise<BioLocationResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.updateBioLocation).mockReturnValue(promise)

      const { isSaving, updateBioLocation } = useBioLocation()

      const updatePromise = updateBioLocation({ bio: 'Test' })
      expect(isSaving.value).toBe(true)

      resolvePromise!(mockResponse)
      await updatePromise

      expect(isSaving.value).toBe(false)
    })

    it('handles update error', async () => {
      vi.mocked(faceApi.updateBioLocation).mockRejectedValue(new Error('Update failed'))

      const { error, updateBioLocation } = useBioLocation()

      const result = await updateBioLocation({ bio: 'Test' })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('validateBio', () => {
    it('validates bio within limit', () => {
      const { validateBio } = useBioLocation()

      const result = validateBio('Une bio valide')

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates null bio', () => {
      const { validateBio } = useBioLocation()

      const result = validateBio(null)

      expect(result.valid).toBe(true)
    })

    it('validates bio at exactly 500 characters', () => {
      const { validateBio } = useBioLocation()
      const exactBio = 'a'.repeat(500)

      const result = validateBio(exactBio)

      expect(result.valid).toBe(true)
    })

    it('rejects bio exceeding 500 characters', () => {
      const { validateBio } = useBioLocation()
      const longBio = 'a'.repeat(501)

      const result = validateBio(longBio)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('La bio ne peut pas dépasser 500 caractères')
    })

    it('rejects bio before calling API', async () => {
      const { updateBioLocation, error } = useBioLocation()
      const longBio = 'a'.repeat(501)

      const result = await updateBioLocation({ bio: longBio })

      expect(result.success).toBe(false)
      expect(result.message).toBe('La bio ne peut pas dépasser 500 caractères')
      expect(error.value).toBe('La bio ne peut pas dépasser 500 caractères')
      expect(faceApi.updateBioLocation).not.toHaveBeenCalled()
    })
  })

  describe('validateLocation', () => {
    it('validates a Benin city when pays is Bénin', () => {
      const { validateLocation } = useBioLocation()

      const result = validateLocation({ ville: 'Cotonou', pays: 'Bénin' })

      expect(result.valid).toBe(true)
    })

    it('validates empty city for a non-Benin country', () => {
      const { validateLocation } = useBioLocation()

      const result = validateLocation({ ville: null, pays: 'Togo' })

      expect(result.valid).toBe(true)
    })

    it('rejects a city outside the official list', () => {
      const { validateLocation } = useBioLocation()

      const result = validateLocation({ ville: 'Ab-Calavi', pays: 'Bénin' })

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Veuillez sélectionner une ville du Bénin valide')
    })

    it('rejects a city when pays is not Bénin before calling API', async () => {
      const { updateBioLocation, error } = useBioLocation()

      const result = await updateBioLocation({ ville: 'Cotonou', pays: 'Togo' })

      expect(result.success).toBe(false)
      expect(result.message).toBe('La ville doit être vide lorsque le pays n\'est pas "Bénin".')
      expect(error.value).toBe('La ville doit être vide lorsque le pays n\'est pas "Bénin".')
      expect(faceApi.updateBioLocation).not.toHaveBeenCalled()
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(faceApi.updateBioLocation).mockRejectedValue(new Error('Error'))

      const { error, updateBioLocation, clearError } = useBioLocation()

      await updateBioLocation({ bio: 'Test' })
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })

  describe('computed properties', () => {
    it('bioCharactersRemaining calculates correctly', async () => {
      const bioInfo: BioLocationInfo = {
        bio: 'Test bio', // 8 characters
        ville: null,
        pays: null,
        formatted_location: null,
      }
      vi.mocked(faceApi.getBioLocation).mockResolvedValue({ data: bioInfo })

      const { bioCharactersRemaining, fetchBioLocation } = useBioLocation()

      await fetchBioLocation()

      expect(bioCharactersRemaining.value).toBe(492) // 500 - 8
    })

    it('bioCharactersRemaining is 0 when exactly at limit', async () => {
      const bioInfo: BioLocationInfo = {
        bio: 'a'.repeat(500), // 500 characters, 0 remaining
        ville: null,
        pays: null,
        formatted_location: null,
      }
      vi.mocked(faceApi.getBioLocation).mockResolvedValue({ data: bioInfo })

      const { bioCharactersRemaining, fetchBioLocation } = useBioLocation()

      await fetchBioLocation()

      expect(bioCharactersRemaining.value).toBe(0)
    })
  })

  describe('clearing bio', () => {
    it('can clear bio by passing null', async () => {
      const clearedResponse: BioLocationResponse = {
        data: {
          bio: null,
          ville: 'Cotonou',
          pays: 'Bénin',
          formatted_location: 'Cotonou, Bénin',
        },
        message: 'Profil mis à jour avec succès',
      }
      vi.mocked(faceApi.updateBioLocation).mockResolvedValue(clearedResponse)

      const { bioLocationInfo, updateBioLocation } = useBioLocation()

      const result = await updateBioLocation({ bio: null })

      expect(result.success).toBe(true)
      expect(bioLocationInfo.value?.bio).toBeNull()
      expect(faceApi.updateBioLocation).toHaveBeenCalledWith({ bio: null })
    })
  })
})
