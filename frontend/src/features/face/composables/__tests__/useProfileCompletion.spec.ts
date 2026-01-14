import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useProfileCompletion } from '../useProfileCompletion'
import { faceApi } from '../../services/faceApi'
import type { ProfileCompletionInfo, ProfileCompletionResponse } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getProfileCompletion: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('useProfileCompletion', () => {
  const mockEmptyCompletionInfo: ProfileCompletionInfo = {
    profile_completion_percentage: 0,
    profile_completion_missing: [
      { key: 'profile_photo', label: 'Ajoutez une photo de profil' },
      { key: 'presentation_video', label: 'Ajoutez une vidéo de présentation' },
      { key: 'acting_video', label: "Ajoutez une vidéo d'acting" },
      { key: 'bio', label: 'Ajoutez une bio' },
      { key: 'ville', label: 'Ajoutez votre ville' },
      { key: 'categorie', label: 'Sélectionnez votre catégorie' },
      { key: 'tarifs', label: 'Ajoutez vos tarifs' },
    ],
    profile_completion_is_complete: false,
  }

  const mockPartialCompletionInfo: ProfileCompletionInfo = {
    profile_completion_percentage: 57,
    profile_completion_missing: [
      { key: 'acting_video', label: "Ajoutez une vidéo d'acting" },
      { key: 'ville', label: 'Ajoutez votre ville' },
      { key: 'tarifs', label: 'Ajoutez vos tarifs' },
    ],
    profile_completion_is_complete: false,
  }

  const mockCompleteCompletionInfo: ProfileCompletionInfo = {
    profile_completion_percentage: 100,
    profile_completion_missing: [],
    profile_completion_is_complete: true,
  }

  const mockEmptyResponse: ProfileCompletionResponse = {
    data: mockEmptyCompletionInfo,
  }

  const mockPartialResponse: ProfileCompletionResponse = {
    data: mockPartialCompletionInfo,
  }

  const mockCompleteResponse: ProfileCompletionResponse = {
    data: mockCompleteCompletionInfo,
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { completionInfo, isLoading, error, percentage, missingItems, isComplete } =
        useProfileCompletion()

      expect(completionInfo.value).toBeNull()
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(percentage.value).toBe(0)
      expect(missingItems.value).toEqual([])
      expect(isComplete.value).toBe(false)
    })
  })

  describe('fetchCompletion', () => {
    it('fetches completion successfully for empty profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockEmptyResponse)

      const { completionInfo, isLoading, error, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(completionInfo.value).toEqual(mockEmptyCompletionInfo)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getProfileCompletion).toHaveBeenCalledOnce()
    })

    it('fetches completion successfully for partial profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockPartialResponse)

      const { completionInfo, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(completionInfo.value).toEqual(mockPartialCompletionInfo)
    })

    it('fetches completion successfully for complete profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockCompleteResponse)

      const { completionInfo, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(completionInfo.value).toEqual(mockCompleteCompletionInfo)
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: ProfileCompletionResponse) => void
      const promise = new Promise<ProfileCompletionResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getProfileCompletion).mockReturnValue(promise)

      const { isLoading, fetchCompletion } = useProfileCompletion()

      const fetchPromise = fetchCompletion()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockEmptyResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockRejectedValue(new Error('Network error'))

      const { completionInfo, error, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(completionInfo.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('computed properties', () => {
    it('percentage returns correct value for empty profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockEmptyResponse)

      const { percentage, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(percentage.value).toBe(0)
    })

    it('percentage returns correct value for partial profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockPartialResponse)

      const { percentage, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(percentage.value).toBe(57)
    })

    it('percentage returns correct value for complete profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockCompleteResponse)

      const { percentage, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(percentage.value).toBe(100)
    })

    it('missingItems returns correct array for empty profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockEmptyResponse)

      const { missingItems, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(missingItems.value).toHaveLength(7)
      expect(missingItems.value[0].key).toBe('profile_photo')
    })

    it('missingItems returns correct array for partial profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockPartialResponse)

      const { missingItems, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(missingItems.value).toHaveLength(3)
    })

    it('missingItems returns empty array for complete profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockCompleteResponse)

      const { missingItems, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(missingItems.value).toHaveLength(0)
    })

    it('isComplete returns false for incomplete profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockPartialResponse)

      const { isComplete, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(isComplete.value).toBe(false)
    })

    it('isComplete returns true for complete profile', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockResolvedValue(mockCompleteResponse)

      const { isComplete, fetchCompletion } = useProfileCompletion()

      await fetchCompletion()

      expect(isComplete.value).toBe(true)
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(faceApi.getProfileCompletion).mockRejectedValue(new Error('Error'))

      const { error, fetchCompletion, clearError } = useProfileCompletion()

      await fetchCompletion()
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })
})
