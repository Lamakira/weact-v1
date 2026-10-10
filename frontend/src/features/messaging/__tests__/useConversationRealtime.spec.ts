import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, h, nextTick, ref, type Ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { channels, fakeEcho, resetFakeEcho } from './fakeEcho'
import { useConversationRealtime } from '../composables/useConversationRealtime'
import { useConversationListRealtime } from '../composables/useConversationListRealtime'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/plugins/echo', async () => {
  const { fakeEcho } = await import('./fakeEcho')
  return { echo: fakeEcho }
})

vi.mock('@/stores/auth', async () => {
  const { reactive } = await import('vue')
  const state = reactive<{ user: { id: number } | null }>({ user: { id: 1 } })
  return { useAuthStore: () => state }
})

const auth = useAuthStore() as unknown as { user: { id: number } | null }

function mountThread(uuid: Ref<string | null>, handlers = {}) {
  const calls = {
    onMessage: vi.fn(),
    onRead: vi.fn(),
    poll: vi.fn().mockResolvedValue(undefined),
    ...handlers,
  }
  const wrapper = mount(
    defineComponent({
      setup() {
        useConversationRealtime(uuid, 'face', calls)
        return () => h('div')
      },
    }),
  )
  return { wrapper, calls }
}

beforeEach(() => {
  resetFakeEcho()
  auth.user = { id: 1 }
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useConversationRealtime', () => {
  it('s\'abonne au canal du fil ouvert et relaie les messages', async () => {
    const uuid = ref<string | null>('conv-1')
    const { calls } = mountThread(uuid)
    await flushPromises()

    const channel = channels.get('conversation.conv-1')!
    expect(fakeEcho.private).toHaveBeenCalledWith('conversation.conv-1')
    channel.emit('.message.sent', { id: 5 })
    expect(calls.onMessage).toHaveBeenCalledWith({ id: 5 })
  })

  it('relaie les accusés de lecture de l\'autre rôle et ignore ceux de son propre rôle', async () => {
    const { calls } = mountThread(ref('conv-1'))
    await flushPromises()
    const channel = channels.get('conversation.conv-1')!

    channel.emit('.messages.read', { reader_role: 'producer', last_read_message_id: 3 })
    channel.emit('.messages.read', { reader_role: 'face', last_read_message_id: 4 })

    expect(calls.onRead).toHaveBeenCalledTimes(1)
    expect(calls.onRead.mock.calls[0]![0].last_read_message_id).toBe(3)
  })

  it('quitte le canal au changement de fil et s\'abonne au nouveau', async () => {
    const uuid = ref<string | null>('conv-1')
    mountThread(uuid)
    await flushPromises()

    uuid.value = 'conv-2'
    await flushPromises()

    expect(fakeEcho.leave).toHaveBeenCalledWith('conversation.conv-1')
    expect(channels.has('conversation.conv-2')).toBe(true)
  })

  it('ne crée aucun canal si le fil est quitté avant la fin du chargement d\'Echo (garde de génération)', async () => {
    const uuid = ref<string | null>('conv-1')
    const { wrapper, calls } = mountThread(uuid)
    wrapper.unmount()
    await flushPromises()
    await flushPromises()

    expect(fakeEcho.private).not.toHaveBeenCalled()
    expect(calls.onMessage).not.toHaveBeenCalled()
  })

  it('ignore une réponse d\'Echo périmée après un changement rapide de fil', async () => {
    const uuid = ref<string | null>('conv-1')
    const { calls } = mountThread(uuid)
    uuid.value = 'conv-2'
    await vi.waitFor(() => expect(channels.has('conversation.conv-2')).toBe(true))
    await flushPromises()

    expect(channels.has('conversation.conv-1')).toBe(false)
    channels.get('conversation.conv-2')!.emit('.message.sent', { id: 9 })
    expect(calls.onMessage).toHaveBeenCalledTimes(1)
  })

  it('quitte le canal au démontage', async () => {
    const { wrapper } = mountThread(ref('conv-1'))
    await flushPromises()

    wrapper.unmount()

    expect(fakeEcho.leave).toHaveBeenCalledWith('conversation.conv-1')
  })

  it('quitte le canal à la déconnexion', async () => {
    mountThread(ref('conv-1'))
    await flushPromises()

    auth.user = null
    await nextTick()

    expect(fakeEcho.leave).toHaveBeenCalledWith('conversation.conv-1')
  })

  it('ne s\'abonne pas pour un utilisateur anonyme', async () => {
    auth.user = null
    mountThread(ref('conv-1'))
    await flushPromises()

    expect(fakeEcho.private).not.toHaveBeenCalled()
  })

  it('ne fait aucun polling quand le temps réel fonctionne', async () => {
    vi.useFakeTimers()
    const { calls } = mountThread(ref('conv-1'))
    await flushPromises()
    channels.get('conversation.conv-1')!.connect()

    await vi.advanceTimersByTimeAsync(60_000)

    expect(calls.poll).not.toHaveBeenCalled()
  })

  it('repli : poll toutes les 15 s sur erreur du canal, puis arrêt à la connexion', async () => {
    vi.useFakeTimers()
    const { calls } = mountThread(ref('conv-1'))
    await flushPromises()
    const channel = channels.get('conversation.conv-1')!

    channel.fail()
    await vi.advanceTimersByTimeAsync(15_000)
    expect(calls.poll).toHaveBeenCalledTimes(1)
    expect(calls.poll).toHaveBeenCalledWith('conv-1')

    channel.connect()
    await vi.advanceTimersByTimeAsync(60_000)
    expect(calls.poll).toHaveBeenCalledTimes(1)
  })

  it('repli : pas de polling tant que l\'onglet est masqué', async () => {
    vi.useFakeTimers()
    const { calls } = mountThread(ref('conv-1'))
    await flushPromises()
    channels.get('conversation.conv-1')!.fail()

    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden')
    await vi.advanceTimersByTimeAsync(30_000)
    expect(calls.poll).not.toHaveBeenCalled()

    visibility.mockReturnValue('visible')
    await vi.advanceTimersByTimeAsync(15_000)
    expect(calls.poll).toHaveBeenCalledTimes(1)
    visibility.mockRestore()
  })

  it('repli : poll aussi quand Echo ne se charge pas', async () => {
    vi.useFakeTimers()
    fakeEcho.private.mockImplementationOnce(() => {
      throw new Error('boom')
    })
    const { calls } = mountThread(ref('conv-1'))
    await flushPromises()

    await vi.advanceTimersByTimeAsync(15_000)

    expect(calls.poll).toHaveBeenCalledTimes(1)
  })
})

