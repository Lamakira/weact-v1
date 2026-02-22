import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useTarifs } from '../useTarifs'
import { faceApi } from '../../services/faceApi'
import type { TarifsInfo, TarifsResponse } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getTarifs: vi.fn(),
    updateTarifs: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('useTarifs', () => {
  const mockTarifsInfo: TarifsInfo = {
    tarif_horaire: 75000,
    tarif_journalier: 250000,
    formatted_tarif_horaire: '75 000 XOF/heure',
    formatted_tarif_journalier: '250 000 XOF/jour',
  }

  const mockResponse: TarifsResponse = {
    data: mockTarifsInfo,
    message: 'Tarifs mis à jour avec succès',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { tarifsInfo, isLoading, isSaving, error } = useTarifs()

      expect(tarifsInfo.value).toBeNull()
      expect(isLoading.value).toBe(false)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })
  })

  describe('fetchTarifs', () => {
    it('fetches tarifs successfully', async () => {
      vi.mocked(faceApi.getTarifs).mockResolvedValue(mockResponse)

      const { tarifsInfo, isLoading, error, fetchTarifs } = useTarifs()

      await fetchTarifs()

      expect(tarifsInfo.value).toEqual(mockTarifsInfo)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getTarifs).toHaveBeenCalledOnce()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: TarifsResponse) => void
      const promise = new Promise<TarifsResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getTarifs).mockReturnValue(promise)

      const { isLoading, fetchTarifs } = useTarifs()

      const fetchPromise = fetchTarifs()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getTarifs).mockRejectedValue(new Error('Network error'))

      const { tarifsInfo, error, fetchTarifs } = useTarifs()

      await fetchTarifs()

      expect(tarifsInfo.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('updateTarifs', () => {
    it('updates tarif horaire successfully', async () => {
      vi.mocked(faceApi.updateTarifs).mockResolvedValue(mockResponse)

      const { tarifsInfo, isSaving, error, updateTarifs } = useTarifs()

      const result = await updateTarifs({ tarif_horaire: 75000, tarif_journalier: null })

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockTarifsInfo)
      expect(result.message).toBe('Tarifs mis à jour avec succès')
      expect(tarifsInfo.value).toEqual(mockTarifsInfo)
      expect(isSaving.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('updates tarif journalier successfully', async () => {
      const journalierResponse: TarifsResponse = {
        data: {
          tarif_horaire: null,
          tarif_journalier: 250000,
          formatted_tarif_horaire: null,
          formatted_tarif_journalier: '250 000 XOF/jour',
        },
        message: 'Tarifs mis à jour avec succès',
      }
      vi.mocked(faceApi.updateTarifs).mockResolvedValue(journalierResponse)

      const { tarifsInfo, updateTarifs } = useTarifs()

      const result = await updateTarifs({ tarif_horaire: null, tarif_journalier: 250000 })

      expect(result.success).toBe(true)
      expect(tarifsInfo.value?.tarif_journalier).toBe(250000)
    })

    it('updates both tarifs together', async () => {
      vi.mocked(faceApi.updateTarifs).mockResolvedValue(mockResponse)

      const { updateTarifs } = useTarifs()

      const result = await updateTarifs({
        tarif_horaire: 75000,
        tarif_journalier: 250000,
      })

      expect(result.success).toBe(true)
      expect(faceApi.updateTarifs).toHaveBeenCalledWith({
        tarif_horaire: 75000,
        tarif_journalier: 250000,
      })
    })

    it('sets saving state during update', async () => {
      let resolvePromise: (value: TarifsResponse) => void
      const promise = new Promise<TarifsResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.updateTarifs).mockReturnValue(promise)

      const { isSaving, updateTarifs } = useTarifs()

      const updatePromise = updateTarifs({ tarif_horaire: 75000, tarif_journalier: null })
      expect(isSaving.value).toBe(true)

      resolvePromise!(mockResponse)
      await updatePromise

      expect(isSaving.value).toBe(false)
    })

    it('handles update error', async () => {
      vi.mocked(faceApi.updateTarifs).mockRejectedValue(new Error('Update failed'))

      const { error, updateTarifs } = useTarifs()

      const result = await updateTarifs({ tarif_horaire: 75000, tarif_journalier: null })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('validateTarifHoraire', () => {
    it('validates tarif horaire within range', () => {
      const { validateTarifHoraire } = useTarifs()

      const result = validateTarifHoraire(75000)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates null tarif horaire', () => {
      const { validateTarifHoraire } = useTarifs()

      const result = validateTarifHoraire(null)

      expect(result.valid).toBe(true)
    })

    it('validates tarif horaire at zero', () => {
      const { validateTarifHoraire } = useTarifs()

      const result = validateTarifHoraire(0)

      expect(result.valid).toBe(true)
    })

    it('validates tarif horaire at maximum (10000000)', () => {
      const { validateTarifHoraire } = useTarifs()

      const result = validateTarifHoraire(10000000)

      expect(result.valid).toBe(true)
    })

    it('rejects negative tarif horaire', () => {
      const { validateTarifHoraire } = useTarifs()

      const result = validateTarifHoraire(-1)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le tarif demi-journée doit être positif')
    })

    it('rejects tarif horaire above maximum', () => {
      const { validateTarifHoraire } = useTarifs()

      const result = validateTarifHoraire(10000001)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le tarif demi-journée ne peut pas dépasser 10 000 000 XOF')
    })

    it('rejects non-integer tarif horaire', () => {
      const { validateTarifHoraire } = useTarifs()

      const result = validateTarifHoraire(75000.5)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le tarif demi-journée doit être un nombre entier')
    })

    it('rejects invalid tarif horaire before calling API', async () => {
      const { updateTarifs, error } = useTarifs()

      const result = await updateTarifs({ tarif_horaire: -1, tarif_journalier: null })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Le tarif demi-journée doit être positif')
      expect(error.value).toBe('Le tarif demi-journée doit être positif')
      expect(faceApi.updateTarifs).not.toHaveBeenCalled()
    })
  })

  describe('validateTarifJournalier', () => {
    it('validates tarif journalier within range', () => {
      const { validateTarifJournalier } = useTarifs()

      const result = validateTarifJournalier(250000)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates null tarif journalier', () => {
      const { validateTarifJournalier } = useTarifs()

      const result = validateTarifJournalier(null)

      expect(result.valid).toBe(true)
    })

    it('validates tarif journalier at zero', () => {
      const { validateTarifJournalier } = useTarifs()

      const result = validateTarifJournalier(0)

      expect(result.valid).toBe(true)
    })

    it('validates tarif journalier at maximum (100000000)', () => {
      const { validateTarifJournalier } = useTarifs()

      const result = validateTarifJournalier(100000000)

      expect(result.valid).toBe(true)
    })

    it('rejects negative tarif journalier', () => {
      const { validateTarifJournalier } = useTarifs()

      const result = validateTarifJournalier(-1)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le tarif journalier doit être positif')
    })

    it('rejects tarif journalier above maximum', () => {
      const { validateTarifJournalier } = useTarifs()

      const result = validateTarifJournalier(100000001)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le tarif journalier ne peut pas dépasser 100 000 000 XOF')
    })

    it('rejects non-integer tarif journalier', () => {
      const { validateTarifJournalier } = useTarifs()

      const result = validateTarifJournalier(250000.5)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le tarif journalier doit être un nombre entier')
    })

    it('rejects invalid tarif journalier before calling API', async () => {
      const { updateTarifs, error } = useTarifs()

      const result = await updateTarifs({ tarif_horaire: null, tarif_journalier: -1 })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Le tarif journalier doit être positif')
      expect(error.value).toBe('Le tarif journalier doit être positif')
      expect(faceApi.updateTarifs).not.toHaveBeenCalled()
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(faceApi.updateTarifs).mockRejectedValue(new Error('Error'))

      const { error, updateTarifs, clearError } = useTarifs()

      await updateTarifs({ tarif_horaire: 75000, tarif_journalier: null })
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })

  describe('validateTarifsConsistency', () => {
    it('validates when daily rate is higher than hourly rate', () => {
      const { validateTarifsConsistency } = useTarifs()

      const result = validateTarifsConsistency(75000, 250000)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates when daily rate equals hourly rate', () => {
      const { validateTarifsConsistency } = useTarifs()

      const result = validateTarifsConsistency(100000, 100000)

      expect(result.valid).toBe(true)
    })

    it('validates when only hourly rate is provided', () => {
      const { validateTarifsConsistency } = useTarifs()

      const result = validateTarifsConsistency(75000, null)

      expect(result.valid).toBe(true)
    })

    it('validates when only daily rate is provided', () => {
      const { validateTarifsConsistency } = useTarifs()

      const result = validateTarifsConsistency(null, 250000)

      expect(result.valid).toBe(true)
    })

    it('validates when both are null', () => {
      const { validateTarifsConsistency } = useTarifs()

      const result = validateTarifsConsistency(null, null)

      expect(result.valid).toBe(true)
    })

    it('rejects when daily rate is lower than hourly rate', () => {
      const { validateTarifsConsistency } = useTarifs()

      const result = validateTarifsConsistency(100000, 50000)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le tarif journalier doit être supérieur ou égal au tarif demi-journée')
    })

    it('rejects inconsistent tarifs before calling API', async () => {
      const { updateTarifs, error } = useTarifs()

      const result = await updateTarifs({ tarif_horaire: 100000, tarif_journalier: 50000 })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Le tarif journalier doit être supérieur ou égal au tarif demi-journée')
      expect(error.value).toBe('Le tarif journalier doit être supérieur ou égal au tarif demi-journée')
      expect(faceApi.updateTarifs).not.toHaveBeenCalled()
    })
  })

  describe('clearing tarifs', () => {
    it('can clear tarif horaire by passing null', async () => {
      const clearedResponse: TarifsResponse = {
        data: {
          tarif_horaire: null,
          tarif_journalier: 250000,
          formatted_tarif_horaire: null,
          formatted_tarif_journalier: '250 000 XOF/jour',
        },
        message: 'Tarifs mis à jour avec succès',
      }
      vi.mocked(faceApi.updateTarifs).mockResolvedValue(clearedResponse)

      const { tarifsInfo, updateTarifs } = useTarifs()

      const result = await updateTarifs({ tarif_horaire: null, tarif_journalier: null })

      expect(result.success).toBe(true)
      expect(tarifsInfo.value?.tarif_horaire).toBeNull()
      expect(faceApi.updateTarifs).toHaveBeenCalledWith({ tarif_horaire: null, tarif_journalier: null })
    })

    it('can clear tarif journalier by passing null', async () => {
      const clearedResponse: TarifsResponse = {
        data: {
          tarif_horaire: 75000,
          tarif_journalier: null,
          formatted_tarif_horaire: '75 000 XOF/heure',
          formatted_tarif_journalier: null,
        },
        message: 'Tarifs mis à jour avec succès',
      }
      vi.mocked(faceApi.updateTarifs).mockResolvedValue(clearedResponse)

      const { tarifsInfo, updateTarifs } = useTarifs()

      const result = await updateTarifs({ tarif_horaire: null, tarif_journalier: null })

      expect(result.success).toBe(true)
      expect(tarifsInfo.value?.tarif_journalier).toBeNull()
    })
  })
})
