import { onScopeDispose } from 'vue'
import { useNotificationStore } from '@/stores/notification'

/** Intervalle minimal entre deux rechargements déclenchés par le retour sur l'onglet. */
const FOCUS_REFRESH_MIN_INTERVAL_MS = 30_000

const NOTIFICATION_CREATED_EVENT = '.notification.created'

/**
 * Recharge une file « À faire » quand l'utilisateur revient sur l'onglet (`focus` /
 * `visibilitychange`, au plus toutes les 30 s) et à chaque nouvelle notification temps réel.
 *
 * L'écoute temps réel passe par `onUserChannelEvent` du store de notifications, qui possède
 * l'abonnement Echo : aucun second abonnement n'est ouvert ici. Les écouteurs sont retirés
 * à la destruction du scope appelant.
 */
export function useTodoAutoRefresh(refresh: () => Promise<void> | void): void {
  if (typeof window === 'undefined') return

  let lastRefreshAt = Date.now()

  function onReturnToTab(): void {
    if (document.visibilityState === 'hidden') return

    const now = Date.now()
    if (now - lastRefreshAt < FOCUS_REFRESH_MIN_INTERVAL_MS) return

    lastRefreshAt = now
    void refresh()
  }

  function onNotification(): void {
    lastRefreshAt = Date.now()
    void refresh()
  }

  window.addEventListener('focus', onReturnToTab)
  document.addEventListener('visibilitychange', onReturnToTab)
  const stopListening = useNotificationStore().onUserChannelEvent(
    NOTIFICATION_CREATED_EVENT,
    onNotification,
  )

  onScopeDispose(() => {
    window.removeEventListener('focus', onReturnToTab)
    document.removeEventListener('visibilitychange', onReturnToTab)
    stopListening()
  })
}
