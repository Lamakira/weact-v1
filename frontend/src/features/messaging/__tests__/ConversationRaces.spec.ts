import { describe, it, expect, vi, beforeEach } from 'vitest'
import { messagingApi } from '../services/messagingApi'
import { useConversation } from '../composables/useConversation'
import { useProducerConversation } from '../composables/useProducerConversation'
import { useConversationsList } from '../composables/useConversationsList'
import { applyConversationUpdate } from '../utils/conversationList'
import { makeConversation, makeListItem, makeMessage } from './fixtures'
import type { Conversation, ConversationUpdatedBroadcast } from '../types'

vi.mock('../services/messagingApi', () => ({
  messagingApi: {
    getConversation: vi.fn(),
    getProducerConversation: vi.fn(),
    getConversations: vi.fn(),
    getProducerConversations: vi.fn(),
  },
}))

const api = vi.mocked(messagingApi)

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

const conv = (id: string, name: string): Conversation =>
  makeConversation('face', {
    id,
    other_participant: { ...makeListItem().other_participant, name },
  })

beforeEach(() => vi.clearAllMocks())

describe.each([
  ['Face', useConversation, 'getConversation'],
  ['Producteur', useProducerConversation, 'getProducerConversation'],
] as const)('%s — réponses périmées', (_label, composable, apiName) => {
  it('une resynchronisation de A qui revient après l\'ouverture de B n\'écrase pas B', async () => {
    const state = composable()
    api[apiName].mockResolvedValueOnce({ data: conv('A', 'Alice') })
    await state.loadConversation('A')

    const staleSync = deferred<{ data: Conversation }>()
    api[apiName].mockReturnValueOnce(staleSync.promise)
    const syncing = state.syncConversation('A') // poll de A en vol

    state.reset() // l'utilisateur ouvre B
    api[apiName].mockResolvedValueOnce({ data: conv('B', 'Bruno') })
    await state.loadConversation('B')

    staleSync.resolve({ data: conv('A', 'Alice') }) // la réponse de A arrive en retard
    await syncing

    expect(state.conversation.value?.id).toBe('B')
    expect(state.otherParticipant.value?.name).toBe('Bruno')
  })

  it('un chargement initial de A qui revient après l\'ouverture de B est ignoré', async () => {
    const state = composable()
    const slowA = deferred<{ data: Conversation }>()
    api[apiName].mockReturnValueOnce(slowA.promise)
    const loadingA = state.loadConversation('A')

    state.reset()
    api[apiName].mockResolvedValueOnce({ data: conv('B', 'Bruno') })
    await state.loadConversation('B')

    slowA.resolve({ data: conv('A', 'Alice') })
    expect(await loadingA).toBe(false)

    expect(state.conversation.value?.id).toBe('B')
    expect(state.isLoading.value).toBe(false)
  })

  it('une réponse dont l\'id ne correspond pas à la demande est ignorée', async () => {
    const state = composable()
    api[apiName].mockResolvedValueOnce({ data: conv('A', 'Alice') })
    await state.loadConversation('A')

    api[apiName].mockResolvedValueOnce({ data: conv('Z', 'Zoé') })
    await state.syncConversation('A')

    expect(state.conversation.value?.id).toBe('A')
  })

  it('un 403/404 pendant la resynchronisation pose l\'état d\'erreur existant', async () => {
    const state = composable()
    api[apiName].mockResolvedValueOnce({ data: conv('A', 'Alice') })
    await state.loadConversation('A')

    api[apiName].mockRejectedValueOnce({ response: { status: 403 } })
    await state.syncConversation('A')

    expect(state.error.value).toBe("Vous n'avez pas accès à cette conversation")
  })

  it('reset() libère l\'état de chargement d\'un fil abandonné', async () => {
    const state = composable()
    api[apiName].mockReturnValueOnce(new Promise(() => {}))
    void state.loadConversation('A')
    expect(state.isLoading.value).toBe(true)

    state.reset()

    expect(state.isLoading.value).toBe(false)
  })

  it('markReceivedMessagesRead ne touche que les messages reçus', async () => {
    const state = composable()
    api[apiName].mockResolvedValueOnce({
      data: makeConversation('face', {
        id: 'A',
        messages: [makeMessage({ id: 1 }), makeMessage({ id: 2, is_own_message: true })],
      }),
    })
    await state.loadConversation('A')

    state.markReceivedMessagesRead('2030-10-09T11:00:00+00:00')

    expect(state.messages.value[0]!.read_at).toBe('2030-10-09T11:00:00+00:00')
    expect(state.messages.value[1]!.read_at).toBeNull()
  })
})

describe('liste — synchronisation silencieuse', () => {
  it('conserve les pages chargées via « charger plus » et dédoublonne par uuid', async () => {
    const list = useConversationsList()
    api.getConversations.mockResolvedValueOnce({
      data: [makeListItem({ id: 'p1-a' }), makeListItem({ id: 'p1-b' })],
      meta: { current_page: 1, last_page: 2, per_page: 2, total: 3 },
    })
    await list.loadConversations()
    api.getConversations.mockResolvedValueOnce({
      data: [makeListItem({ id: 'p2-a' })],
      meta: { current_page: 2, last_page: 2, per_page: 2, total: 3 },
    })
    await list.loadMoreConversations()

    api.getConversations.mockResolvedValueOnce({
      data: [makeListItem({ id: 'p1-b', unread_count: 3 }), makeListItem({ id: 'p1-a' })],
      meta: { current_page: 1, last_page: 2, per_page: 2, total: 3 },
    })
    await list.syncConversations()

    expect(list.conversations.value.map((c) => c.id)).toEqual(['p1-b', 'p1-a', 'p2-a'])
    expect(list.conversations.value[0]!.unread_count).toBe(3)
    expect(list.meta.value?.current_page).toBe(2)
  })
})

describe('applyConversationUpdate — désordre', () => {
  const update = (createdAt: string, content: string): ConversationUpdatedBroadcast => ({
    conversation_id: 'c1',
    latest_message: { id: 1, content, sender_id: 2, sender_name: 'X', created_at: createdAt },
    unread_count: 1,
    updated_at: createdAt,
  })

  it('ignore une mise à jour plus ancienne que le dernier message affiché', () => {
    const items = [makeListItem({ id: 'c1' })]
    applyConversationUpdate(items, update('2030-10-09T12:00:00+00:00', 'récent'), 1)

    const known = applyConversationUpdate(items, update('2030-10-09T11:00:00+00:00', 'ancien'), 1)

    expect(known).toBe(true) // conversation connue : pas de rechargement
    expect(items[0]!.latest_message?.content).toBe('récent')
  })

  it('applique une mise à jour plus récente ou simultanée', () => {
    const items = [makeListItem({ id: 'c1' })]
    applyConversationUpdate(items, update('2030-10-09T12:00:00+00:00', 'a'), 1)
    applyConversationUpdate(items, update('2030-10-09T12:00:00+00:00', 'b'), 1)
    expect(items[0]!.latest_message?.content).toBe('b')
  })
})
