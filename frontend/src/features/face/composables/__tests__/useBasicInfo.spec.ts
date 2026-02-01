import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useBasicInfo } from '../useBasicInfo'
import { faceApi } from '../../services/faceApi'
import type { BasicInfo, BasicInfoResponse } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getBasicInfo: vi.fn(),
    updateBasicInfo: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('useBasicInfo', () => {
  const mockBasicInfo: BasicInfo = {
    nom: 'Dupont',
    prenom: 'Jean',
    username: 'jeandupont',
  }

  const mockResponse: BasicInfoResponse = {
    data: mockBasicInfo,
    message: 'Informations mises à jour avec succès',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { basicInfo, isLoading, isSaving, error } = useBasicInfo()

      expect(basicInfo.value).toBeNull()
      expect(isLoading.value).toBe(false)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })
  })

  describe('fetchBasicInfo', () => {
    it('fetches basic info successfully', async () => {
      vi.mocked(faceApi.getBasicInfo).mockResolvedValue(mockResponse)

      const { basicInfo, isLoading, error, fetchBasicInfo } = useBasicInfo()

      await fetchBasicInfo()

      expect(basicInfo.value).toEqual(mockBasicInfo)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getBasicInfo).toHaveBeenCalledOnce()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: BasicInfoResponse) => void
      const promise = new Promise<BasicInfoResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getBasicInfo).mockReturnValue(promise)

      const { isLoading, fetchBasicInfo } = useBasicInfo()

      const fetchPromise = fetchBasicInfo()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getBasicInfo).mockRejectedValue(new Error('Network error'))

      const { basicInfo, error, fetchBasicInfo } = useBasicInfo()

      await fetchBasicInfo()

      expect(basicInfo.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('updateBasicInfo', () => {
    it('updates basic info successfully', async () => {
      vi.mocked(faceApi.updateBasicInfo).mockResolvedValue(mockResponse)

      const { basicInfo, isSaving, error, updateBasicInfo } = useBasicInfo()

      const result = await updateBasicInfo({
        nom: 'Dupont',
        prenom: 'Jean',
        username: 'jeandupont',
      })

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockBasicInfo)
      expect(result.message).toBe('Informations mises à jour avec succès')
      expect(basicInfo.value).toEqual(mockBasicInfo)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('updates only nom successfully', async () => {
      const nomOnlyResponse: BasicInfoResponse = {
        data: {
          nom: 'Martin',
          prenom: 'Jean',
          username: 'jeandupont',
        },
        message: 'Informations mises à jour avec succès',
      }
      vi.mocked(faceApi.updateBasicInfo).mockResolvedValue(nomOnlyResponse)

      const { basicInfo, updateBasicInfo } = useBasicInfo()

      const result = await updateBasicInfo({ nom: 'Martin' })

      expect(result.success).toBe(true)
      expect(basicInfo.value?.nom).toBe('Martin')
    })

    it('updates only prenom successfully', async () => {
      const prenomOnlyResponse: BasicInfoResponse = {
        data: {
          nom: 'Dupont',
          prenom: 'Marie',
          username: 'jeandupont',
        },
        message: 'Informations mises à jour avec succès',
      }
      vi.mocked(faceApi.updateBasicInfo).mockResolvedValue(prenomOnlyResponse)

      const { basicInfo, updateBasicInfo } = useBasicInfo()

      const result = await updateBasicInfo({ prenom: 'Marie' })

      expect(result.success).toBe(true)
      expect(basicInfo.value?.prenom).toBe('Marie')
    })

    it('updates only username successfully', async () => {
      const usernameOnlyResponse: BasicInfoResponse = {
        data: {
          nom: 'Dupont',
          prenom: 'Jean',
          username: 'newusername',
        },
        message: 'Informations mises à jour avec succès',
      }
      vi.mocked(faceApi.updateBasicInfo).mockResolvedValue(usernameOnlyResponse)

      const { basicInfo, updateBasicInfo } = useBasicInfo()

      const result = await updateBasicInfo({ username: 'newusername' })

      expect(result.success).toBe(true)
      expect(basicInfo.value?.username).toBe('newusername')
    })

    it('sets saving state during update', async () => {
      let resolvePromise: (value: BasicInfoResponse) => void
      const promise = new Promise<BasicInfoResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.updateBasicInfo).mockReturnValue(promise)

      const { isSaving, updateBasicInfo } = useBasicInfo()

      const updatePromise = updateBasicInfo({ nom: 'Test' })
      expect(isSaving.value).toBe(true)

      resolvePromise!(mockResponse)
      await updatePromise

      expect(isSaving.value).toBe(false)
    })

    it('handles update error', async () => {
      vi.mocked(faceApi.updateBasicInfo).mockRejectedValue(new Error('Update failed'))

      const { error, updateBasicInfo } = useBasicInfo()

      const result = await updateBasicInfo({ nom: 'Test' })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })

    it('passes correct data to API', async () => {
      vi.mocked(faceApi.updateBasicInfo).mockResolvedValue(mockResponse)

      const { updateBasicInfo } = useBasicInfo()

      await updateBasicInfo({
        nom: 'Nouveau Nom',
        prenom: 'Nouveau Prénom',
        username: 'nouveauusername',
      })

      expect(faceApi.updateBasicInfo).toHaveBeenCalledWith({
        nom: 'Nouveau Nom',
        prenom: 'Nouveau Prénom',
        username: 'nouveauusername',
      })
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(faceApi.updateBasicInfo).mockRejectedValue(new Error('Error'))

      const { error, updateBasicInfo, clearError } = useBasicInfo()

      await updateBasicInfo({ nom: 'Test' })
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })
})
