import { ref, onMounted, onUnmounted, type Ref } from 'vue'
import { bookingChatApi } from '../services/bookingChatApi'
import type { BookingMessage, BookingMessageBroadcast } from '../types'

export interface UseBookingChatReturn {
  messages: Ref<BookingMessage[]>
  isLoading: Ref<boolean>
  isSending: Ref<boolean>
  reverbError: Ref<boolean>
  sendMessage: (content: string) => Promise<void>
  refreshMessages: () => Promise<void>
}

export function useBookingChat(
  bookingId: string,
  realtimeChannelKey: number,
  currentUserId: number,
): UseBookingChatReturn {
  const POLL_INTERVAL_MS = 5000

  const messages = ref<BookingMessage[]>([])
  const isLoading = ref(false)
  const isSending = ref(false)
  const reverbError = ref(false)
  let pollingTimer: ReturnType<typeof setInterval> | null = null
  let isPollingRequestInFlight = false
  let isDisposed = false

  interface EchoChannel {
    listen: (event: string, callback: (payload: BookingMessageBroadcast) => void) => EchoChannel
    error: (callback: () => void) => EchoChannel
    subscribed?: (callback: () => void) => EchoChannel
  }

  function startPolling(): void {
    if (pollingTimer !== null) return

    pollingTimer = setInterval(async () => {
      if (isPollingRequestInFlight) return

      isPollingRequestInFlight = true
      try {
        await loadMessages()
      } finally {
        isPollingRequestInFlight = false
      }
    }, POLL_INTERVAL_MS)
  }

  function stopPolling(): void {
    if (pollingTimer === null) return
    clearInterval(pollingTimer)
    pollingTimer = null
  }

  async function loadMessages(): Promise<void> {
    isLoading.value = true
    try {
      const response = await bookingChatApi.fetchMessages(bookingId)
      messages.value = response.data
    } catch {
      // REST load failure — messages stay empty, UI shows empty state
    } finally {
      isLoading.value = false
    }
  }

  function hasUnreadIncoming(): boolean {
    return messages.value.some((m) => !m.is_own_message && !m.read_at)
  }

  // Marque lu côté serveur UNIQUEMENT quand le fil est réellement visible (onglet au premier plan) ;
  // le chargement / polling des messages ne marque jamais lu à lui seul. Non bloquant.
  async function markReadIfVisible(): Promise<void> {
    if (typeof document === 'undefined' || document.visibilityState !== 'visible') return
    try {
      await bookingChatApi.markRead(bookingId)
      const readAt = new Date().toISOString()
      for (const m of messages.value) {
        if (!m.is_own_message && !m.read_at) m.read_at = readAt
      }
    } catch {
      // ignoré : le prochain message reçu ou le retour sur l'onglet réessaiera
    }
  }

  // Retour sur l'onglet : les messages reçus pendant l'absence sont marqués lus
  function handleVisibilityChange(): void {
    if (document.visibilityState === 'visible' && hasUnreadIncoming()) void markReadIfVisible()
  }

  async function subscribeToChannel(): Promise<void> {
    try {
      // Echo (pusher-js + laravel-echo) chargé à la demande, hors des chunks de listes
      const { echo } = await import('@/plugins/echo')
      if (isDisposed) return

      const channel = echo.private(`booking.${realtimeChannelKey}`) as EchoChannel

      channel
        .listen('.booking.message.sent', (event: BookingMessageBroadcast) => {
          // Prevent duplicates (e.g., two tabs, or WS delivery of own sent message)
          const alreadyExists = messages.value.some((m) => m.id === event.id)
          if (!alreadyExists) {
            messages.value.push({
              ...event,
              // is_own_message intentionally absent from broadcast payload — compute client-side
              is_own_message: event.sender_id === currentUserId,
            })
          }
          // Message de l'autre partie reçu fil ouvert : lu côté serveur (badge « Messages » à jour)
          if (event.sender_id !== currentUserId) void markReadIfVisible()
        })
        .error(() => {
          reverbError.value = true
          startPolling()
        })

      if (typeof channel.subscribed === 'function') {
        channel.subscribed(() => {
          reverbError.value = false
          stopPolling()
        })
      }
    } catch {
      reverbError.value = true
      startPolling()
    }
  }

  async function unsubscribeFromChannel(): Promise<void> {
    stopPolling()
    try {
      const { echo } = await import('@/plugins/echo')
      echo.leave(`booking.${realtimeChannelKey}`)
    } catch {
      // Ignore cleanup errors
    }
  }

  async function sendMessage(content: string): Promise<void> {
    if (isSending.value || !content.trim()) return
    isSending.value = true
    try {
      const response = await bookingChatApi.sendMessage(bookingId, content)
      // Guard against races where WS event and HTTP response carry the same message.
      const alreadyExists = messages.value.some((m) => m.id === response.data.id)
      if (!alreadyExists) {
        // Append the server response (has is_own_message: true from BookingMessageResource)
        messages.value.push(response.data)
      }
    } finally {
      isSending.value = false
    }
  }

  async function refreshMessages(): Promise<void> {
    reverbError.value = false
    await unsubscribeFromChannel()
    await loadMessages()
    // Explicit REST polling fallback while attempting to re-establish realtime.
    startPolling()
    await subscribeToChannel()
  }

  onMounted(async () => {
    document.addEventListener('visibilitychange', handleVisibilityChange)
    await loadMessages()
    if (hasUnreadIncoming()) void markReadIfVisible()
    await subscribeToChannel()
  })

  onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    isDisposed = true
    void unsubscribeFromChannel()
  })

  return {
    messages,
    isLoading,
    isSending,
    reverbError,
    sendMessage,
    refreshMessages,
  }
}
