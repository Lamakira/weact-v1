import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useAdminProducers } from '../useAdminProducers'
import { adminProducersApi } from '../../services/adminProducersApi'
import type {
  AdminProducerListResponse,
  AdminProducerDetailResponse,
  AdminProducerData,
} from '../../services/adminProducersApi'

vi.mock('../../services/adminProducersApi', () => ({
  adminProducersApi: {
    getProducers: vi.fn(),
    getProducer: vi.fn(),
    updateProducer: vi.fn(),
    toggleActive: vi.fn(),
    deleteProducer: vi.fn(),
  },
}))

vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorDetails: vi.fn((err: any) => err?.response?.data?.errors ?? {}),
  getApiErrorMessage: vi.fn((err: any) => err?.response?.data?.message ?? null),
}))

function makeProducer(overrides: Partial<AdminProducerData> = {}): AdminProducerData {
  return {
    id: 1,
    type: 'agency',
    agency_name: 'Studio One',
    first_name: 'Jean',
    last_name: 'Dupont',
    display_name: 'Studio One',
    bio: 'A production studio',
    profile_photo_url: null,
    thumbnail_url: null,
    agency_logo_url: null,
    agency_logo_thumbnail_url: null,
    average_rating: 4.5,
    ratings_count: 12,
    created_at: '2025-01-01T00:00:00.000Z',
    updated_at: '2025-01-01T00:00:00.000Z',
    ...overrides,
  }
}

