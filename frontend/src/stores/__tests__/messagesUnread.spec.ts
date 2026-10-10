import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import apiClient from '@/services/apiClient'

vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn() },
}))

// Registre minimal du canal utilisateur : le store de notifications possède le vrai canal.
const channel = vi.hoisted(() => ({
  isSubscribed: false,
  listeners: new Map<string, (payload: unknown) => void>(),
  off: null as unknown as ReturnType<typeof vi.fn>,
}))
vi.mock('@/stores/notification', () => ({
  useNotificationStore: () => ({
    get isSubscribed() {
      return channel.isSubscribed
    },
    onUserChannelEvent: (event: string, cb: (payload: unknown) => void) => {
      channel.listeners.set(event, cb)
      return channel.off
    },
  }),
}))

import { useMessagesUnreadStore, syncMessagesUnreadFromServer } from '../messagesUnread'

function respondWith(count: number) {
  vi.mocked(apiClient.get).mockResolvedValue({ data: { data: { count } } })
}

function emit(payload: unknown) {
  channel.listeners.get('.conversation.updated')?.(payload)
}

describe('useMessagesUnreadStore', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-10T10:00:00Z'))
    setActivePinia(createPinia())
    vi.clearAllMocks()
    channel.isSubscribed = false
    channel.listeners.clear()
    channel.off = vi.fn()
  })

  afterEach(() => {
    useMessagesUnreadStore().$reset()
    vi.useRealTimers()
  })

  it('démarre à 0', () => {
    expect(useMessagesUnreadStore().count).toBe(0)
  })

  it('fetchCount appelle l’endpoint du rôle et pose le compteur', async () => {
    respondWith(4)
    const store = useMessagesUnreadStore()
    await store.fetchCount('producer')
    expect(apiClient.get).toHaveBeenCalledWith('/producer/conversations/unread-count')
    expect(store.count).toBe(4)

    respondWith(2)
    await store.fetchCount('face')
    expect(apiClient.get).toHaveBeenLastCalledWith('/face/conversations/unread-count')
    expect(store.count).toBe(2)
  })

  it('fetchCount laisse le compteur inchangé et ne lève pas en cas d’erreur', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    const store = useMessagesUnreadStore()
    store.setCount(3)
    vi.mocked(apiClient.get).mockRejectedValue(new Error('boom'))
    await expect(store.fetchCount('face')).resolves.toBeUndefined()
    expect(store.count).toBe(3)
  })

  it('setCount borne à 0 minimum', () => {
    const store = useMessagesUnreadStore()
    store.setCount(5)
    expect(store.count).toBe(5)
    store.setCount(-2)
    expect(store.count).toBe(0)
  })

  it('une réponse de fetch périmée n’écrase pas une valeur poussée entre-temps', async () => {
    let resolve!: (v: unknown) => void
    vi.mocked(apiClient.get).mockReturnValue(new Promise((r) => { resolve = r }) as never)
    const store = useMessagesUnreadStore()
    const pending = store.fetchCount('face')
    store.setCount(7) // événement temps réel plus récent
    resolve({ data: { data: { count: 1 } } })
    await pending
    expect(store.count).toBe(7)
  })

  it('start : un seul fetch au boot, idempotent pour un même rôle', async () => {
    respondWith(2)
    const store = useMessagesUnreadStore()
    store.start('face')
    store.start('face')
    await vi.advanceTimersByTimeAsync(0)
    expect(apiClient.get).toHaveBeenCalledTimes(1)
    expect(store.count).toBe(2)
  })

  it('met à jour depuis `conversation.updated` via unread_conversations_count, sans requête', async () => {
    respondWith(1)
    const store = useMessagesUnreadStore()
    store.start('face')
    await vi.advanceTimersByTimeAsync(0)
    vi.mocked(apiClient.get).mockClear()

    emit({ conversation_id: 'x', unread_count: 5, unread_conversations_count: 3 })
    expect(store.count).toBe(3)
    // Payload sans le champ (ancien serveur) : ignoré
    emit({ conversation_id: 'x', unread_count: 5 })
    expect(store.count).toBe(3)
    emit({ conversation_id: 'x', unread_conversations_count: 0 })
    expect(store.count).toBe(0)
    expect(apiClient.get).not.toHaveBeenCalled()
  })

  it('focus : refetch au plus une fois par 30 s', async () => {
    respondWith(1)
    const store = useMessagesUnreadStore()
    store.start('face')
    await vi.advanceTimersByTimeAsync(0)
    expect(apiClient.get).toHaveBeenCalledTimes(1)

    window.dispatchEvent(new Event('focus'))
    expect(apiClient.get).toHaveBeenCalledTimes(1) // < 30 s après le boot

    vi.setSystemTime(new Date('2026-10-10T10:00:31Z'))
    window.dispatchEvent(new Event('focus'))
    expect(apiClient.get).toHaveBeenCalledTimes(2)

    window.dispatchEvent(new Event('focus'))
    expect(apiClient.get).toHaveBeenCalledTimes(2)
  })

  it('repli par polling uniquement tant que le temps réel n’est pas abonné', async () => {
    respondWith(1)
    const store = useMessagesUnreadStore()
    store.start('producer')
    await vi.advanceTimersByTimeAsync(0)
    expect(apiClient.get).toHaveBeenCalledTimes(1)

    channel.isSubscribed = true
    await vi.advanceTimersByTimeAsync(120_000)
    expect(apiClient.get).toHaveBeenCalledTimes(1) // temps réel OK : aucun polling

    channel.isSubscribed = false
    await vi.advanceTimersByTimeAsync(60_000)
    expect(apiClient.get).toHaveBeenCalledTimes(2)
  })

  it('stop retire l’écouteur, le focus et le polling mais garde la valeur', async () => {
    respondWith(2)
    const store = useMessagesUnreadStore()
    store.start('face')
    await vi.advanceTimersByTimeAsync(0)
    vi.mocked(apiClient.get).mockClear()

    store.stop()
    expect(channel.off).toHaveBeenCalledOnce()
    vi.setSystemTime(new Date('2026-10-10T10:05:00Z'))
    window.dispatchEvent(new Event('focus'))
    await vi.advanceTimersByTimeAsync(180_000)
    expect(apiClient.get).not.toHaveBeenCalled()
    expect(store.count).toBe(2)
  })

  it('$reset remet à 0 et arrête le suivi (logout)', async () => {
    respondWith(6)
    const store = useMessagesUnreadStore()
    store.start('face')
    await vi.advanceTimersByTimeAsync(0)
    expect(store.count).toBe(6)

    store.$reset()
    expect(store.count).toBe(0)
    expect(channel.off).toHaveBeenCalledOnce()

    // Un nouveau compte peut redémarrer (nouveau fetch)
    respondWith(1)
    store.start('face')
    await vi.advanceTimersByTimeAsync(0)
    expect(store.count).toBe(1)
  })

  it('syncMessagesUnreadFromServer applique une valeur numérique et ignore le reste', () => {
    const store = useMessagesUnreadStore()
    syncMessagesUnreadFromServer(4)
    expect(store.count).toBe(4)
    syncMessagesUnreadFromServer(undefined)
    syncMessagesUnreadFromServer('3')
    expect(store.count).toBe(4)
  })
})
