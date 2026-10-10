import { onBeforeUnmount, watch, type Ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  THREAD_POLL_INTERVAL_MS,
  loadEcho,
  useVisiblePolling,
  watchConnection,
  type EchoChannel,
  type EchoInstance,
} from '../utils/realtime'
import type { MessageBroadcast, MessagesReadBroadcast } from '../types'

interface ConversationRealtimeHandlers {
  /** Message de l'autre participant (ou d'un autre onglet du même utilisateur) */
  onMessage: (message: MessageBroadcast) => void
  /** Accusé de lecture de l'autre participant */
  onRead: (receipt: MessagesReadBroadcast) => void
  /** Resynchronisation silencieuse du fil (repli par polling) */
  poll: (conversationUuid: string) => Promise<unknown> | unknown
}

/**
 * Temps réel du fil ouvert : abonnement à `conversation.{uuid}` (canal privé Reverb).
 *
 * - s'abonne à l'ouverture d'un fil, quitte le canal au changement de fil, au démontage
 *   et à la déconnexion ;
 * - un uuid `null` (aucun fil, ou fil en erreur 403/404) coupe l'abonnement ET le polling ;
 * - garde de génération contre les courses (chargement d'Echo asynchrone) ;
 * - repli : si Echo ne charge pas ou si le canal est en erreur, le fil est resynchronisé
 *   toutes les 15 s (onglet visible uniquement) ; le polling s'arrête dès que le temps
 *   réel est connecté. Aucun polling quand le temps réel fonctionne ;
 * - connexion WebSocket coupée (`unavailable`/`failed`) : même repli ; au retour de la
 *   connexion, un rattrapage unique puis arrêt du polling.
 */
export function useConversationRealtime(
  conversationUuid: Ref<string | null>,
  role: 'face' | 'producer',
  handlers: ConversationRealtimeHandlers,
) {
  const authStore = useAuthStore()

  let generation = 0
  let echo: EchoInstance | null = null
  let channelName: string | null = null
  let stopWatchingConnection: (() => void) | null = null

  const polling = useVisiblePolling(() => {
    const uuid = conversationUuid.value
    return uuid ? handlers.poll(uuid) : undefined
  }, THREAD_POLL_INTERVAL_MS)

  async function subscribe(uuid: string): Promise<void> {
    if (!authStore.user?.id) return // jamais d'abonnement pour un visiteur anonyme

    const name = `conversation.${uuid}`
    if (channelName === name) return

    const current = ++generation

    let loaded: EchoInstance
    try {
      loaded = await loadEcho()
    } catch {
      if (current === generation) polling.start()
      return
    }
    if (current !== generation) return
    echo = loaded

    stopWatchingConnection?.()
    stopWatchingConnection = watchConnection(loaded, {
      onDown: () => {
        if (current === generation) polling.start()
      },
      onUp: () => {
        if (current !== generation) return
        polling.stop()
        void handlers.poll(uuid) // rattrapage des messages manqués pendant la coupure
      },
    })

    try {
      const channel = loaded.private(name) as unknown as EchoChannel
      channelName = name

      channel
        .listen('.message.sent', ((event: MessageBroadcast) => {
          if (current !== generation) return
          handlers.onMessage(event)
        }) as (payload: never) => void)
        .listen('.messages.read', ((event: MessagesReadBroadcast) => {
          if (current !== generation) return
          // Le serveur exclut la connexion émettrice ; on ignore aussi les accusés de notre propre rôle
          if (event.reader_role === role) return
          handlers.onRead(event)
        }) as (payload: never) => void)
        .error(() => {
          if (current !== generation) return
          polling.start()
        })

      if (typeof channel.subscribed === 'function') {
        channel.subscribed(() => {
          if (current !== generation) return
          polling.stop()
        })
      }
    } catch {
      if (current === generation) polling.start()
    }
  }

  function unsubscribe(): void {
    generation++
    polling.stop()
    stopWatchingConnection?.()
    stopWatchingConnection = null
    if (echo && channelName) {
      try {
        echo.leave(channelName)
      } catch {
        // Ignore cleanup errors
      }
    }
    channelName = null
  }

  watch(
    conversationUuid,
    (uuid) => {
      unsubscribe()
      if (uuid) void subscribe(uuid)
    },
    { immediate: true },
  )

  // Déconnexion : on quitte le canal immédiatement
  watch(
    () => authStore.user?.id,
    (userId) => {
      if (!userId) unsubscribe()
    },
  )

  onBeforeUnmount(() => {
    unsubscribe()
  })

  return { isPolling: polling.isActive }
}
