import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useAvailability } from '../useAvailability'
import { faceApi } from '../../services/faceApi'
import type { AvailabilityInfo, AvailabilityResponse } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getAvailability: vi.fn(),
    updateAvailability: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('useAvailability', () => {
  const mockAvailableInfo: AvailabilityInfo = {
    is_available: true,
    availability_badge: 'Disponible',
    availability_badge_color: 'green',
  }

  const mockUnavailableInfo: AvailabilityInfo = {
    is_available: false,
    availability_badge: 'Indisponible',
    availability_badge_color: 'grey',
  }

  const mockAvailableResponse: AvailabilityResponse = {
    data: mockAvailableInfo,
    message: 'Disponibilité mise à jour avec succès',
  }

  const mockUnavailableResponse: AvailabilityResponse = {
    data: mockUnavailableInfo,
    message: 'Disponibilité mise à jour avec succès',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { availabilityInfo, isLoading, isSaving, error } = useAvailability()

      expect(availabilityInfo.value).toBeNull()
      expect(isLoading.value).toBe(false)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })
  })

  describe('fetchAvailability', () => {
    it('fetches availability successfully', async () => {
      vi.mocked(faceApi.getAvailability).mockResolvedValue(mockAvailableResponse)

      const { availabilityInfo, isLoading, error, fetchAvailability } = useAvailability()

      await fetchAvailability()

      expect(availabilityInfo.value).toEqual(mockAvailableInfo)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getAvailability).toHaveBeenCalledOnce()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: AvailabilityResponse) => void
      const promise = new Promise<AvailabilityResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getAvailability).mockReturnValue(promise)

      const { isLoading, fetchAvailability } = useAvailability()

      const fetchPromise = fetchAvailability()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockAvailableResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getAvailability).mockRejectedValue(new Error('Network error'))

      const { availabilityInfo, error, fetchAvailability } = useAvailability()

      await fetchAvailability()

      expect(availabilityInfo.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('updateAvailability', () => {
    it('updates availability to false successfully', async () => {
      vi.mocked(faceApi.updateAvailability).mockResolvedValue(mockUnavailableResponse)

      const { availabilityInfo, isSaving, error, updateAvailability } = useAvailability()

      const result = await updateAvailability({ is_available: false })

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockUnavailableInfo)
      expect(result.message).toBe('Disponibilité mise à jour avec succès')
      expect(availabilityInfo.value).toEqual(mockUnavailableInfo)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('updates availability to true successfully', async () => {
      vi.mocked(faceApi.updateAvailability).mockResolvedValue(mockAvailableResponse)

      const { availabilityInfo, updateAvailability } = useAvailability()

      const result = await updateAvailability({ is_available: true })

      expect(result.success).toBe(true)
      expect(availabilityInfo.value?.is_available).toBe(true)
      expect(availabilityInfo.value?.availability_badge).toBe('Disponible')
      expect(availabilityInfo.value?.availability_badge_color).toBe('green')
    })

    it('sets saving state during update', async () => {
      let resolvePromise: (value: AvailabilityResponse) => void
      const promise = new Promise<AvailabilityResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.updateAvailability).mockReturnValue(promise)

      const { isSaving, updateAvailability } = useAvailability()

      const updatePromise = updateAvailability({ is_available: false })
      expect(isSaving.value).toBe(true)

      resolvePromise!(mockUnavailableResponse)
      await updatePromise

      expect(isSaving.value).toBe(false)
    })

    it('handles update error', async () => {
      vi.mocked(faceApi.updateAvailability).mockRejectedValue(new Error('Update failed'))

      const { error, updateAvailability } = useAvailability()

      const result = await updateAvailability({ is_available: false })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })

    it('performs optimistic update', async () => {
      // Set up initial state
      vi.mocked(faceApi.getAvailability).mockResolvedValue(mockAvailableResponse)

      const { availabilityInfo, fetchAvailability, updateAvailability } = useAvailability()
      await fetchAvailability()

      expect(availabilityInfo.value?.is_available).toBe(true)

      // Create a promise that we can control
      let resolvePromise: (value: AvailabilityResponse) => void
      const promise = new Promise<AvailabilityResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.updateAvailability).mockReturnValue(promise)

      // Start the update
      const updatePromise = updateAvailability({ is_available: false })

      // Check optimistic update happened immediately
      expect(availabilityInfo.value?.is_available).toBe(false)
      expect(availabilityInfo.value?.availability_badge).toBe('Indisponible')
      expect(availabilityInfo.value?.availability_badge_color).toBe('grey')

      // Resolve the promise
      resolvePromise!(mockUnavailableResponse)
      await updatePromise
    })

    it('rolls back optimistic update on error', async () => {
      // Set up initial state
      vi.mocked(faceApi.getAvailability).mockResolvedValue(mockAvailableResponse)

      const { availabilityInfo, fetchAvailability, updateAvailability } = useAvailability()
      await fetchAvailability()

      expect(availabilityInfo.value?.is_available).toBe(true)

      // Mock the API to fail
      vi.mocked(faceApi.updateAvailability).mockRejectedValue(new Error('Network error'))

      // Attempt the update
      await updateAvailability({ is_available: false })

      // Check that it rolled back to the original state
      expect(availabilityInfo.value?.is_available).toBe(true)
      expect(availabilityInfo.value?.availability_badge).toBe('Disponible')
      expect(availabilityInfo.value?.availability_badge_color).toBe('green')
    })
  })

  describe('toggleAvailability', () => {
    it('toggles from available to unavailable', async () => {
      // Set up initial state as available
      vi.mocked(faceApi.getAvailability).mockResolvedValue(mockAvailableResponse)
      vi.mocked(faceApi.updateAvailability).mockResolvedValue(mockUnavailableResponse)

      const { availabilityInfo, fetchAvailability, toggleAvailability } = useAvailability()
      await fetchAvailability()

      expect(availabilityInfo.value?.is_available).toBe(true)

      const result = await toggleAvailability()

      expect(result.success).toBe(true)
      expect(faceApi.updateAvailability).toHaveBeenCalledWith({ is_available: false })
    })

    it('toggles from unavailable to available', async () => {
      // Set up initial state as unavailable
      vi.mocked(faceApi.getAvailability).mockResolvedValue(mockUnavailableResponse)
      vi.mocked(faceApi.updateAvailability).mockResolvedValue(mockAvailableResponse)

      const { availabilityInfo, fetchAvailability, toggleAvailability } = useAvailability()
      await fetchAvailability()

      expect(availabilityInfo.value?.is_available).toBe(false)

      const result = await toggleAvailability()

      expect(result.success).toBe(true)
      expect(faceApi.updateAvailability).toHaveBeenCalledWith({ is_available: true })
    })

    it('defaults to toggling to false when no initial state', async () => {
      vi.mocked(faceApi.updateAvailability).mockResolvedValue(mockUnavailableResponse)

      const { toggleAvailability } = useAvailability()

      await toggleAvailability()

      // Default is true, so toggle should set to false
      expect(faceApi.updateAvailability).toHaveBeenCalledWith({ is_available: false })
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(faceApi.updateAvailability).mockRejectedValue(new Error('Error'))

      const { error, updateAvailability, clearError } = useAvailability()

      await updateAvailability({ is_available: false })
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })
})
