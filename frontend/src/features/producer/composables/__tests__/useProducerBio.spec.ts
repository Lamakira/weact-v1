import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { useProducerBio } from '../useProducerBio'
import { producerApi } from '../../services/producerApi'

// Mock the producer API
vi.mock('../../services/producerApi', () => ({
  producerApi: {
    getBio: vi.fn(),
    updateBio: vi.fn(),
  },
}))

describe('useProducerBio', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('should have null bio initially', () => {
      const { bio } = useProducerBio()
      expect(bio.value).toBeNull()
    })

    it('should not be loading initially', () => {
      const { isLoading } = useProducerBio()
      expect(isLoading.value).toBe(false)
    })

    it('should not be saving initially', () => {
      const { isSaving } = useProducerBio()
      expect(isSaving.value).toBe(false)
    })

    it('should have no error initially', () => {
      const { error } = useProducerBio()
      expect(error.value).toBeNull()
    })

    it('should have charCount of 0 initially', () => {
      const { charCount } = useProducerBio()
      expect(charCount.value).toBe(0)
    })

    it('should not be over limit initially', () => {
      const { isOverLimit } = useProducerBio()
      expect(isOverLimit.value).toBe(false)
    })
  })

  describe('fetchBio', () => {
    it('should fetch bio successfully', async () => {
      vi.mocked(producerApi.getBio).mockResolvedValue({
        data: { bio: 'Test bio content' },
      })

      const { bio, fetchBio, isLoading } = useProducerBio()

      const promise = fetchBio()
      expect(isLoading.value).toBe(true)

      const result = await promise
      await flushPromises()

      expect(isLoading.value).toBe(false)
      expect(bio.value).toBe('Test bio content')
      expect(result.success).toBe(true)
    })

    it('should handle fetch error', async () => {
      vi.mocked(producerApi.getBio).mockRejectedValue(new Error('Network error'))

      const { bio, fetchBio, error } = useProducerBio()

      const result = await fetchBio()
      await flushPromises()

      expect(bio.value).toBeNull()
      expect(error.value).toBe('Erreur lors du chargement de la bio')
      expect(result.success).toBe(false)
    })

    it('should handle null bio from API', async () => {
      vi.mocked(producerApi.getBio).mockResolvedValue({
        data: { bio: null },
      })

      const { bio, fetchBio } = useProducerBio()

      await fetchBio()
      await flushPromises()

      expect(bio.value).toBeNull()
    })
  })

  describe('saveBio', () => {
    it('should save bio successfully', async () => {
      vi.mocked(producerApi.updateBio).mockResolvedValue({
        data: { bio: 'Updated bio' },
        message: 'Bio mise à jour avec succès',
      })

      const { bio, saveBio, isSaving } = useProducerBio()

      const promise = saveBio('Updated bio')
      expect(isSaving.value).toBe(true)

      const result = await promise
      await flushPromises()

      expect(isSaving.value).toBe(false)
      expect(bio.value).toBe('Updated bio')
      expect(result.success).toBe(true)
      expect(result.message).toBe('Bio mise à jour avec succès')
    })

    it('should clear bio when saving null', async () => {
      vi.mocked(producerApi.updateBio).mockResolvedValue({
        data: { bio: null },
        message: 'Bio mise à jour avec succès',
      })

      const { bio, saveBio } = useProducerBio()

      const result = await saveBio(null)
      await flushPromises()

      expect(bio.value).toBeNull()
      expect(result.success).toBe(true)
    })

    it('should reject bio over 500 characters without API call', async () => {
      const { saveBio, error } = useProducerBio()

      const longBio = 'a'.repeat(501)
      const result = await saveBio(longBio)

      expect(producerApi.updateBio).not.toHaveBeenCalled()
      expect(result.success).toBe(false)
      expect(error.value).toBe('La bio ne peut pas dépasser 500 caractères')
    })

    it('should handle validation error from API', async () => {
      const axiosError = {
        isAxiosError: true,
        response: {
          status: 422,
          data: {
            errors: {
              bio: ['La bio ne peut pas dépasser 500 caractères'],
            },
          },
        },
      }

      vi.mocked(producerApi.updateBio).mockRejectedValue(axiosError)

      // Mock isAxiosError
      vi.mock('axios', () => ({
        isAxiosError: (error: unknown) => {
          return typeof error === 'object' && error !== null && 'isAxiosError' in error
        },
      }))

      const { saveBio, error } = useProducerBio()

      const result = await saveBio('Test bio')
      await flushPromises()

      expect(result.success).toBe(false)
      expect(error.value).toBe('La bio ne peut pas dépasser 500 caractères')
    })

    it('should handle network error', async () => {
      vi.mocked(producerApi.updateBio).mockRejectedValue(new Error('Network error'))

      const { saveBio, error } = useProducerBio()

      const result = await saveBio('Test bio')
      await flushPromises()

      expect(result.success).toBe(false)
      expect(error.value).toBe('Erreur lors de la mise à jour de la bio')
    })
  })

  describe('computed properties', () => {
    it('should update charCount when bio changes', async () => {
      vi.mocked(producerApi.getBio).mockResolvedValue({
        data: { bio: 'Hello World' },
      })

      const { bio, charCount, fetchBio } = useProducerBio()

      await fetchBio()
      await flushPromises()

      expect(charCount.value).toBe(11)
    })

    it('should detect when bio is near limit', async () => {
      vi.mocked(producerApi.getBio).mockResolvedValue({
        data: { bio: 'a'.repeat(460) },
      })

      const { isNearLimit, fetchBio } = useProducerBio()

      await fetchBio()
      await flushPromises()

      expect(isNearLimit.value).toBe(true)
    })

    it('should detect when bio is over limit', async () => {
      vi.mocked(producerApi.getBio).mockResolvedValue({
        data: { bio: 'a'.repeat(510) },
      })

      const { isOverLimit, fetchBio } = useProducerBio()

      await fetchBio()
      await flushPromises()

      expect(isOverLimit.value).toBe(true)
    })

    it('should calculate remaining characters', async () => {
      vi.mocked(producerApi.getBio).mockResolvedValue({
        data: { bio: 'a'.repeat(100) },
      })

      const { remainingChars, fetchBio } = useProducerBio()

      await fetchBio()
      await flushPromises()

      expect(remainingChars.value).toBe(400)
    })
  })

  describe('clearError', () => {
    it('should clear error message', async () => {
      vi.mocked(producerApi.getBio).mockRejectedValue(new Error('Test error'))

      const { error, fetchBio, clearError } = useProducerBio()

      await fetchBio()
      await flushPromises()

      expect(error.value).not.toBeNull()

      clearError()

      expect(error.value).toBeNull()
    })
  })
})
