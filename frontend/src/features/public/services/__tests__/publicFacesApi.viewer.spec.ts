import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { getMock, authState } = vi.hoisted(() => ({
  getMock: vi.fn(),
  authState: { isProducer: false },
}))

vi.mock('../apiClient', () => ({
  publicApiClient: { get: getMock },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authState,
}))

vi.mock('@/services/apiClient', () => ({
  getAuthToken: () => localStorage.getItem('auth_token'),
}))

import { fetchPublicFaceProfile, fetchPublicFaces, publicFaceName } from '../publicFacesApi'

describe('publicFacesApi — visiteur (lot B13)', () => {
  beforeEach(() => {
    getMock.mockReset()
    getMock.mockResolvedValue({ data: { data: [], meta: {} } })
    localStorage.clear()
    authState.isProducer = false
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('n\'envoie aucun en-tête d\'authentification pour un invité', async () => {
    await fetchPublicFaces()
    await fetchPublicFaceProfile('adjoua')

    expect(getMock.mock.calls[0]?.[1]).not.toHaveProperty('headers')
    expect(getMock.mock.calls[1]?.[1]).toBeUndefined()
  })

  it('joint le jeton de session à la liste et au profil pour un Producer connecté', async () => {
    localStorage.setItem('auth_token', '12|secret')
    authState.isProducer = true

    await fetchPublicFaces()
    await fetchPublicFaceProfile('adjoua')

    expect(getMock.mock.calls[0]?.[1]).toMatchObject({ headers: { Authorization: 'Bearer 12|secret' } })
    expect(getMock.mock.calls[1]?.[1]).toMatchObject({ headers: { Authorization: 'Bearer 12|secret' } })
  })

  it('n\'envoie aucun jeton pour une session non Producer (Face, admin)', async () => {
    localStorage.setItem('auth_token', '12|secret')
    authState.isProducer = false

    await fetchPublicFaces()
    await fetchPublicFaceProfile('adjoua')

    expect(getMock.mock.calls[0]?.[1]).not.toHaveProperty('headers')
    expect(getMock.mock.calls[1]?.[1]).toBeUndefined()
  })

  it('publicFaceName : nom complet seulement s\'il n\'est pas une initiale', () => {
    expect(publicFaceName({ prenom: 'Adjoua', nom: 'Kouassi' })).toBe('Adjoua Kouassi')
    expect(publicFaceName({ prenom: 'Adjoua', nom: 'K.' })).toBe('Adjoua')
    expect(publicFaceName({ prenom: 'Adjoua', nom: 'É.' })).toBe('Adjoua')
    expect(publicFaceName({ prenom: 'Adjoua', nom: null })).toBe('Adjoua')
  })
})
