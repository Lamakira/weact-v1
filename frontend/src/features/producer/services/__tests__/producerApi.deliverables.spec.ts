import { describe, it, expect, vi, beforeEach } from 'vitest'

const mockGet = vi.fn()

vi.mock('@/services/apiClient', () => ({
  default: { get: (...args: unknown[]) => mockGet(...args) },
  getCsrfCookie: vi.fn(),
}))

import { producerApi } from '../producerApi'

describe('producerApi.listDeliverablesToReview', () => {
  beforeEach(() => {
    mockGet.mockReset()
  })

  it('shares one request between simultaneous callers (sidebar badge + dashboard module)', async () => {
    let resolve!: (value: unknown) => void
    mockGet.mockReturnValue(new Promise((r) => { resolve = r }))

    const first = producerApi.listDeliverablesToReview()
    const second = producerApi.listDeliverablesToReview()
    resolve({ data: { data: [{ id: 'a' }] } })

    await expect(first).resolves.toEqual({ data: [{ id: 'a' }] })
    await expect(second).resolves.toEqual({ data: [{ id: 'a' }] })
    expect(mockGet).toHaveBeenCalledTimes(1)
  })

  it('fires a new request once the previous one has settled', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } })

    await producerApi.listDeliverablesToReview()
    await producerApi.listDeliverablesToReview()

    expect(mockGet).toHaveBeenCalledTimes(2)
  })

  it('does not keep a failed request cached', async () => {
    mockGet.mockRejectedValueOnce(new Error('network')).mockResolvedValueOnce({ data: { data: [] } })

    await expect(producerApi.listDeliverablesToReview()).rejects.toThrow('network')
    await expect(producerApi.listDeliverablesToReview()).resolves.toEqual({ data: [] })
    expect(mockGet).toHaveBeenCalledTimes(2)
  })
})
