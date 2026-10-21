import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import apiClient from '@/services/apiClient'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'

vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn() },
}))

// Registre minimal du canal utilisateur : le store de notifications possède le vrai canal.
const channel = vi.hoisted(() => ({
  listeners: new Map<string, (payload: unknown) => void>(),
  off: null as unknown as ReturnType<typeof vi.fn>,
}))
vi.mock('@/stores/notification', () => ({
  useNotificationStore: () => ({
    onUserChannelEvent: (event: string, cb: (payload: unknown) => void) => {
      channel.listeners.set(event, cb)
      return channel.off
    },
  }),
}))

import { useActionCountsStore } from '../actionCounts'

function respondWith(data: Record<string, number>) {
  vi.mocked(apiClient.get).mockResolvedValue({ data: { data } })
}

describe('useActionCountsStore', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-20T10:00:00Z'))
    setActivePinia(createPinia())
    vi.clearAllMocks()
    resetAllSharedCachedResources()
    channel.listeners.clear()
    channel.off = vi.fn()
  })

  afterEach(() => {
    useActionCountsStore().$reset()
    vi.useRealTimers()
  })

  it('démarre à 0 pour tous les compteurs', () => {
    expect(useActionCountsStore().counts).toEqual({
      bookings: 0,
      candidatures: 0,
      missions: 0,
      livrables: 0,
      messages: 0,
    })
  })

  it('start charge /me/action-counts et complète les champs absents du rôle par 0', async () => {
    respondWith({ bookings: 2, candidatures: 1, livrables: 3, messages: 4 })
    const store = useActionCountsStore()
    store.start()
    await flushPromises()

    expect(apiClient.get).toHaveBeenCalledWith('/me/action-counts')
    expect(store.counts).toEqual({ bookings: 2, candidatures: 1, missions: 0, livrables: 3, messages: 4 })
  })

  it('start est idempotent (un seul chargement, une seule série d’abonnements)', async () => {
    respondWith({ bookings: 1 })
    const store = useActionCountsStore()
    store.start()
    store.start()
    await flushPromises()
    expect(apiClient.get).toHaveBeenCalledTimes(1)
    expect(channel.listeners.size).toBe(2)
  })

  it('recharge sur .notification.created et .conversation.updated', async () => {
    respondWith({ bookings: 1 })
    const store = useActionCountsStore()
    store.start()
    await flushPromises()
    expect([...channel.listeners.keys()].sort()).toEqual(['.conversation.updated', '.notification.created'])

    respondWith({ bookings: 5 })
    channel.listeners.get('.notification.created')?.({})
    await flushPromises()
    expect(apiClient.get).toHaveBeenCalledTimes(2)
    expect(store.counts.bookings).toBe(5)

    respondWith({ bookings: 6 })
    channel.listeners.get('.conversation.updated')?.({})
    await flushPromises()
    expect(apiClient.get).toHaveBeenCalledTimes(3)
    expect(store.counts.bookings).toBe(6)
  })

  it('un événement reçu pendant un chargement en cours déclenche un rechargement ensuite', async () => {
    let resolveFirst: (v: unknown) => void = () => {}
    vi.mocked(apiClient.get)
      .mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve }))
      .mockResolvedValueOnce({ data: { data: { bookings: 9 } } })
    const store = useActionCountsStore()
    store.start()
    channel.listeners.get('.notification.created')?.({})
    resolveFirst({ data: { data: { bookings: 1 } } })
    await flushPromises()

    expect(apiClient.get).toHaveBeenCalledTimes(2)
    expect(store.counts.bookings).toBe(9)
  })

  it('recharge au retour sur l’onglet, au plus toutes les 30 s', async () => {
    respondWith({ bookings: 1 })
    const store = useActionCountsStore()
    store.start()
    await flushPromises()
    expect(apiClient.get).toHaveBeenCalledTimes(1)

    vi.advanceTimersByTime(10_000)
    window.dispatchEvent(new Event('focus'))
    await flushPromises()
    expect(apiClient.get).toHaveBeenCalledTimes(1)

    vi.advanceTimersByTime(21_000)
    window.dispatchEvent(new Event('focus'))
    await flushPromises()
    expect(apiClient.get).toHaveBeenCalledTimes(2)
  })

  it('une erreur réseau laisse les compteurs inchangés et ne lève pas', async () => {
    respondWith({ bookings: 3 })
    const store = useActionCountsStore()
    await store.refresh()
    vi.mocked(apiClient.get).mockRejectedValue(new Error('boom'))
    await expect(store.refresh()).resolves.toBeUndefined()
    expect(store.counts.bookings).toBe(3)
  })

  it('stop désabonne le canal et le focus', async () => {
    respondWith({ bookings: 1 })
    const store = useActionCountsStore()
    store.start()
    await flushPromises()
    store.stop()

    expect(channel.off).toHaveBeenCalledTimes(2)
    vi.advanceTimersByTime(60_000)
    window.dispatchEvent(new Event('focus'))
    await flushPromises()
    expect(apiClient.get).toHaveBeenCalledTimes(1)
  })

  it('le reset générique des caches (déconnexion) remet les compteurs à zéro', async () => {
    respondWith({ bookings: 7, messages: 2 })
    const store = useActionCountsStore()
    await store.refresh()
    expect(store.counts.bookings).toBe(7)

    resetAllSharedCachedResources()
    expect(store.counts.bookings).toBe(0)
    expect(store.counts.messages).toBe(0)
  })

  it('$reset remet à zéro et arrête le suivi', async () => {
    respondWith({ bookings: 7 })
    const store = useActionCountsStore()
    store.start()
    await flushPromises()
    store.$reset()

    expect(store.counts.bookings).toBe(0)
    expect(channel.off).toHaveBeenCalledTimes(2)
  })
})
