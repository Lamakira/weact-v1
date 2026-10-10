import { describe, it, expect, vi, beforeEach } from 'vitest'
import apiClient from '@/services/apiClient'
import { messagingApi } from '../services/messagingApi'

vi.mock('@/plugins/echo', () => ({ echo: { socketId: () => '123.456' } }))

vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  getAuthToken: vi.fn(() => null),
}))

const post = vi.mocked(apiClient.post)

beforeEach(() => {
  vi.clearAllMocks()
  post.mockResolvedValue({ data: { data: {} } })
})

describe('messagingApi — X-Socket-ID (toOthers côté serveur)', () => {
  it.each([
    ['face', () => messagingApi.sendMessage('c1', { content: 'Salut' }), '/face/conversations/c1/messages'],
    ['producer', () => messagingApi.sendProducerMessage('c1', { content: 'Salut' }), '/producer/conversations/c1/messages'],
    ['read face', () => messagingApi.markConversationRead('face', 'c1'), '/face/conversations/c1/read'],
    ['read producer', () => messagingApi.markConversationRead('producer', 'c1'), '/producer/conversations/c1/read'],
  ])('%s : envoie l\'en-tête X-Socket-ID', async (_label, call, url) => {
    await call()

    expect(post).toHaveBeenCalledTimes(1)
    expect(post.mock.calls[0]![0]).toBe(url)
    expect(post.mock.calls[0]![2]).toEqual({ headers: { 'X-Socket-ID': '123.456' } })
  })
})
