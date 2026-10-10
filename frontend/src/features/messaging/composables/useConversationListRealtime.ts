import { onActivated, onBeforeUnmount, onDeactivated, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  LIST_POLL_INTERVAL_MS,
  loadEcho,
  useVisiblePolling,
  type EchoChannel,
  type EchoInstance,
} from '../utils/realtime'
import type { ConversationUpdatedBroadcast } from '../types'

interface ConversationListRealtimeHandlers {
  onUpdated: (update: ConversationUpdatedBroadcast) => void
  /** Resynchronisation silencieuse de la liste (repli par polling) */
  poll: () => Promise<unknown> | unknown
}

/**
 * Temps réel de la liste des conversations : écoute `.conversation.updated` sur le canal
 * privé de l'utilisateur (`App.Models.User.{id}`).
 *
 * Echo renvoie la MÊME instance de canal pour un nom donné : le store de notifications
 * y est déjà abonné, on n'ouvre donc ni seconde connexion ni second abonnement. On
 * n'enlève que NOTRE écouteur (jamais `leave`, le canal appartient au store).
 *
 * Repli : liste resynchronisée toutes les 30 s (onglet visible) si Echo ne charge pas ou
 * si le canal est en erreur ; arrêt dès que le temps réel est connecté.
 */
export function useConversationListRealtime(handlers: ConversationListRealtimeHandlers) {
  const authStore = useAuthStore()
  const EVENT = '.conversation.updated'

  let generation = 0
  let channel: EchoChannel | null = null
  let listener: ((payload: never) => void) | null = null
  let isActive = false

  const polling = useVisiblePolling(() => handlers.poll(), LIST_POLL_INTERVAL_MS)

  async function subscribe(): Promise<void> {
    const userId = authStore.user?.id
    if (!userId || channel) return // jamais d'abonnement pour un visiteur anonyme

    const current = ++generation

    let echo: EchoInstance
    try {
      echo = await loadEcho()
    } catch {
      if (current === generation) polling.start()
      return
    }
    if (current !== generation) return

    try {
      const userChannel = echo.private(`App.Models.User.${userId}`) as unknown as EchoChannel
      const callback = ((event: ConversationUpdatedBroadcast) => {
        if (current !== generation) return
        handlers.onUpdated(event)
      }) as (payload: never) => void

      userChannel.listen(EVENT, callback).error(() => {
        if (current !== generation) return
        polling.start()
      })
      if (typeof userChannel.subscribed === 'function') {
        userChannel.subscribed(() => {
          if (current !== generation) return
          polling.stop()
        })
      }
      channel = userChannel
      listener = callback
    } catch {
      if (current === generation) polling.start()
    }
  }

  function unsubscribe(): void {
    generation++
    polling.stop()
    if (channel && listener) {
      try {
        channel.stopListening(EVENT, listener)
      } catch {
        // Ignore cleanup errors
      }
    }
    channel = null
    listener = null
  }

  function activate(): void {
    if (isActive) return
    isActive = true
    void subscribe()
  }

  function deactivate(): void {
    isActive = false
    unsubscribe()
  }

  onMounted(activate)
  onActivated(activate)
  onDeactivated(deactivate)
  onBeforeUnmount(deactivate)

  // Déconnexion : on retire l'écouteur immédiatement
  watch(
    () => authStore.user?.id,
    (userId) => {
      if (!userId) unsubscribe()
      else if (isActive) void subscribe()
    },
  )

  return { isPolling: polling.isActive }
}
