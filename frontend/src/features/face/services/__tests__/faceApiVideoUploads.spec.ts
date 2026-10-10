import { beforeEach, describe, expect, it, vi } from 'vitest'

const mockPost = vi.fn()
const mockGetCsrfCookie = vi.fn()

vi.mock('@/services/apiClient', () => ({
  default: {
    post: (...args: unknown[]) => mockPost(...args),
  },
  getCsrfCookie: (...args: unknown[]) => mockGetCsrfCookie(...args),
}))

import { faceApi } from '../faceApi'

describe('faceApi video uploads', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockPost.mockResolvedValue({ data: { data: { id: 'video-1' } } })
    mockGetCsrfCookie.mockResolvedValue(undefined)
  })

  it('uses a dedicated ten-minute timeout for a large deliverable upload', async () => {
    const video = new File(['video'], 'preuve.mp4', { type: 'video/mp4' })

    await faceApi.uploadDeliverable('shipment-1', video)

    const [url, payload, config] = mockPost.mock.calls[0]!
    expect(url).toBe('/face/shipments/shipment-1/deliverables')
    expect(payload).toBeInstanceOf(FormData)
    expect((payload as FormData).get('video')).toBe(video)
    expect((config as { timeout?: number }).timeout).toBe(600_000)
  })

  it('uses the same timeout for the presentation video upload', async () => {
    const video = new File(['video'], 'presentation.mp4', { type: 'video/mp4' })

    await faceApi.uploadPresentationVideo(video)

    const [url, payload, config] = mockPost.mock.calls[0]!
    expect(url).toBe('/face/presentation-video')
    expect((payload as FormData).get('video')).toBe(video)
    expect((config as { timeout?: number }).timeout).toBe(600_000)
  })

  it('uses the same timeout for a portfolio video upload', async () => {
    const video = new File(['video'], 'portfolio.mp4', { type: 'video/mp4' })

    await faceApi.uploadFaceVideo('acting', video)

    const [url, payload, config] = mockPost.mock.calls[0]!
    expect(url).toBe('/face/videos')
    expect((payload as FormData).get('type')).toBe('acting')
    expect((payload as FormData).get('video')).toBe(video)
    expect((config as { timeout?: number }).timeout).toBe(600_000)
  })
})
