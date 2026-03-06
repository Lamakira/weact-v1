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
  const messages = ref<BookingMessage[]>([])
  const isLoading = ref(false)
  const isSending = ref(false)
  const reverbError = ref(false)

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

  function subscribeToChannel(): void {
    try {
      echo
        .private(`booking.${bookingId}`)
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
        })
    } catch {
      reverbError.value = true
    }
  }

  function unsubscribeFromChannel(): void {
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
      // Append the server response (has is_own_message: true from BookingMessageResource)
      messages.value.push(response.data)
    } finally {
      isSending.value = false
    }
  }

  async function refreshMessages(): Promise<void> {
    reverbError.value = false
    unsubscribeFromChannel()
    await loadMessages()
    subscribeToChannel()
  }

  onMounted(async () => {
    await loadMessages()
    subscribeToChannel()
  })

  onUnmounted(() => {
    unsubscribeFromChannel()
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
