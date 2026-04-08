import { ref, onMounted, onUnmounted, type Ref } from 'vue'
import { echo } from '@/plugins/echo'
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

export function useBookingChat(bookingId: number, currentUserId: number): UseBookingChatReturn {
  const POLL_INTERVAL_MS = 5000

  const messages = ref<BookingMessage[]>([])
  const isLoading = ref(false)
  const isSending = ref(false)
  const reverbError = ref(false)
  let pollingTimer: ReturnType<typeof setInterval> | null = null
  let isPollingRequestInFlight = false

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

  async function subscribeToChannel(): Promise<void> {
    try {
      const channel = echo.private(`booking.${bookingId}`) as EchoChannel

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
      echo.leave(`booking.${bookingId}`)
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
    await loadMessages()
    await subscribeToChannel()
  })

  onUnmounted(() => {
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
