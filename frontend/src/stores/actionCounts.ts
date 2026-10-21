import { defineStore } from 'pinia'
import { computed } from 'vue'
import apiClient from '@/services/apiClient'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'
import { useNotificationStore } from '@/stores/notification'

/**
 * Compteurs des badges de navigation (lot D3), GET /me/action-counts. Chaque valeur est le
 * nombre d'éléments du filtre « À traiter » de la page qu'elle désigne. Les champs absents
 * pour un rôle restent à 0 (Face : candidatures ; Producer : missions).
 */
export interface ActionCounts {
  bookings: number
  candidatures: number
  missions: number
  livrables: number
  messages: number
}

const EMPTY_COUNTS: ActionCounts = {
  bookings: 0,
  candidatures: 0,
  missions: 0,
  livrables: 0,
  messages: 0,
}

const FOCUS_REFETCH_MIN_INTERVAL_MS = 30_000
const REALTIME_EVENTS = ['.notification.created', '.conversation.updated'] as const

// Les valeurs vivent dans une ressource partagée : le reset générique des caches (déconnexion,
// changement de compte) les remet à zéro sans que le store auth importe cette feature.
const resource = createSharedCachedResource<ActionCounts>({
  key: 'action-counts',
  initialValue: EMPTY_COUNTS,
  ttlMs: FOCUS_REFETCH_MIN_INTERVAL_MS,
  load: async () => {
    const response = await apiClient.get<{ data: Partial<ActionCounts> }>('/me/action-counts')
    return { ...EMPTY_COUNTS, ...response.data.data }
  },
  getErrorMessage: () => 'Impossible de charger les compteurs.',
})

// Poignées de cycle de vie (hors état réactif) : une seule série par session de layout.
let started = false
let focusHandler: (() => void) | null = null
let offUserChannel: Array<() => void> = []
let lastFetchAt = 0
let fetching = false
let refetchRequested = false

export const useActionCountsStore = defineStore('actionCounts', () => {
  const counts = computed<ActionCounts>(() => resource.data.value)

  // Non-fatal : les badges gardent leur dernière valeur si le serveur est injoignable.
  // Une demande reçue pendant un chargement en cours déclenche UN rechargement ensuite
  // (la réponse en vol peut précéder le changement qui a motivé la demande).
  async function refresh(): Promise<void> {
    if (fetching) {
      refetchRequested = true
      return
    }
    fetching = true
    lastFetchAt = Date.now()
    try {
      await resource.fetch({ force: true })
    } finally {
      fetching = false
    }
    if (refetchRequested) {
      refetchRequested = false
      await refresh()
    }
  }

  /**
   * Démarre le suivi pour un layout authentifié : un chargement initial, puis rechargement
   * à chaque notification / conversation reçue sur le canal utilisateur et au retour sur
   * l'onglet (au plus toutes les 30 s). Idempotent.
   */
  function start(): void {
    if (started) return
    started = true

    void refresh()

    const notificationStore = useNotificationStore()
    offUserChannel = REALTIME_EVENTS.map((event) =>
      notificationStore.onUserChannelEvent(event, (() => {
        void refresh()
      }) as (payload: never) => void),
    )

    if (typeof window !== 'undefined') {
      focusHandler = () => {
        if (Date.now() - lastFetchAt < FOCUS_REFETCH_MIN_INTERVAL_MS) return
        void refresh()
      }
      window.addEventListener('focus', focusHandler)
    }
  }

  function stop(): void {
    offUserChannel.forEach((off) => off())
    offUserChannel = []
    if (typeof window !== 'undefined' && focusHandler) {
      window.removeEventListener('focus', focusHandler)
    }
    focusHandler = null
    started = false
  }

  function $reset(): void {
    stop()
    resource.reset()
    lastFetchAt = 0
    refetchRequested = false
  }

  return { counts, refresh, start, stop, $reset }
})
