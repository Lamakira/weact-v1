import { describe, it, expect, vi, beforeEach } from 'vitest'

const mockPost = vi.fn()

vi.mock('@/services/apiClient', () => ({
  default: { post: (...args: unknown[]) => mockPost(...args), get: vi.fn(), put: vi.fn(), delete: vi.fn() },
  getCsrfCookie: vi.fn(),
}))

import { candidatureApi } from '../candidatureApi'

describe('candidatureApi : confirm et reconfirm avec signature du contrat d\'image', () => {
  beforeEach(() => {
    mockPost.mockReset()
    mockPost.mockResolvedValue({ data: { data: { id: 'c-1' }, message: 'ok' } })
  })

  const signature = { name: 'Aïcha Bello', code: '123456' }

  it('confirm : corps { signature } quand la Face signe', async () => {
    await candidatureApi.confirmCandidature('c-1', signature)
    expect(mockPost).toHaveBeenCalledWith('/face/candidatures/c-1/confirm', { signature })
  })

  it('confirm : corps vide sans signature (appel historique)', async () => {
    await candidatureApi.confirmCandidature('c-1')
    expect(mockPost).toHaveBeenCalledWith('/face/candidatures/c-1/confirm')
  })

  it('reconfirm : corps { signature } quand la Face signe', async () => {
    await candidatureApi.reconfirmCandidature('c-1', signature)
    expect(mockPost).toHaveBeenCalledWith('/face/candidatures/c-1/reconfirm', { signature })
  })

  it('reconfirm : corps vide sans signature (appel historique)', async () => {
    await candidatureApi.reconfirmCandidature('c-1')
    expect(mockPost).toHaveBeenCalledWith('/face/candidatures/c-1/reconfirm')
  })
})
