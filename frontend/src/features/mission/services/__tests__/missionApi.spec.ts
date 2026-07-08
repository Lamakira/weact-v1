import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { CreateMissionData } from '../../types'

const mockPost = vi.fn()
const mockGetCsrfCookie = vi.fn()

vi.mock('@/services/apiClient', () => ({
  default: {
    post: (...args: unknown[]) => mockPost(...args),
    get: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
  getCsrfCookie: (...args: unknown[]) => mockGetCsrfCookie(...args),
}))

import { missionApi } from '../missionApi'

function makeUgcData(overrides: Partial<CreateMissionData> = {}): CreateMissionData {
  return {
    titre: 'Appel UGC — Unboxing Tenue Shade Fit',
    description: 'Unboxing + avis de notre nouvelle tenue.',
    profil_recherche: 'Créatrices lifestyle',
    date_limite_candidature: '2026-08-01',
    nombre_faces_voulu: 3,
    type_mission: 'ugc',
    genre_voulu: 'femme',
    type_compensation: 'product',
    nom_produit: 'Tenue Shade Fit',
    valeur_produit: 20000,
    ...overrides,
  }
}

describe('missionApi.createMission — FormData photos produit', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockPost.mockResolvedValue({ data: { data: { id: 'm-1' } } })
    mockGetCsrfCookie.mockResolvedValue(undefined)
  })

  it('sends plain JSON when no photo is attached (création inchangée)', async () => {
    await missionApi.createMission(makeUgcData())

    const [url, payload] = mockPost.mock.calls[0]!
    expect(url).toBe('/producer/missions')
    expect(payload).not.toBeInstanceOf(FormData)
    expect(payload).toMatchObject({ nom_produit: 'Tenue Shade Fit' })
    expect('product_photos' in (payload as Record<string, unknown>)).toBe(false)
  })

  it('switches to multipart FormData when photos are attached', async () => {
    const photo = new File(['a'], 'produit.jpg', { type: 'image/jpeg' })

    await missionApi.createMission(makeUgcData({ product_photos: [photo] }))

    const [url, payload, config] = mockPost.mock.calls[0]!
    expect(url).toBe('/producer/missions')
    expect(payload).toBeInstanceOf(FormData)

    const formData = payload as FormData
    expect(formData.get('titre')).toBe('Appel UGC — Unboxing Tenue Shade Fit')
    expect(formData.get('valeur_produit')).toBe('20000')
    expect(formData.get('nombre_faces_voulu')).toBe('3')
    expect(formData.getAll('product_photos[]')).toEqual([photo])
    expect((config as { headers: Record<string, string> }).headers['Content-Type']).toBe(
      'multipart/form-data',
    )
  })

  it('omits undefined optional fields from the FormData payload', async () => {
    const photo = new File(['a'], 'produit.jpg', { type: 'image/jpeg' })

    await missionApi.createMission(makeUgcData({ product_photos: [photo] }))

    const formData = mockPost.mock.calls[0]![1] as FormData
    expect(formData.has('budget')).toBe(false)
    expect(formData.has('date_tournage')).toBe(false)
    expect(formData.has('lieu')).toBe(false)
  })
})