describe('useAdminProducers', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('fetchProducers', () => {
    it('fetches paginated producers and sets state', async () => {
      const mockResponse: AdminProducerListResponse = {
        data: [makeProducer({ id: 1 }), makeProducer({ id: 2, display_name: 'Studio Two' })],
        meta: { current_page: 1, last_page: 2, per_page: 15, total: 20 },
      }
      vi.mocked(adminProducersApi.getProducers).mockResolvedValue(mockResponse)

      const { producers, pagination, isLoading, error, fetchProducers } = useAdminProducers()

      expect(producers.value).toEqual([])
      expect(isLoading.value).toBe(false)

      const promise = fetchProducers({ page: 1 })
      expect(isLoading.value).toBe(true)

      await promise

      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(producers.value).toHaveLength(2)
      expect(producers.value[0].id).toBe(1)
      expect(pagination.value?.total).toBe(20)
      expect(pagination.value?.last_page).toBe(2)
    })

    it('passes search and type params to the API', async () => {
      vi.mocked(adminProducersApi.getProducers).mockResolvedValue({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
      })

      const { fetchProducers } = useAdminProducers()
      await fetchProducers({ page: 1, search: 'studio', type: 'agency' })

      expect(adminProducersApi.getProducers).toHaveBeenCalledWith({
        page: 1,
        search: 'studio',
        type: 'agency',
      })
    })

    it('handles fetch error and clears data', async () => {
      vi.mocked(adminProducersApi.getProducers).mockRejectedValue({
        response: { data: { message: 'Erreur serveur' } },
      })

      const { producers, pagination, error, fetchProducers } = useAdminProducers()
      await fetchProducers()

      expect(error.value).toBe('Erreur serveur')
      expect(producers.value).toEqual([])
      expect(pagination.value).toBeNull()
    })
  })

  describe('fetchProducer', () => {
    it('fetches a single producer by ID', async () => {
      const mockResponse: AdminProducerDetailResponse = {
        data: makeProducer({ id: 42 }),
        message: 'OK',
      }
      vi.mocked(adminProducersApi.getProducer).mockResolvedValue(mockResponse)

      const { producer, error, fetchProducer } = useAdminProducers()
      await fetchProducer(42)

      expect(adminProducersApi.getProducer).toHaveBeenCalledWith(42)
      expect(producer.value?.id).toBe(42)
      expect(error.value).toBeNull()
    })

    it('handles fetch error and clears producer', async () => {
      vi.mocked(adminProducersApi.getProducer).mockRejectedValue({
        response: { data: { message: 'Producteur introuvable' } },
      })

      const { producer, error, fetchProducer } = useAdminProducers()
      await fetchProducer(999)

      expect(error.value).toBe('Producteur introuvable')
      expect(producer.value).toBeNull()
    })
  })

  describe('fetchProducer - detail fields (Story 13-7)', () => {
    it('populates producer ref including missions array', async () => {
      const producerWithMissions = makeProducer({
        id: 42,
        missions: [
          { id: 1, title: 'Mission Alpha', status: 'published', status_label: 'Publiée', created_at: '2025-06-01T00:00:00.000Z' },
          { id: 2, title: 'Mission Beta', status: 'completed', status_label: 'Terminée', created_at: '2025-05-01T00:00:00.000Z' },
        ],
      })
      vi.mocked(adminProducersApi.getProducer).mockResolvedValue({
        data: producerWithMissions,
        message: 'OK',
      })

      const { producer, fetchProducer } = useAdminProducers()
      await fetchProducer(42)

      expect(producer.value?.missions).toHaveLength(2)
      expect(producer.value?.missions?.[0].title).toBe('Mission Alpha')
      expect(producer.value?.missions?.[0].status).toBe('published')
      expect(producer.value?.missions?.[1].title).toBe('Mission Beta')
      expect(producer.value?.missions?.[1].status).toBe('completed')
    })

    it('handles producer with no missions (undefined)', async () => {
      const producerNoMissions = makeProducer({ id: 43 })
      vi.mocked(adminProducersApi.getProducer).mockResolvedValue({
        data: producerNoMissions,
        message: 'OK',
      })

      const { producer, fetchProducer } = useAdminProducers()
      await fetchProducer(43)

      expect(producer.value?.missions).toBeUndefined()
    })
  })

  describe('updateProducer', () => {
    it('updates producer and returns success result', async () => {
      const updatedProducer = makeProducer({ id: 5, agency_name: 'New Name' })
      vi.mocked(adminProducersApi.updateProducer).mockResolvedValue({
        data: updatedProducer,
        message: 'Profil mis à jour',
      })

      const { producer, updateProducer } = useAdminProducers()
      const result = await updateProducer(5, { agency_name: 'New Name' })

      expect(result.success).toBe(true)
      expect(result.message).toBe('Profil mis à jour')
      expect(producer.value?.agency_name).toBe('New Name')
    })

    it('returns validation errors on failure', async () => {
      vi.mocked(adminProducersApi.updateProducer).mockRejectedValue({
        response: {
          data: {
            message: 'Données invalides',
            errors: { type: ['Le type est invalide.'] },
          },
        },
      })

      const { updateProducer } = useAdminProducers()
      const result = await updateProducer(5, { type: 'invalid' })

      expect(result.success).toBe(false)
      expect(result.errors?.type).toEqual(['Le type est invalide.'])
      expect(result.message).toBe('Données invalides')
    })
  })

  describe('deleteProducer', () => {
    it('deletes producer and returns success', async () => {
      vi.mocked(adminProducersApi.deleteProducer).mockResolvedValue({
        message: 'Profil Producteur supprimé avec succès',
      })

      const { deleteProducer } = useAdminProducers()
      const result = await deleteProducer(10)

      expect(adminProducersApi.deleteProducer).toHaveBeenCalledWith(10)
      expect(result.success).toBe(true)
      expect(result.message).toBe('Profil Producteur supprimé avec succès')
    })

    it('returns error when deletion is blocked', async () => {
      vi.mocked(adminProducersApi.deleteProducer).mockRejectedValue({
        response: {
          data: { message: 'Impossible de supprimer ce producteur. Des missions actives existent.' },
        },
      })

      const { deleteProducer } = useAdminProducers()
      const result = await deleteProducer(10)

      expect(result.success).toBe(false)
      expect(result.message).toBe(
        'Impossible de supprimer ce producteur. Des missions actives existent.',
      )
    })
  })

  describe('toggleActive', () => {
    it('toggles producer active status and updates producer ref', async () => {
      const deactivatedProducer = makeProducer({ id: 5, is_active: false })
      vi.mocked(adminProducersApi.toggleActive).mockResolvedValue({
        data: deactivatedProducer,
        message: 'Compte désactivé avec succès',
      })

      const { producer, toggleActive } = useAdminProducers()
      const result = await toggleActive(5)

      expect(adminProducersApi.toggleActive).toHaveBeenCalledWith(5)
      expect(result.success).toBe(true)
      expect(result.message).toBe('Compte désactivé avec succès')
      expect(producer.value?.is_active).toBe(false)
    })

    it('updates producer in list when toggling', async () => {
      vi.mocked(adminProducersApi.getProducers).mockResolvedValue({
        data: [makeProducer({ id: 5, is_active: true })],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
      })
      vi.mocked(adminProducersApi.toggleActive).mockResolvedValue({
        data: makeProducer({ id: 5, is_active: false }),
        message: 'Compte désactivé avec succès',
      })

      const { producers, fetchProducers, toggleActive } = useAdminProducers()
      await fetchProducers()
      expect(producers.value[0].is_active).toBe(true)

      await toggleActive(5)
      expect(producers.value[0].is_active).toBe(false)
    })

    it('returns error on toggle failure', async () => {
      vi.mocked(adminProducersApi.toggleActive).mockRejectedValue({
        response: {
          data: { message: 'Aucun compte utilisateur associé à ce profil.' },
        },
      })

      const { toggleActive } = useAdminProducers()
      const result = await toggleActive(5)

      expect(result.success).toBe(false)
      expect(result.message).toBe('Aucun compte utilisateur associé à ce profil.')
    })
  })

  describe('loading state', () => {
    it('sets isLoading to false after each operation completes', async () => {
      vi.mocked(adminProducersApi.getProducers).mockResolvedValue({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
      })

      const { isLoading, fetchProducers } = useAdminProducers()

      await fetchProducers()
      expect(isLoading.value).toBe(false)
    })

    it('sets isLoading to false even on error', async () => {
      vi.mocked(adminProducersApi.getProducers).mockRejectedValue(new Error('Network error'))

      const { isLoading, fetchProducers } = useAdminProducers()

      await fetchProducers()
      expect(isLoading.value).toBe(false)
    })
  })
})
