import { defineStore, getActivePinia } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/services/apiClient'
import { useNotificationStore } from '@/stores/notification'

type MessagesRole = 'face' | 'producer'

const FOCUS_REFETCH_MIN_INTERVAL_MS = 30_000
const FALLBACK_POLL_INTERVAL_MS = 60_000
const CONVERSATION_UPDATED_EVENT = '.conversation.updated'

// Poignées de cycle de vie (hors état réactif) : une seule série par session de layout.
let startedRole: MessagesRole | null = null
let focusHandler: (() => void) | null = null
let offUserChannel: (() => void) | null = null
let pollIntervalId: ReturnType<typeof setInterval> | null = null
let lastFetchAt = 0

/**
 * Compteur « Messages » = nombre de CONVERSATIONS ayant au moins un message non lu pour
 * l'utilisateur courant (pas le nombre de messages). Source unique de vérité : toujours
 * une valeur fournie par le serveur (endpoint dédié, événement `conversation.updated`,
 * réponses liste / fil / lecture) — jamais recalculée côté client depuis une liste partielle.
 * Calque useUgcValidationCountStore.
 */
export const useMessagesUnreadStore = defineStore('messagesUnread', () => {
  const count = ref(0)
  // Incrémenté à chaque valeur serveur poussée : une réponse de fetch partie avant
  // un événement plus récent est périmée et ne doit pas l'écraser.
  let version = 0

  function setCount(n: number): void {
    version++
    count.value = Math.max(0, Math.trunc(n))
  }

  // Non-fatal : le badge reste sur sa dernière valeur si le serveur est injoignable.
  async function fetchCount(role: MessagesRole): Promise<void> {
    lastFetchAt = Date.now()
    const requestVersion = version
    try {
      const response = await apiClient.get<{ data: { count: number } }>(
        `/${role}/conversations/unread-count`,
      )
      if (requestVersion !== version) return
      setCount(response.data.data.count)
    } catch (error) {
      console.error('[MessagesUnreadStore] Failed to fetch unread conversations count:', error)
    }
  }

  function startFocusListener(role: MessagesRole): void {
    if (typeof window === 'undefined' || focusHandler) return

    focusHandler = () => {
      if (Date.now() - lastFetchAt < FOCUS_REFETCH_MIN_INTERVAL_MS) return
      void fetchCount(role)
    }
    window.addEventListener('focus', focusHandler)
  }

  function stopFocusListener(): void {
    if (typeof window !== 'undefined' && focusHandler) {
      window.removeEventListener('focus', focusHandler)
    }
    focusHandler = null
  }

  // Repli : tant que le canal temps réel de l'utilisateur n'est pas abonné, on resynchronise
  // périodiquement (onglet visible). Aucun appel quand le temps réel fonctionne.
  function startFallbackPoll(role: MessagesRole): void {
    if (pollIntervalId) return

    const notificationStore = useNotificationStore()
    pollIntervalId = setInterval(() => {
      if (notificationStore.isSubscribed) return
      if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return
      void fetchCount(role)
    }, FALLBACK_POLL_INTERVAL_MS)
  }

  function stopFallbackPoll(): void {
    if (pollIntervalId) clearInterval(pollIntervalId)
    pollIntervalId = null
  }

  /**
   * Démarre le suivi pour un layout authentifié : UN fetch initial, puis mises à jour
   * par événement, focus (≤ 1 / 30 s) et repli par polling. Idempotent pour un même rôle ;
   * aucun refetch à la navigation.
   */
  function start(role: MessagesRole): void {
    if (startedRole === role) return
    stop()
    startedRole = role

    void fetchCount(role)

    offUserChannel = useNotificationStore().onUserChannelEvent(
      CONVERSATION_UPDATED_EVENT,
      ((event: { unread_conversations_count?: number }) => {
        if (typeof event?.unread_conversations_count === 'number') {
          setCount(event.unread_conversations_count)
        }
      }) as (payload: never) => void,
    )
    startFocusListener(role)
    startFallbackPoll(role)
  }

  function stop(): void {
    offUserChannel?.()
    offUserChannel = null
    stopFocusListener()
    stopFallbackPoll()
    startedRole = null
  }

  function $reset(): void {
    stop()
    version++
    lastFetchAt = 0
    count.value = 0
  }

  return { count, fetchCount, setCount, start, stop, $reset }
})

/**
 * Applique la valeur serveur `unread_conversations_count` d'une réponse liste / fil / lecture.
 * No-op sans Pinia actif ou sans valeur numérique (réponses antérieures au champ).
 */
export function syncMessagesUnreadFromServer(value: unknown): void {
  if (typeof value !== 'number' || !getActivePinia()) return
  useMessagesUnreadStore().setCount(value)
}
