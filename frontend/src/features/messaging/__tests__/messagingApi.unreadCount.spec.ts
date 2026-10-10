import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import apiClient from '@/services/apiClient'
import { useMessagesUnreadStore } from '@/stores/messagesUnread'
import { messagingApi } from '../services/messagingApi'

vi.mock('@/plugins/echo', () => ({ echo: { socketId: () => '123.456' } }))

vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  getAuthToken: vi.fn(() => null),
}))

const get = vi.mocked(apiClient.get)
const post = vi.mocked(apiClient.post)

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('messagingApi — compteur « Messages » fourni par le serveur', () => {
  it.each([
    ['liste face', () => messagingApi.getConversations()],
    ['liste producteur', () => messagingApi.getProducerConversations()],
  ])('%s : applique meta.unread_conversations_count', async (_label, call) => {
    get.mockResolvedValue({ data: { data: [], meta: { unread_conversations_count: 4 } } })
    await call()
    expect(useMessagesUnreadStore().count).toBe(4)
  })

  it.each([
    ['fil face', () => messagingApi.getConversation('c1')],
    ['fil producteur', () => messagingApi.getProducerConversation('c1')],
  ])('%s : applique meta.unread_conversations_count (ouverture = lecture)', async (_label, call) => {
    get.mockResolvedValue({ data: { data: { id: 'c1' }, meta: { unread_conversations_count: 2 } } })
    await call()
    expect(useMessagesUnreadStore().count).toBe(2)
  })

  it('lecture : applique data.unread_conversations_count', async () => {
    useMessagesUnreadStore().setCount(5)
    post.mockResolvedValue({ data: { data: { marked: 3, unread_conversations_count: 4 } } })
    await messagingApi.markConversationRead('face', 'c1')
    expect(useMessagesUnreadStore().count).toBe(4)
  })

  it('réponse sans le champ : le compteur reste inchangé', async () => {
    useMessagesUnreadStore().setCount(5)
    get.mockResolvedValue({ data: { data: [], meta: { current_page: 1 } } })
    await messagingApi.getConversations()
    expect(useMessagesUnreadStore().count).toBe(5)
  })
})
