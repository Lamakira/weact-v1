import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { CreateBookingData } from '../../types'

const mockPost = vi.fn()
const mockGetCsrfCookie = vi.fn()

vi.mock('@/services/apiClient', () => ({
  default: {
    post: (...args: unknown[]) => mockPost(...args),
    get: vi.fn(),
    delete: vi.fn(),
  },
  getCsrfCookie: (...args: unknown[]) => mockGetCsrfCookie(...args),
}))

import { bookingApi } from '../bookingApi'

function makeUgcData(overrides: Partial<CreateBookingData> = {}): CreateBookingData {
  return {
    face_id: 'face-uuid',
    type_contenu: 'UGC',
    type_compensation: 'product',
    nom_produit: 'Tenue Shade Fit',
    valeur_produit: 20000,
    ...overrides,
  }
}

describe('bookingApi.createBooking — FormData photos produit', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockPost.mockResolvedValue({ data: { data: { id: 'b-1' } } })
    mockGetCsrfCookie.mockResolvedValue(undefined)
  })

  it('sends plain JSON when no photo is attached (création inchangée)', async () => {
    await bookingApi.createBooking(makeUgcData())

    expect(mockPost).toHaveBeenCalledTimes(1)
    const [url, payload] = mockPost.mock.calls[0]!
    expect(url).toBe('/bookings')
    expect(payload).not.toBeInstanceOf(FormData)
    expect(payload).toMatchObject({ nom_produit: 'Tenue Shade Fit', valeur_produit: 20000 })
    expect('product_photos' in (payload as Record<string, unknown>)).toBe(false)
  })

  it('switches to multipart FormData when photos are attached', async () => {
    const photo1 = new File(['a'], 'a.jpg', { type: 'image/jpeg' })
    const photo2 = new File(['b'], 'b.png', { type: 'image/png' })

    await bookingApi.createBooking(makeUgcData({ product_photos: [photo1, photo2] }))

    const [url, payload, config] = mockPost.mock.calls[0]!
    expect(url).toBe('/bookings')
    expect(payload).toBeInstanceOf(FormData)

    const formData = payload as FormData
    // Champs scalaires stringifiés (les FormRequests Laravel valident les strings numériques).
    expect(formData.get('face_id')).toBe('face-uuid')
    expect(formData.get('type_contenu')).toBe('UGC')
    expect(formData.get('valeur_produit')).toBe('20000')
    // Fichiers sous product_photos[] (array côté Laravel).
    expect(formData.getAll('product_photos[]')).toEqual([photo1, photo2])
    expect((config as { headers: Record<string, string> }).headers['Content-Type']).toBe(
      'multipart/form-data',
    )
  })

  it('omits undefined fields from the FormData payload', async () => {
    const photo = new File(['a'], 'a.jpg', { type: 'image/jpeg' })

    await bookingApi.createBooking(makeUgcData({ product_photos: [photo] }))

    const formData = mockPost.mock.calls[0]![1] as FormData
    expect(formData.has('nombre_videos')).toBe(false)
    expect(formData.has('montant_remuneration')).toBe(false)
    expect(formData.has('date_debut')).toBe(false)
  })
})
