import { describe, it, expect, vi, beforeEach } from 'vitest'
import { usePersonalInfo } from '../usePersonalInfo'
import { faceApi } from '../../services/faceApi'
import type { PersonalInfoInfo, PersonalInfoResponse } from '../../types'

const mockRefreshUser = vi.fn()
const mockSetUser = vi.fn()

const mockAuthStore = {
  user: {
    id: 1,
    email: 'face@example.com',
    userable_type: 'Face' as const,
    userable_id: 10,
    userable: {
      id: 10,
      nom: 'Doe',
      prenom: 'John',
      username: 'johndoe',
      sexe: 'autre' as string | null,
      sexe_label: 'Autre' as string | null,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    },
    email_verified: true,
    email_verified_at: '2026-01-01T00:00:00Z',
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
  },
  refreshUser: mockRefreshUser,
  setUser: mockSetUser,
}

vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getPersonalInfo: vi.fn(),
    updatePersonalInfo: vi.fn(),
  },
}))

vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => mockAuthStore,
}))

describe('usePersonalInfo', () => {
  const mockPersonalInfo: PersonalInfoInfo = {
    sexe: 'homme',
    date_naissance: '1995-06-15',
    nationalite: 'Béninoise',
    pays: 'Bénin',
    show_age: true,
    whatsapp_number: '+22901020304',
  }

  const mockResponse: PersonalInfoResponse = {
    data: mockPersonalInfo,
    message: 'Informations personnelles mises à jour avec succès',
  }

  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthStore.user = {
      ...mockAuthStore.user,
      userable: {
        ...mockAuthStore.user.userable,
        sexe: 'autre',
        sexe_label: 'Autre',
      },
    }
  })

  it('refreshes auth store after a successful personal info update', async () => {
    vi.mocked(faceApi.updatePersonalInfo).mockResolvedValue(mockResponse)
    mockRefreshUser.mockResolvedValue(true)

    const { personalInfo, updatePersonalInfo } = usePersonalInfo()
    const result = await updatePersonalInfo({ sexe: 'homme' })

    expect(result.success).toBe(true)
    expect(personalInfo.value).toEqual(mockPersonalInfo)
    expect(mockRefreshUser).toHaveBeenCalledOnce()
    expect(mockSetUser).not.toHaveBeenCalled()
  })

  it('falls back to updating the local auth store when refreshUser fails', async () => {
    vi.mocked(faceApi.updatePersonalInfo).mockResolvedValue(mockResponse)
    mockRefreshUser.mockResolvedValue(false)

    const { updatePersonalInfo } = usePersonalInfo()
    const result = await updatePersonalInfo({ sexe: 'homme' })

    expect(result.success).toBe(true)
    expect(mockSetUser).toHaveBeenCalledWith(
      expect.objectContaining({
        userable: expect.objectContaining({
          sexe: 'homme',
          sexe_label: 'Homme',
        }),
      }),
    )
  })
})
