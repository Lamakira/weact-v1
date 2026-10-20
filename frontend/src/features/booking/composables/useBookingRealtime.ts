import { onUnmounted, watch, type Ref } from 'vue'

/**
 * Événements diffusés sur le canal privé `booking.{id}` qui changent l'état du booking.
 * (Les messages du chat passent par BookingChat, sur le même canal.)
 */
export const BOOKING_STATE_EVENTS = [
  '.booking.expired',
  '.booking.cancelled',
  '.booking.no_show_reported',
  '.booking.status_changed',
] as const

interface EchoChannel {
  listen: (event: string, callback: () => void) => EchoChannel
  stopListening: (event: string, callback?: () => void) => EchoChannel
}

/**
 * Écoute le canal du booking tant que `active` est vrai et appelle `onChange` à chaque
 * changement d'état (le plus souvent : recharger le booking). Désabonnement propre au
 * démontage et quand le booking devient terminal.
 *
 * On retire uniquement nos propres écouteurs (pas de `leave`) : BookingChat peut être
 * abonné au même canal.
 */
export function useBookingRealtime(
  channelKey: Ref<number | null>,
  active: Ref<boolean>,
  onChange: () => void | Promise<void>,
): void {
  let attached: { key: number; handler: () => void } | null = null
  // Invalide les attachements asynchrones périmés (changement rapide d'état, démontage).
  let generation = 0

  async function detach(): Promise<void> {
    const current = attached
    attached = null
    if (current === null) return

    try {
      const { echo } = await import('@/plugins/echo')
      const channel = echo.private(`booking.${current.key}`) as EchoChannel
      for (const event of BOOKING_STATE_EVENTS) {
        channel.stopListening(event, current.handler)
      }
    } catch {
      // Ignore realtime cleanup errors.
    }
  }

  async function attach(key: number, token: number): Promise<void> {
    try {
      const { echo } = await import('@/plugins/echo')
      if (token !== generation) return

      const handler = (): void => {
        void onChange()
      }
      const channel = echo.private(`booking.${key}`) as EchoChannel
      for (const event of BOOKING_STATE_EVENTS) {
        channel.listen(event, handler)
      }
      attached = { key, handler }
    } catch {
      attached = null
    }
  }

  watch(
    [channelKey, active],
    async ([key, isActive]) => {
      const token = ++generation
      await detach()
      if (key === null || !isActive || token !== generation) return
      await attach(key, token)
    },
    { immediate: true },
  )

  onUnmounted(() => {
    generation++
    void detach()
  })
}
