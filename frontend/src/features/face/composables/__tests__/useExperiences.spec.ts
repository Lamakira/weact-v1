import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useExperiences } from '../useExperiences'
import { faceApi } from '../../services/faceApi'
import type { Experience, ExperienceResponse, ExperiencesListResponse } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getExperiences: vi.fn(),
    createExperience: vi.fn(),
    updateExperience: vi.fn(),
    deleteExperience: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('useExperiences', () => {
  const mockExperience: Experience = {
    id: 1,
    titre: 'Publicité Coca-Cola',
    description: 'Tournage publicitaire',
    date_debut: '2024-01-15',
    date_fin: '2024-03-20',
    is_ongoing: false,
    formatted_period: '15/01/2024 - 20/03/2024',
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z',
  }

  const mockExperiences: Experience[] = [
    { id: 1, titre: 'Publicité Coca-Cola', description: 'Tournage publicitaire', date_debut: '2024-01-15', date_fin: '2024-03-20', is_ongoing: false, formatted_period: '15/01/2024 - 20/03/2024', created_at: '2024-01-01T00:00:00.000Z', updated_at: '2024-01-01T00:00:00.000Z' },
    { id: 2, titre: 'Film documentaire', description: null, date_debut: '2023-06-15', date_fin: '2023-12-20', is_ongoing: false, formatted_period: '15/06/2023 - 20/12/2023', created_at: '2023-06-15T00:00:00.000Z', updated_at: '2023-06-15T00:00:00.000Z' },
    { id: 3, titre: 'Série TV locale', description: 'Rôle secondaire', date_debut: '2022-03-20', date_fin: null, is_ongoing: true, formatted_period: '20/03/2022 - Présent', created_at: '2022-03-20T00:00:00.000Z', updated_at: '2022-03-20T00:00:00.000Z' },
  ]

  const mockListResponse: ExperiencesListResponse = {
    data: mockExperiences,
    message: 'Expériences récupérées avec succès',
  }

  const mockCreateResponse: ExperienceResponse = {
    data: mockExperience,
    message: 'Expérience créée avec succès',
  }

  const mockUpdateResponse: ExperienceResponse = {
    data: { ...mockExperience, titre: 'Mise à jour titre' },
    message: 'Expérience mise à jour avec succès',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const {
        experiences,
        isLoading,
        isSaving,
        isDeleting,
        error,
        validationErrors,
      } = useExperiences()

      expect(experiences.value).toEqual([])
      expect(isLoading.value).toBe(false)
      expect(isSaving.value).toBe(false)
      expect(isDeleting.value).toBe(false)
      expect(error.value).toBeNull()
      expect(validationErrors.value).toEqual({})
    })
  })

  describe('fetchExperiences', () => {
    it('fetches experiences successfully', async () => {
      vi.mocked(faceApi.getExperiences).mockResolvedValue(mockListResponse)

      const { experiences, isLoading, error, fetchExperiences } = useExperiences()

      await fetchExperiences()

      expect(experiences.value).toHaveLength(3)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getExperiences).toHaveBeenCalledOnce()
    })

    it('trusts backend sorting order', async () => {
      // Backend returns data sorted by date_debut descending
      const sortedExperiences: Experience[] = [
        { id: 2, titre: 'New', description: null, date_debut: '2024-01-15', date_fin: '2024-06-30', is_ongoing: false, formatted_period: '15/01/2024 - 30/06/2024', created_at: '2024-01-01T00:00:00.000Z', updated_at: '2024-01-01T00:00:00.000Z' },
        { id: 3, titre: 'Middle', description: null, date_debut: '2022-01-01', date_fin: '2022-12-31', is_ongoing: false, formatted_period: '01/01/2022 - 31/12/2022', created_at: '2022-01-01T00:00:00.000Z', updated_at: '2022-01-01T00:00:00.000Z' },
        { id: 1, titre: 'Old', description: null, date_debut: '2020-01-01', date_fin: '2020-12-31', is_ongoing: false, formatted_period: '01/01/2020 - 31/12/2020', created_at: '2020-01-01T00:00:00.000Z', updated_at: '2020-01-01T00:00:00.000Z' },
      ]
      vi.mocked(faceApi.getExperiences).mockResolvedValue({ data: sortedExperiences })

      const { experiences, fetchExperiences } = useExperiences()

      await fetchExperiences()

      // Data should be in the same order as returned by backend
      expect(experiences.value[0].date_debut).toBe('2024-01-15')
      expect(experiences.value[1].date_debut).toBe('2022-01-01')
      expect(experiences.value[2].date_debut).toBe('2020-01-01')
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: ExperiencesListResponse) => void
      const promise = new Promise<ExperiencesListResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getExperiences).mockReturnValue(promise)

      const { isLoading, fetchExperiences } = useExperiences()

      const fetchPromise = fetchExperiences()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockListResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getExperiences).mockRejectedValue(new Error('Network error'))

      const { experiences, error, fetchExperiences } = useExperiences()

      await fetchExperiences()

      expect(experiences.value).toEqual([])
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('addExperience', () => {
    it('adds experience successfully', async () => {
      vi.mocked(faceApi.createExperience).mockResolvedValue(mockCreateResponse)

      const { experiences, isSaving, error, addExperience } = useExperiences()

      const result = await addExperience({
        titre: 'Publicité Coca-Cola',
        description: 'Tournage publicitaire',
        date_debut: '2024-01-15',
        date_fin: '2024-03-20',
      })

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockExperience)
      expect(result.message).toBe('Expérience créée avec succès')
      expect(experiences.value).toContainEqual(mockExperience)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets saving state during add', async () => {
      let resolvePromise: (value: ExperienceResponse) => void
      const promise = new Promise<ExperienceResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.createExperience).mockReturnValue(promise)

      const { isSaving, addExperience } = useExperiences()

      const addPromise = addExperience({
        titre: 'Test',
        description: null,
        date_debut: '2024-01-15',
      })
      expect(isSaving.value).toBe(true)

      resolvePromise!(mockCreateResponse)
      await addPromise

      expect(isSaving.value).toBe(false)
    })

    it('handles add error', async () => {
      vi.mocked(faceApi.createExperience).mockRejectedValue(new Error('Create failed'))

      const { error, addExperience } = useExperiences()

      const result = await addExperience({
        titre: 'Test',
        description: null,
        date_debut: '2024-01-15',
      })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })

    it('adds new experience and re-sorts list', async () => {
      const oldExperience: Experience = {
        id: 1,
        titre: 'Old',
        description: null,
        date_debut: '2020-01-15',
        date_fin: '2020-12-31',
        is_ongoing: false,
        formatted_period: '15/01/2020 - 31/12/2020',
        created_at: '2020-01-01T00:00:00.000Z',
        updated_at: '2020-01-01T00:00:00.000Z',
      }
      const newExperience: Experience = {
        id: 2,
        titre: 'New',
        description: null,
        date_debut: '2024-01-15',
        date_fin: '2024-06-30',
        is_ongoing: false,
        formatted_period: '15/01/2024 - 30/06/2024',
        created_at: '2024-01-01T00:00:00.000Z',
        updated_at: '2024-01-01T00:00:00.000Z',
      }

      vi.mocked(faceApi.getExperiences).mockResolvedValue({ data: [oldExperience] })
      vi.mocked(faceApi.createExperience).mockResolvedValue({ data: newExperience, message: 'Created' })

      const { experiences, fetchExperiences, addExperience } = useExperiences()

      await fetchExperiences()
      expect(experiences.value[0].id).toBe(1)

      await addExperience({ titre: 'New', description: null, date_debut: '2024-01-15', date_fin: '2024-06-30' })

      // New experience should be first (2024 > 2020)
      expect(experiences.value[0].id).toBe(2)
      expect(experiences.value[1].id).toBe(1)
    })
  })

  describe('editExperience', () => {
    it('edits experience successfully', async () => {
      vi.mocked(faceApi.getExperiences).mockResolvedValue(mockListResponse)
      vi.mocked(faceApi.updateExperience).mockResolvedValue(mockUpdateResponse)

      const { experiences, isSaving, error, fetchExperiences, editExperience } = useExperiences()

      await fetchExperiences()
      const result = await editExperience(1, {
        titre: 'Mise à jour titre',
        description: 'Tournage publicitaire',
        date_debut: '2024-01-15',
        date_fin: '2024-03-20',
      })

      expect(result.success).toBe(true)
      expect(result.data?.titre).toBe('Mise à jour titre')
      expect(result.message).toBe('Expérience mise à jour avec succès')
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('updates experience in list', async () => {
      const initialExperience: Experience = {
        id: 1,
        titre: 'Original',
        description: null,
        date_debut: '2024-01-15',
        date_fin: '2024-06-30',
        is_ongoing: false,
        formatted_period: '15/01/2024 - 30/06/2024',
        created_at: '2024-01-01T00:00:00.000Z',
        updated_at: '2024-01-01T00:00:00.000Z',
      }
      const updatedExperience: Experience = {
        id: 1,
        titre: 'Updated',
        description: 'New description',
        date_debut: '2024-01-15',
        date_fin: '2024-06-30',
        is_ongoing: false,
        formatted_period: '15/01/2024 - 30/06/2024',
        created_at: '2024-01-01T00:00:00.000Z',
        updated_at: '2024-01-01T00:00:00.000Z',
      }

      vi.mocked(faceApi.getExperiences).mockResolvedValue({ data: [initialExperience] })
      vi.mocked(faceApi.updateExperience).mockResolvedValue({ data: updatedExperience, message: 'Updated' })

      const { experiences, fetchExperiences, editExperience } = useExperiences()

      await fetchExperiences()
      expect(experiences.value[0].titre).toBe('Original')

      await editExperience(1, { titre: 'Updated', description: 'New description', date_debut: '2024-01-15', date_fin: '2024-06-30' })

      expect(experiences.value[0].titre).toBe('Updated')
      expect(experiences.value[0].description).toBe('New description')
    })

    it('sets saving state during edit', async () => {
      let resolvePromise: (value: ExperienceResponse) => void
      const promise = new Promise<ExperienceResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.updateExperience).mockReturnValue(promise)

      const { isSaving, editExperience } = useExperiences()

      const editPromise = editExperience(1, {
        titre: 'Test',
        description: null,
        date_debut: '2024-01-15',
      })
      expect(isSaving.value).toBe(true)

      resolvePromise!(mockUpdateResponse)
      await editPromise

      expect(isSaving.value).toBe(false)
    })

    it('handles edit error', async () => {
      vi.mocked(faceApi.updateExperience).mockRejectedValue(new Error('Update failed'))

      const { error, editExperience } = useExperiences()

      const result = await editExperience(1, {
        titre: 'Test',
        description: null,
        date_debut: '2024-01-15',
      })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })

    it('re-sorts list after date change', async () => {
      // Backend returns data sorted by date_debut descending
      const experiences: Experience[] = [
        { id: 2, titre: 'New', description: null, date_debut: '2024-01-15', date_fin: '2024-06-30', is_ongoing: false, formatted_period: '15/01/2024 - 30/06/2024', created_at: '2024-01-01T00:00:00.000Z', updated_at: '2024-01-01T00:00:00.000Z' },
        { id: 1, titre: 'Old', description: null, date_debut: '2020-01-15', date_fin: '2020-12-31', is_ongoing: false, formatted_period: '15/01/2020 - 31/12/2020', created_at: '2020-01-01T00:00:00.000Z', updated_at: '2020-01-01T00:00:00.000Z' },
      ]
      const updatedExperience: Experience = {
        id: 1,
        titre: 'Old updated',
        description: null,
        date_debut: '2025-01-15', // Now newest
        date_fin: null,
        is_ongoing: true,
        formatted_period: '15/01/2025 - Présent',
        created_at: '2020-01-01T00:00:00.000Z',
        updated_at: '2020-01-01T00:00:00.000Z',
      }

      vi.mocked(faceApi.getExperiences).mockResolvedValue({ data: experiences })
      vi.mocked(faceApi.updateExperience).mockResolvedValue({ data: updatedExperience, message: 'Updated' })

      const { experiences: exps, fetchExperiences, editExperience } = useExperiences()

      await fetchExperiences()
      expect(exps.value[0].id).toBe(2) // 2024 is first (backend sorted)

      await editExperience(1, { titre: 'Old updated', description: null, date_debut: '2025-01-15' })

      expect(exps.value[0].id).toBe(1) // 2025 is now first (client re-sorted)
    })
  })

  describe('removeExperience', () => {
    it('removes experience successfully', async () => {
      vi.mocked(faceApi.getExperiences).mockResolvedValue(mockListResponse)
      vi.mocked(faceApi.deleteExperience).mockResolvedValue({ message: 'Deleted' })

      const { experiences, isDeleting, error, fetchExperiences, removeExperience } = useExperiences()

      await fetchExperiences()
      expect(experiences.value).toHaveLength(3)

      const result = await removeExperience(1)

      expect(result).toBe(true)
      expect(experiences.value).toHaveLength(2)
      expect(experiences.value.find((e) => e.id === 1)).toBeUndefined()
      expect(isDeleting.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets deleting state during remove', async () => {
      let resolvePromise: (value: { message: string }) => void
      const promise = new Promise<{ message: string }>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.deleteExperience).mockReturnValue(promise)

      const { isDeleting, removeExperience } = useExperiences()

      const removePromise = removeExperience(1)
      expect(isDeleting.value).toBe(true)

      resolvePromise!({ message: 'Deleted' })
      await removePromise

      expect(isDeleting.value).toBe(false)
    })

    it('handles remove error', async () => {
      vi.mocked(faceApi.deleteExperience).mockRejectedValue(new Error('Delete failed'))

      const { error, removeExperience } = useExperiences()

      const result = await removeExperience(1)

      expect(result).toBe(false)
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('clearError', () => {
    it('clears the error and validation errors', async () => {
      vi.mocked(faceApi.createExperience).mockRejectedValue(new Error('Error'))

      const { error, validationErrors, addExperience, clearError } = useExperiences()

      await addExperience({ titre: 'Test', description: null, date_debut: '2024-01-15' })
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
      expect(validationErrors.value).toEqual({})
    })
  })
})
