<script setup lang="ts">
import { ref, watch, nextTick, onMounted, computed } from 'vue'
import ChatHeader from './ChatHeader.vue'
import BookingChatMessage from './BookingChatMessage.vue'
import { useBookingChat } from '../composables/useBookingChat'
import { BookingStatus, type Booking } from '../types'

interface Props {
  booking: Booking
  currentUserId: number
}

const props = defineProps<Props>()

const { messages, isLoading, isSending, reverbError, sendMessage, refreshMessages } =
  useBookingChat(props.booking.id, props.booking.realtime_channel_key, props.currentUserId)

const draft = ref('')
const messagesContainer = ref<HTMLDivElement | null>(null)

const isCompleted = computed(() => props.booking.status === BookingStatus.COMPLETED)

function scrollToBottom(): void {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

// Auto-scroll when new messages arrive
watch(
  () => messages.value.length,
  () => {
    nextTick(scrollToBottom)
  },
)

onMounted(() => {
  nextTick(scrollToBottom)
})

async function handleSend(): Promise<void> {
  if (!draft.value.trim() || isSending.value) return
  const content = draft.value.trim()
  draft.value = ''
  await sendMessage(content)
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    handleSend()
  }
}
</script>

<template>
  <div class="flex flex-col h-full" data-testid="booking-chat">
    <!-- Sticky header with booking context -->
    <ChatHeader :booking="booking" :current-user-id="currentUserId" />

    <!-- Reverb connection error banner (AC5) -->
    <div
      v-if="reverbError"
      class="flex items-center justify-between gap-3 bg-amber-50 border-b border-amber-100 px-4 py-2.5 text-sm text-amber-800"
    >
      <span>Chat temporairement indisponible. Les messages en temps réel ne sont pas disponibles.</span>
      <button
        class="shrink-0 text-xs font-medium text-[#198496] hover:underline"
        @click="refreshMessages"
      >
        Rafraîchir
      </button>
    </div>

    <!-- Message list (scrollable area) -->
    <div
      ref="messagesContainer"
      class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50"
    >
      <!-- Loading skeleton -->
      <div v-if="isLoading" class="flex flex-col items-center justify-center h-full text-gray-400">
        <div class="w-8 h-8 border-2 border-[#198496] border-t-transparent rounded-full animate-spin mb-2" />
        <p class="text-sm">Chargement des messages...</p>
      </div>

      <!-- Empty state -->
      <div
        v-else-if="messages.length === 0"
        class="flex flex-col items-center justify-center h-full text-gray-400 text-center"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="w-12 h-12 mb-3 text-gray-200"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.5"
            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
          />
        </svg>
        <p class="text-sm">Aucun message pour le moment.</p>
        <p class="text-xs mt-1">Commencez la conversation !</p>
      </div>

      <!-- Messages list -->
      <template v-else>
        <BookingChatMessage
          v-for="message in messages"
          :key="message.id"
          :message="message"
          :is-own="message.is_own_message"
        />
      </template>
    </div>

    <!-- Read-only banner for completed bookings (AC6) -->
    <div
      v-if="isCompleted"
      class="px-4 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-500 text-center"
    >
      Le booking est terminé. Le chat est désormais en lecture seule.
    </div>

    <!-- Message input (hidden for completed bookings) -->
    <div
      v-else
      class="border-t border-gray-100 bg-white px-3 py-3 flex items-end gap-2 sticky bottom-0"
    >
      <textarea
        v-model="draft"
        maxlength="2000"
        rows="1"
        placeholder="Écrire un message..."
        class="flex-1 resize-none rounded-[20px] border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#198496]/30 focus:border-[#198496] transition-colors overflow-hidden"
        style="max-height: 120px"
        :disabled="isSending"
        @keydown="handleKeydown"
        @input="
          ($event.target as HTMLTextAreaElement).style.height = 'auto';
          ($event.target as HTMLTextAreaElement).style.height =
            Math.min(($event.target as HTMLTextAreaElement).scrollHeight, 120) + 'px'
        "
      />
      <button
        class="shrink-0 w-10 h-10 rounded-full bg-[#198496] text-white flex items-center justify-center transition-colors hover:bg-[#146c7a] disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="isSending || !draft.trim()"
        aria-label="Envoyer le message"
        @click="handleSend"
      >
        <svg
          v-if="!isSending"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="currentColor"
          class="w-5 h-5 ml-0.5"
        >
          <path
            d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z"
          />
        </svg>
        <div
          v-else
          class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"
        />
      </button>
    </div>
  </div>
</template>