describe('useConversationListRealtime', () => {
  function mountList() {
    const calls = { onUpdated: vi.fn(), poll: vi.fn().mockResolvedValue(undefined) }
    const wrapper = mount(
      defineComponent({
        setup() {
          useConversationListRealtime(calls)
          return () => h('div')
        },
      }),
    )
    return { wrapper, calls }
  }

  it('écoute conversation.updated sur le canal privé de l\'utilisateur (même instance que le store)', async () => {
    const { calls } = mountList()
    await flushPromises()

    const channel = channels.get('App.Models.User.1')!
    channel.emit('.conversation.updated', { conversation_id: 'c1' })

    expect(calls.onUpdated).toHaveBeenCalledWith({ conversation_id: 'c1' })
  })

  it('retire uniquement son écouteur au démontage (sans quitter le canal du store)', async () => {
    const { wrapper } = mountList()
    await flushPromises()
    const channel = channels.get('App.Models.User.1')!

    wrapper.unmount()

    expect(channel.listenerCount('.conversation.updated')).toBe(0)
    expect(fakeEcho.leave).not.toHaveBeenCalled()
  })

  it('ne s\'abonne pas pour un utilisateur anonyme', async () => {
    auth.user = null
    mountList()
    await flushPromises()

    expect(fakeEcho.private).not.toHaveBeenCalled()
  })

  it('repli : poll de la liste toutes les 30 s sur erreur, arrêt à la connexion', async () => {
    vi.useFakeTimers()
    const { calls } = mountList()
    await flushPromises()
    const channel = channels.get('App.Models.User.1')!

    channel.fail()
    await vi.advanceTimersByTimeAsync(29_000)
    expect(calls.poll).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1_000)
    expect(calls.poll).toHaveBeenCalledTimes(1)

    channel.connect()
    await vi.advanceTimersByTimeAsync(90_000)
    expect(calls.poll).toHaveBeenCalledTimes(1)
  })

  it('retire l\'écouteur à la déconnexion', async () => {
    mountList()
    await flushPromises()
    const channel = channels.get('App.Models.User.1')!

    auth.user = null
    await nextTick()

    expect(channel.listenerCount('.conversation.updated')).toBe(0)
  })
})
