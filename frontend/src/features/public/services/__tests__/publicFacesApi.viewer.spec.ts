import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

vi.mock('../apiClient', () => ({
  publicApiClient: { get: getMock },
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

  it('joint le jeton de session à la liste et au profil quand l\'utilisateur est connecté', async () => {
    localStorage.setItem('auth_token', '12|secret')

    await fetchPublicFaces()
    await fetchPublicFaceProfile('adjoua')

    expect(getMock.mock.calls[0]?.[1]).toMatchObject({ headers: { Authorization: 'Bearer 12|secret' } })
    expect(getMock.mock.calls[1]?.[1]).toMatchObject({ headers: { Authorization: 'Bearer 12|secret' } })
  })

  it('publicFaceName : nom complet seulement s\'il n\'est pas une initiale', () => {
    expect(publicFaceName({ prenom: 'Adjoua', nom: 'Kouassi' })).toBe('Adjoua Kouassi')
    expect(publicFaceName({ prenom: 'Adjoua', nom: 'K.' })).toBe('Adjoua')
    expect(publicFaceName({ prenom: 'Adjoua', nom: 'É.' })).toBe('Adjoua')
    expect(publicFaceName({ prenom: 'Adjoua', nom: null })).toBe('Adjoua')
  })
})
