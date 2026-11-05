import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { CreateBookingData } from '../../types'

const mockPost = vi.fn()
const mockGet = vi.fn()
const mockGetCsrfCookie = vi.fn()

vi.mock('@/services/apiClient', () => ({
  default: {
    post: (...args: unknown[]) => mockPost(...args),
    get: (...args: unknown[]) => mockGet(...args),
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

describe('bookingApi.contestBooking', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockPost.mockResolvedValue({ data: { data: { id: 'b-1' } } })
    mockGetCsrfCookie.mockResolvedValue(undefined)
  })

  it('posts the message to the contest endpoint after fetching the CSRF cookie', async () => {
    const result = await bookingApi.contestBooking('booking-uuid-9', 'J\'étais présente sur place.')

    expect(mockGetCsrfCookie).toHaveBeenCalledTimes(1)
    expect(mockPost).toHaveBeenCalledWith('/bookings/booking-uuid-9/contest', {
      message: 'J\'étais présente sur place.',
    })
    expect(result).toEqual({ data: { id: 'b-1' } })
  })
})

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

describe('bookingApi.getBookings — tri et pagination serveur', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGet.mockResolvedValue({ data: { data: [], meta: {} } })
  })

  it('keeps the historical query (page + status) when no option is given', async () => {
    await bookingApi.getBookings(2, 'active')
    expect(mockGet).toHaveBeenCalledWith('/bookings?page=2&status=active')
  })

  it('adds sort, direction and per_page when provided', async () => {
    await bookingApi.getBookings(1, '', { sort: 'montant', direction: 'desc', perPage: 25 })
    expect(mockGet).toHaveBeenCalledWith('/bookings?page=1&sort=montant&direction=desc&per_page=25')
  })

  it('omits direction when no sort is set', async () => {
    await bookingApi.getBookings(1, 'pending', { sort: null, direction: 'desc', perPage: 15 })
    expect(mockGet).toHaveBeenCalledWith('/bookings?page=1&status=pending&per_page=15')
  })
})

describe('bookingApi.acceptBooking : signature du contrat d\'image', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockPost.mockResolvedValue({ data: { data: { id: 'b-1' } } })
    mockGetCsrfCookie.mockResolvedValue(undefined)
  })

  it('poste sans corps quand aucune signature n\'est fournie (acceptation inchangée)', async () => {
    await bookingApi.acceptBooking('b-1')

    expect(mockPost).toHaveBeenCalledTimes(1)
    expect(mockPost.mock.calls[0]).toEqual(['/bookings/b-1/accept'])
  })

  it('envoie la signature (nom et code) dans le corps', async () => {
    await bookingApi.acceptBooking('b-1', { name: 'Aïcha Bello', code: '123456' })

    expect(mockPost).toHaveBeenCalledWith('/bookings/b-1/accept', {
      signature: { name: 'Aïcha Bello', code: '123456' },
    })
  })
})

describe('bookingApi.createBooking : autorisation d\'image', () => {
  const imageTerms = {
    finalite: 'Publicité',
    supports: ['social', 'web'] as ('social' | 'web')[],
    territoire: 'benin' as const,
    duree_annees: 3 as const,
  }
  const signature = { name: 'Jean Producteur', code: '654321' }

  beforeEach(() => {
    vi.clearAllMocks()
    mockPost.mockResolvedValue({ data: { data: { id: 'b-1' } } })
    mockGetCsrfCookie.mockResolvedValue(undefined)
  })

  it('envoie image_terms et signature en JSON sans photo', async () => {
    await bookingApi.createBooking(makeUgcData({ image_terms: imageTerms, signature }))

    const payload = mockPost.mock.calls[0]![1] as Record<string, unknown>
    expect(payload.image_terms).toEqual(imageTerms)
    expect(payload.signature).toEqual(signature)
  })

  it('n\'ajoute aucune clé de contrat quand le drapeau est faux', async () => {
    await bookingApi.createBooking(makeUgcData())

    const payload = mockPost.mock.calls[0]![1] as Record<string, unknown>
    expect('image_terms' in payload).toBe(false)
    expect('signature' in payload).toBe(false)
  })

  it('aplatit image_terms et signature en notation à crochets dans le FormData', async () => {
    const photo = new File(['a'], 'a.jpg', { type: 'image/jpeg' })

    await bookingApi.createBooking(makeUgcData({ product_photos: [photo], image_terms: imageTerms, signature }))

    const formData = mockPost.mock.calls[0]![1] as FormData
    expect(formData.get('image_terms[finalite]')).toBe('Publicité')
    expect(formData.getAll('image_terms[supports][]')).toEqual(['social', 'web'])
    expect(formData.get('image_terms[territoire]')).toBe('benin')
    expect(formData.get('image_terms[duree_annees]')).toBe('3')
    expect(formData.get('signature[name]')).toBe('Jean Producteur')
    expect(formData.get('signature[code]')).toBe('654321')
    expect(formData.has('image_terms')).toBe(false)
  })
})
