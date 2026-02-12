import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useProducerBasicInfo } from '../useProducerBasicInfo'
import { producerApi } from '../../services/producerApi'
import type {
  ProducerBasicInfoResponse,
  ProducerBasicInfoAgency,
  ProducerBasicInfoParticulier,
} from '../../types'

// Mock the producerApi module
vi.mock('../../services/producerApi', () => ({
  producerApi: {
    getBasicInfo: vi.fn(),
    updateBasicInfo: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('useProducerBasicInfo', () => {
  const mockAgencyInfo: ProducerBasicInfoAgency = {
    type: 'agency',
    agency_name: 'Production ABC',
  }

  const mockParticulierInfo: ProducerBasicInfoParticulier = {
    type: 'particulier',
    first_name: 'Jean',
    last_name: 'Dupont',
  }

  const mockAgencyResponse: ProducerBasicInfoResponse = {
    data: mockAgencyInfo,
    message: 'Informations mises à jour avec succès',
  }

  const mockParticulierResponse: ProducerBasicInfoResponse = {
    data: mockParticulierInfo,
    message: 'Informations mises à jour avec succès',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { basicInfo, isLoading, isSaving, error } = useProducerBasicInfo()

      expect(basicInfo.value).toBeNull()
      expect(isLoading.value).toBe(false)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })
  })

  describe('fetchBasicInfo', () => {
    it('fetches agency basic info successfully', async () => {
      vi.mocked(producerApi.getBasicInfo).mockResolvedValue(mockAgencyResponse)

      const { basicInfo, isLoading, error, fetchBasicInfo } = useProducerBasicInfo()

      await fetchBasicInfo()

      expect(basicInfo.value).toEqual(mockAgencyInfo)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(producerApi.getBasicInfo).toHaveBeenCalledOnce()
    })

    it('fetches particulier basic info successfully', async () => {
      vi.mocked(producerApi.getBasicInfo).mockResolvedValue(mockParticulierResponse)

      const { basicInfo, isLoading, error, fetchBasicInfo } = useProducerBasicInfo()

      await fetchBasicInfo()

      expect(basicInfo.value).toEqual(mockParticulierInfo)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: ProducerBasicInfoResponse) => void
      const promise = new Promise<ProducerBasicInfoResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(producerApi.getBasicInfo).mockReturnValue(promise)

      const { isLoading, fetchBasicInfo } = useProducerBasicInfo()

      const fetchPromise = fetchBasicInfo()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockAgencyResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(producerApi.getBasicInfo).mockRejectedValue(new Error('Network error'))

      const { basicInfo, error, fetchBasicInfo } = useProducerBasicInfo()

      await fetchBasicInfo()

      expect(basicInfo.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('updateBasicInfo - Agency', () => {
    it('updates agency_name successfully', async () => {
      vi.mocked(producerApi.updateBasicInfo).mockResolvedValue(mockAgencyResponse)

      const { basicInfo, isSaving, error, updateBasicInfo } = useProducerBasicInfo()

      const result = await updateBasicInfo({ agency_name: 'Production ABC' })

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockAgencyInfo)
      expect(result.message).toBe('Informations mises à jour avec succès')
      expect(basicInfo.value).toEqual(mockAgencyInfo)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('passes agency_name to API correctly', async () => {
      vi.mocked(producerApi.updateBasicInfo).mockResolvedValue(mockAgencyResponse)

      const { updateBasicInfo } = useProducerBasicInfo()

      await updateBasicInfo({ agency_name: 'New Agency Name' })

      expect(producerApi.updateBasicInfo).toHaveBeenCalledWith({
        agency_name: 'New Agency Name',
      })
    })
  })

  describe('updateBasicInfo - Particulier', () => {
    it('updates first_name and last_name successfully', async () => {
      vi.mocked(producerApi.updateBasicInfo).mockResolvedValue(mockParticulierResponse)

      const { basicInfo, isSaving, error, updateBasicInfo } = useProducerBasicInfo()

      const result = await updateBasicInfo({
        first_name: 'Jean',
        last_name: 'Dupont',
      })

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockParticulierInfo)
      expect(result.message).toBe('Informations mises à jour avec succès')
      expect(basicInfo.value).toEqual(mockParticulierInfo)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('updates only first_name successfully', async () => {
      const firstNameOnlyResponse: ProducerBasicInfoResponse = {
        data: {
          type: 'particulier',
          first_name: 'Marie',
          last_name: 'Dupont',
        },
        message: 'Informations mises à jour avec succès',
      }
      vi.mocked(producerApi.updateBasicInfo).mockResolvedValue(firstNameOnlyResponse)

      const { basicInfo, updateBasicInfo } = useProducerBasicInfo()

      const result = await updateBasicInfo({ first_name: 'Marie' })

      expect(result.success).toBe(true)
      expect((basicInfo.value as ProducerBasicInfoParticulier)?.first_name).toBe('Marie')
    })

    it('updates only last_name successfully', async () => {
      const lastNameOnlyResponse: ProducerBasicInfoResponse = {
        data: {
          type: 'particulier',
          first_name: 'Jean',
          last_name: 'Martin',
        },
        message: 'Informations mises à jour avec succès',
      }
      vi.mocked(producerApi.updateBasicInfo).mockResolvedValue(lastNameOnlyResponse)

      const { basicInfo, updateBasicInfo } = useProducerBasicInfo()

      const result = await updateBasicInfo({ last_name: 'Martin' })

      expect(result.success).toBe(true)
      expect((basicInfo.value as ProducerBasicInfoParticulier)?.last_name).toBe('Martin')
    })

    it('passes particulier data to API correctly', async () => {
      vi.mocked(producerApi.updateBasicInfo).mockResolvedValue(mockParticulierResponse)

      const { updateBasicInfo } = useProducerBasicInfo()

      await updateBasicInfo({
        first_name: 'Nouveau Prénom',
        last_name: 'Nouveau Nom',
      })

      expect(producerApi.updateBasicInfo).toHaveBeenCalledWith({
        first_name: 'Nouveau Prénom',
        last_name: 'Nouveau Nom',
      })
    })
  })

  describe('updateBasicInfo - common behavior', () => {
    it('sets saving state during update', async () => {
      let resolvePromise: (value: ProducerBasicInfoResponse) => void
      const promise = new Promise<ProducerBasicInfoResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(producerApi.updateBasicInfo).mockReturnValue(promise)

      const { isSaving, updateBasicInfo } = useProducerBasicInfo()

      const updatePromise = updateBasicInfo({ agency_name: 'Test' })
      expect(isSaving.value).toBe(true)

      resolvePromise!(mockAgencyResponse)
      await updatePromise

      expect(isSaving.value).toBe(false)
    })

    it('handles update error', async () => {
      vi.mocked(producerApi.updateBasicInfo).mockRejectedValue(new Error('Update failed'))

      const { error, updateBasicInfo } = useProducerBasicInfo()

      const result = await updateBasicInfo({ agency_name: 'Test' })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(producerApi.updateBasicInfo).mockRejectedValue(new Error('Error'))

      const { error, updateBasicInfo, clearError } = useProducerBasicInfo()

      await updateBasicInfo({ agency_name: 'Test' })
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })
})
