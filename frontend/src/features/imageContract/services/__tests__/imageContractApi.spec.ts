import { describe, it, expect, vi, beforeEach } from 'vitest'

const mockPost = vi.fn()
const mockGet = vi.fn()
const mockGetCsrfCookie = vi.fn()

vi.mock('@/services/apiClient', () => ({
  default: {
    post: (...args: unknown[]) => mockPost(...args),
    get: (...args: unknown[]) => mockGet(...args),
  },
  getCsrfCookie: (...args: unknown[]) => mockGetCsrfCookie(...args),
}))

import { imageContractApi } from '../imageContractApi'

describe('imageContractApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGetCsrfCookie.mockResolvedValue(undefined)
  })

  it('charge le texte du contrat', async () => {
    const template = { version: 'v1', articles: [{ title: 'Objet', body: '...' }] }
    mockGet.mockResolvedValue({ data: template })

    const result = await imageContractApi.getTemplate()

    expect(mockGet).toHaveBeenCalledWith('/image-contract/template')
    expect(result).toEqual(template)
  })

  it('accepte aussi un gabarit enveloppé dans data', async () => {
    const template = { version: 'v1', articles: [] }
    mockGet.mockResolvedValue({ data: { data: template } })

    expect(await imageContractApi.getTemplate()).toEqual(template)
  })

  it('demande un code Producteur après le cookie CSRF', async () => {
    const reply = { data: { sent_to: 'a•••••@gmail.com', expires_at: '2026-11-04T10:10:00Z', retry_after: 60 } }
    mockPost.mockResolvedValue({ data: reply })

    const result = await imageContractApi.requestCode({ purpose: 'booking_request' })

    expect(mockGetCsrfCookie).toHaveBeenCalledTimes(1)
    expect(mockPost).toHaveBeenCalledWith('/image-contract/code', { purpose: 'booking_request' })
    expect(result).toEqual(reply)
  })

  it('demande un code Face avec le booking et les informations du parent', async () => {
    mockPost.mockResolvedValue({ data: { data: { sent_to: 'm•••@gmail.com', expires_at: 'x', retry_after: 60 } } })

    await imageContractApi.requestCode({
      purpose: 'booking_accept',
      booking_id: 'b-1',
      guardian_name: 'Marie Dossou',
      guardian_relation: 'mere',
      guardian_email: 'marie@gmail.com',
    })

    expect(mockPost).toHaveBeenCalledWith('/image-contract/code', {
      purpose: 'booking_accept',
      booking_id: 'b-1',
      guardian_name: 'Marie Dossou',
      guardian_relation: 'mere',
      guardian_email: 'marie@gmail.com',
    })
  })

  it('télécharge le PDF en blob', async () => {
    const blob = new Blob(['%PDF'])
    mockGet.mockResolvedValue({ data: blob })

    const result = await imageContractApi.downloadPdf('b-1')

    expect(mockGet).toHaveBeenCalledWith('/bookings/b-1/image-contract/pdf', { responseType: 'blob' })
    expect(result).toBe(blob)
  })
})
