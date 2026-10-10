import { onBeforeUnmount, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { LIST_POLL_INTERVAL_MS, loadEcho, useVisiblePolling, watchConnection } from '../utils/realtime'
import type { ConversationUpdatedBroadcast } from '../types'

interface ConversationListRealtimeHandlers {
  onUpdated: (update: ConversationUpdatedBroadcast) => void
  /** Resynchronisation silencieuse de la liste (repli par polling et rattrapage) */
  poll: () => Promise<unknown> | unknown
}

const EVENT = '.conversation.updated'

/**
 * Temps réel de la liste des conversations : `.conversation.updated` sur le canal privé de
 * l'utilisateur (`App.Models.User.{id}`).
 *
 * Le store de notifications POSSÈDE ce canal (abonnement, `leave` sur erreur,
 * ré-abonnement) : on s'y inscrit via `onUserChannelEvent`, qui ré-attache l'écouteur à
 * chaque (ré)abonnement. Aucune référence au canal n'est conservée ici, donc aucune
 * référence morte possible.
 *
 * Repli : liste resynchronisée toutes les 30 s (onglet visible) tant que le canal du store
 * n'est pas abonné (Echo non chargé, canal en erreur) ou que Reverb est injoignable ;
 * arrêt dès que le temps réel est (re)connecté, avec un rattrapage unique.
 */
export function useConversationListRealtime(handlers: ConversationListRealtimeHandlers) {
  const authStore = useAuthStore()
  const notificationStore = useNotificationStore()

  const polling = useVisiblePolling(() => handlers.poll(), LIST_POLL_INTERVAL_MS)

  let off: (() => void) | null = null
  let stopWatchingConnection: (() => void) | null = null
  let generation = 0
  let socketDown = false

  // Pas de temps réel tant que le canal du store n'est pas abonné ; le premier tick du
  // polling n'arrive qu'après 30 s, le temps que l'abonnement s'établisse.
  function syncPolling(): void {
    if (!authStore.user?.id) {
      polling.stop()
      return
    }
    if (socketDown || !notificationStore.isSubscribed) polling.start()
    else polling.stop()
  }

  async function watchSocket(): Promise<void> {
    const current = generation
    try {
      const echo = await loadEcho()
      if (current !== generation) return
      stopWatchingConnection = watchConnection(echo, {
        onDown: () => {
          socketDown = true
          syncPolling()
        },
        onUp: () => {
          socketDown = false
          syncPolling()
          void handlers.poll() // rattrapage des événements manqués pendant la coupure
        },
      })
    } catch {
      // Echo indisponible : le store n'est pas abonné non plus, le polling couvre
    }
  }

  function start(): void {
    stop()
    if (!authStore.user?.id) return
    off = notificationStore.onUserChannelEvent(EVENT, ((event: ConversationUpdatedBroadcast) => {
      handlers.onUpdated(event)
    }) as (payload: never) => void)
    syncPolling()
    void watchSocket()
  }

  function stop(): void {
    generation++
    off?.()
    off = null
    stopWatchingConnection?.()
    stopWatchingConnection = null
    socketDown = false
    polling.stop()
  }

  onMounted(start)
  onBeforeUnmount(stop)

  // Connexion / déconnexion de l'utilisateur
  watch(
    () => authStore.user?.id,
    (userId) => {
      if (userId) start()
      else stop()
    },
  )
  watch(() => notificationStore.isSubscribed, syncPolling)

  return { isPolling: polling.isActive }
}
