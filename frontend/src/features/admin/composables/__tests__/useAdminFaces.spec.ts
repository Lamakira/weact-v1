import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock adminFacesApi
const mockGetFaces = vi.fn()
const mockGetFace = vi.fn()
const mockUpdateFace = vi.fn()
const mockDeleteFace = vi.fn()
vi.mock('../../services/adminFacesApi', () => ({
  adminFacesApi: {
    getFaces: (...args: unknown[]) => mockGetFaces(...args),
    getFace: (...args: unknown[]) => mockGetFace(...args),
    updateFace: (...args: unknown[]) => mockUpdateFace(...args),
    deleteFace: (...args: unknown[]) => mockDeleteFace(...args),
  },
}))

// Mock error utilities
vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorDetails: vi.fn((err: { response?: { data?: { error?: { details?: Record<string, string[]> } } } } ) => {
    return err?.response?.data?.error?.details ?? {}
  }),
  getApiErrorMessage: vi.fn((err: { response?: { data?: { error?: { message?: string } } } }) => {
    return err?.response?.data?.error?.message ?? 'Error'
  }),
}))

import { useAdminFaces } from '../useAdminFaces'

const mockFace = {
  id: 1,
  nom: 'Dupont',
  prenom: 'Jean',
  username: 'jdupont',
  bio: 'Test bio',
  ville: 'Cotonou',
  quartier: null,
  pays: 'Bénin',
  categorie: 'acteur',
  niche: 'beaute',
  is_available: true,
  profile_completion_percentage: 75,
  average_rating: 4.2,
  ratings_count: 5,
  profile_photo_url: null,
  created_at: '2026-01-15T00:00:00.000Z',
}

describe('useAdminFaces - fetchFaces', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('fetches faces list successfully', async () => {
    const mockResponse = {
      data: [mockFace, { ...mockFace, id: 2, nom: 'Martin', username: 'pmartin' }],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
    }
    mockGetFaces.mockResolvedValue(mockResponse)

    const { faces, pagination, fetchFaces } = useAdminFaces()
    await fetchFaces()

    expect(mockGetFaces).toHaveBeenCalledWith(undefined)
    expect(faces.value).toHaveLength(2)
    expect(faces.value[0].nom).toBe('Dupont')
    expect(pagination.value?.total).toBe(2)
  })

  it('passes params to API including search and filters', async () => {
    mockGetFaces.mockResolvedValue({
      data: [mockFace],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })

    const { fetchFaces } = useAdminFaces()
    await fetchFaces({ page: 2, search: 'Dupont', category: 'acteur' })

    expect(mockGetFaces).toHaveBeenCalledWith({
      page: 2,
      search: 'Dupont',
      category: 'acteur',
    })
  })

  it('sets loading state during fetch', async () => {
    let resolvePromise: (value: unknown) => void
    mockGetFaces.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    const { isLoading, fetchFaces } = useAdminFaces()
    const fetchPromise = fetchFaces()

    expect(isLoading.value).toBe(true)

    resolvePromise!({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    })

    await fetchPromise

    expect(isLoading.value).toBe(false)
  })

  it('handles fetch error', async () => {
    mockGetFaces.mockRejectedValue({
      response: {
        data: {
          error: { message: 'Unauthorized' },
        },
      },
    })

    const { faces, error, fetchFaces } = useAdminFaces()
    await fetchFaces()

    expect(error.value).toBe('Unauthorized')
    expect(faces.value).toHaveLength(0)
  })
})

describe('useAdminFaces - fetchFace', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('fetches single face successfully', async () => {
    mockGetFace.mockResolvedValue({
      data: mockFace,
      message: 'Face retrieved successfully',
    })

    const { face, fetchFace } = useAdminFaces()
    await fetchFace(1)

    expect(mockGetFace).toHaveBeenCalledWith(1)
    expect(face.value?.nom).toBe('Dupont')
    expect(face.value?.username).toBe('jdupont')
  })

  it('handles fetch face error', async () => {
    mockGetFace.mockRejectedValue({
      response: {
        data: {
          error: { message: 'Face not found' },
        },
      },
    })

    const { face, error, fetchFace } = useAdminFaces()
    await fetchFace(999)

    expect(error.value).toBe('Face not found')
    expect(face.value).toBeNull()
  })
})

describe('useAdminFaces - updateFace', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('updates face successfully and returns API message', async () => {
    mockUpdateFace.mockResolvedValue({
      data: { ...mockFace, nom: 'NewName' },
      message: 'Profil Face mis à jour avec succès',
    })

    const { face, updateFace } = useAdminFaces()
    const result = await updateFace(1, { nom: 'NewName' })

    expect(result.success).toBe(true)
    expect(result.message).toBe('Profil Face mis à jour avec succès')
    expect(face.value?.nom).toBe('NewName')
    expect(mockUpdateFace).toHaveBeenCalledWith(1, { nom: 'NewName' })
  })

  it('returns validation errors on update failure', async () => {
    mockUpdateFace.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'validation_error',
            message: 'Les données fournies ne sont pas valides',
            details: { username: ["Ce nom d'utilisateur est déjà utilisé."] },
          },
        },
      },
    })

    const { updateFace } = useAdminFaces()
    const result = await updateFace(1, { username: 'taken' })

    expect(result.success).toBe(false)
    expect(result.message).toBe('Les données fournies ne sont pas valides')
    expect(result.errors?.username?.[0]).toBe("Ce nom d'utilisateur est déjà utilisé.")
  })
})

describe('useAdminFaces - deleteFace', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('deletes face successfully', async () => {
    mockDeleteFace.mockResolvedValue({
      message: 'Profil Face supprimé avec succès',
    })

    const { deleteFace } = useAdminFaces()
    const result = await deleteFace(1)

    expect(result.success).toBe(true)
    expect(result.message).toBe('Profil Face supprimé avec succès')
    expect(mockDeleteFace).toHaveBeenCalledWith(1)
  })

  it('returns error when delete is blocked by active candidatures', async () => {
    mockDeleteFace.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'active_candidatures',
            message: 'Impossible de supprimer ce profil. Des candidatures actives existent.',
          },
        },
      },
    })

    const { deleteFace } = useAdminFaces()
    const result = await deleteFace(1)

    expect(result.success).toBe(false)
    expect(result.message).toBe(
      'Impossible de supprimer ce profil. Des candidatures actives existent.',
    )
  })

  it('sets loading state during delete', async () => {
    let resolvePromise: (value: unknown) => void
    mockDeleteFace.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    const { isLoading, deleteFace } = useAdminFaces()
    const deletePromise = deleteFace(1)

    expect(isLoading.value).toBe(true)

    resolvePromise!({ message: 'Profil Face supprimé avec succès' })

    await deletePromise

    expect(isLoading.value).toBe(false)
  })
})
