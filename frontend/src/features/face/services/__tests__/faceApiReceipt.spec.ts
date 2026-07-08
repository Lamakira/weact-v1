import { describe, it, expect, vi, beforeEach } from 'vitest'

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

import { faceApi } from '../faceApi'

/**
 * Spec réception : confirmShipmentReceipt envoie les 1-2 photos du produit reçu
 * en FormData multipart sous `reception_photos[]` (calque createBooking/uploadDeliverable).
 */
describe('faceApi.confirmShipmentReceipt — FormData photos de réception', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockPost.mockResolvedValue({ data: { data: { id: 's-1', tunnel_status: 'received' } } })
    mockGetCsrfCookie.mockResolvedValue(undefined)
  })

  it('posts the reception photos as multipart FormData', async () => {
    const photo1 = new File(['a'], 'reception-1.jpg', { type: 'image/jpeg' })
    const photo2 = new File(['b'], 'reception-2.png', { type: 'image/png' })

    await faceApi.confirmShipmentReceipt('shipment-uuid-1', [photo1, photo2])

    expect(mockGetCsrfCookie).toHaveBeenCalledOnce()

    const [url, payload, config] = mockPost.mock.calls[0]!
    expect(url).toBe('/face/shipments/shipment-uuid-1/confirm-receipt')
    expect(payload).toBeInstanceOf(FormData)

    const formData = payload as FormData
    expect(formData.getAll('reception_photos[]')).toEqual([photo1, photo2])
    expect((config as { headers: Record<string, string> }).headers['Content-Type']).toBe(
      'multipart/form-data',
    )
  })
})
