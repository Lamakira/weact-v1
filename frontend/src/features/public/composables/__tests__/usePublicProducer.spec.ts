import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { usePublicProducer } from '../usePublicProducer'
import { publicApi } from '../../services/publicApi'
import type { PublicProducer } from '../../types'

// Mock the public API
vi.mock('../../services/publicApi', () => ({
  publicApi: {
    getProducer: vi.fn(),
  },
}))

const mockParticulierProducer: PublicProducer = {
  id: 1,
  type: 'particulier',
  display_name: 'Jean Dupont',
  agency_name: null,
  bio: 'Test bio content',
  profile_photo_url: 'https://example.com/photo.jpg',
  thumbnail_url: 'https://example.com/thumb.jpg',
  agency_logo_url: null,
  agency_logo_thumbnail_url: null,
  missions_count: 0,
  average_rating: null,
  ratings_count: 0,
  member_since: 'janvier 2026',
}

const mockAgencyProducer: PublicProducer = {
  id: 2,
  type: 'agency',
  display_name: 'Test Agency',
  agency_name: 'Test Agency',
  bio: 'We are a creative agency',
  profile_photo_url: 'https://example.com/agency-photo.jpg',
  thumbnail_url: 'https://example.com/agency-thumb.jpg',
  agency_logo_url: 'https://example.com/logo.png',
  agency_logo_thumbnail_url: 'https://example.com/logo-thumb.png',
  missions_count: 0,
  average_rating: 4.5,
  ratings_count: 10,
  member_since: 'décembre 2025',
}

describe('usePublicProducer', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('should have null producer initially', () => {
      const { producer } = usePublicProducer()
      expect(producer.value).toBeNull()
    })

    it('should not be loading initially', () => {
      const { isLoading } = usePublicProducer()
      expect(isLoading.value).toBe(false)
    })

    it('should have no error initially', () => {
      const { error } = usePublicProducer()
      expect(error.value).toBeNull()
    })

    it('should not have notFound initially', () => {
      const { notFound } = usePublicProducer()
      expect(notFound.value).toBe(false)
    })

    it('should have isAgency as false initially', () => {
      const { isAgency } = usePublicProducer()
      expect(isAgency.value).toBe(false)
    })
  })

  describe('fetchProducer', () => {
    it('should fetch particulier producer successfully', async () => {
      vi.mocked(publicApi.getProducer).mockResolvedValue({
        data: mockParticulierProducer,
      })

      const { producer, fetchProducer, isLoading, isAgency } = usePublicProducer()

      const promise = fetchProducer(1)
      expect(isLoading.value).toBe(true)

      const result = await promise
      await flushPromises()

      expect(isLoading.value).toBe(false)
      expect(producer.value).toEqual(mockParticulierProducer)
      expect(result.success).toBe(true)
      expect(result.producer).toEqual(mockParticulierProducer)
      expect(isAgency.value).toBe(false)
    })

    it('should fetch agency producer successfully', async () => {
      vi.mocked(publicApi.getProducer).mockResolvedValue({
        data: mockAgencyProducer,
      })

      const { producer, fetchProducer, isAgency } = usePublicProducer()

      const result = await fetchProducer(2)
      await flushPromises()

      expect(producer.value).toEqual(mockAgencyProducer)
      expect(result.success).toBe(true)
      expect(isAgency.value).toBe(true)
    })

    it('should handle 404 not found error', async () => {
      const axiosError = {
        isAxiosError: true,
        response: {
          status: 404,
        },
      }
      vi.mocked(publicApi.getProducer).mockRejectedValue(axiosError)

      const { producer, fetchProducer, notFound, error } = usePublicProducer()

      const result = await fetchProducer(99999)
      await flushPromises()

      expect(producer.value).toBeNull()
      expect(notFound.value).toBe(true)
      expect(error.value).toBeNull()
      expect(result.success).toBe(false)
      expect(result.notFound).toBe(true)
    })

    it('should handle network error', async () => {
      vi.mocked(publicApi.getProducer).mockRejectedValue(new Error('Network error'))

      const { producer, fetchProducer, error, notFound } = usePublicProducer()

      const result = await fetchProducer(1)
      await flushPromises()

      expect(producer.value).toBeNull()
      expect(notFound.value).toBe(false)
      expect(error.value).toBe('Une erreur est survenue. Veuillez réessayer.')
      expect(result.success).toBe(false)
      expect(result.error).toBe('Une erreur est survenue. Veuillez réessayer.')
    })

    it('should reset state before fetching', async () => {
      // First, set up some error state
      vi.mocked(publicApi.getProducer).mockRejectedValueOnce({
        response: { status: 404 },
      })

      const { fetchProducer, notFound, error, producer } = usePublicProducer()

      await fetchProducer(1)
      expect(notFound.value).toBe(true)

      // Now fetch successfully
      vi.mocked(publicApi.getProducer).mockResolvedValueOnce({
        data: mockParticulierProducer,
      })

      await fetchProducer(2)
      await flushPromises()

      expect(notFound.value).toBe(false)
      expect(error.value).toBeNull()
      expect(producer.value).toEqual(mockParticulierProducer)
    })
  })

  describe('clearProducer', () => {
    it('should clear all state', async () => {
      vi.mocked(publicApi.getProducer).mockResolvedValue({
        data: mockParticulierProducer,
      })

      const { producer, fetchProducer, clearProducer, error, notFound } = usePublicProducer()

      await fetchProducer(1)
      await flushPromises()

      expect(producer.value).not.toBeNull()

      clearProducer()

      expect(producer.value).toBeNull()
      expect(error.value).toBeNull()
      expect(notFound.value).toBe(false)
    })
  })

  describe('isAgency computed', () => {
    it('should return true for agency type', async () => {
      vi.mocked(publicApi.getProducer).mockResolvedValue({
        data: mockAgencyProducer,
      })

      const { fetchProducer, isAgency } = usePublicProducer()

      await fetchProducer(2)
      await flushPromises()

      expect(isAgency.value).toBe(true)
    })

    it('should return false for particulier type', async () => {
      vi.mocked(publicApi.getProducer).mockResolvedValue({
        data: mockParticulierProducer,
      })

      const { fetchProducer, isAgency } = usePublicProducer()

      await fetchProducer(1)
      await flushPromises()

      expect(isAgency.value).toBe(false)
    })

    it('should return false when producer is null', () => {
      const { isAgency } = usePublicProducer()
      expect(isAgency.value).toBe(false)
    })
  })
})
