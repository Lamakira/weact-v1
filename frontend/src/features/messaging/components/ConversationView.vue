<script setup lang="ts">
import { ref, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Loader2, MessageSquare, RefreshCw } from 'lucide-vue-next'
import ConversationHeader from './ConversationHeader.vue'
import MessageBubble from './MessageBubble.vue'
import MessageInput from './MessageInput.vue'
import { useConversation } from '../composables/useConversation'
import { useSendMessage } from '../composables/useSendMessage'

const route = useRoute()
const router = useRouter()

const {
  messages,
  otherParticipant,
  missionTitle,
  isLoading,
  isRefreshing,
  error: loadError,
  refreshError,
  loadConversation,
  refreshConversation,
  addMessage,
  clearRefreshError,
} = useConversation()

const {
  isSending,
  error: sendError,
  sendMessage,
  resetError: resetSendError,
} = useSendMessage()

const scrollContainer = ref<HTMLElement | null>(null)
const newMessageText = ref('')

const getConversationId = (): string | null => {
  const param = route.params.conversationId
  const value = Array.isArray(param) ? param[0] : param
  return typeof value === 'string' && value.length > 0 ? value : null
}

const scrollToBottom = async (behavior: ScrollBehavior = 'smooth') => {
  await nextTick()
  if (scrollContainer.value) {
    scrollContainer.value.scrollTo({
      top: scrollContainer.value.scrollHeight,
      behavior,
    })
  }
}

const handleBack = () => {
  router.push({ name: 'face-candidatures' })
}

const handleSendMessage = async () => {
  if (!newMessageText.value.trim() || isSending.value) return

  const conversationId = getConversationId()
  if (!conversationId) return
  const content = newMessageText.value.trim()
  resetSendError()

  const sentMessage = await sendMessage(conversationId, content)
  if (sentMessage) {
    addMessage(sentMessage)
    newMessageText.value = ''
    scrollToBottom('smooth')
  }
}

const retryLoading = () => {
  const conversationId = getConversationId()
  if (conversationId) {
    void loadConversation(conversationId)
  }
}

const handleRefresh = async () => {
  clearRefreshError()
  const conversationId = getConversationId()
  if (!conversationId) return
  const success = await refreshConversation(conversationId)
  if (success) {
    scrollToBottom('smooth')
  }
}

// Initial load
onMounted(async () => {
  const conversationId = getConversationId()
  if (!conversationId) {
    router.push({ name: 'face-candidatures' })
    return
  }
  await loadConversation(conversationId)
  scrollToBottom('auto')
})

// Watch for new messages to scroll
watch(
  messages,
  () => {
    scrollToBottom('smooth')
  },
  { deep: true },
)

// Auto-dismiss refresh error after 5 seconds
watch(refreshError, (newError) => {
  if (newError) {
    setTimeout(() => {
      clearRefreshError()
    }, 5000)
  }
})
</script>

<template>
  <div class="flex h-screen flex-col overflow-hidden bg-background">
    <!-- Header Section -->
    <ConversationHeader
      v-if="!isLoading && !loadError && otherParticipant"
      :participant="otherParticipant"
      :mission-title="missionTitle"
      :is-refreshing="isRefreshing"
      @back="handleBack"
      @refresh="handleRefresh"
    />

    <!-- Refresh Error Toast -->
    <div
      v-if="refreshError"
      class="absolute left-1/2 top-20 z-30 -translate-x-1/2 transform rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground shadow-lg"
    >
      {{ refreshError }}
    </div>

    <!-- Main Chat Area -->
    <main ref="scrollContainer" class="relative flex-1 space-y-4 overflow-y-auto px-4 py-6 md:px-6">
      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="absolute inset-0 flex flex-col items-center justify-center space-y-4"
      >
        <Loader2 class="h-10 w-10 animate-spin text-primary" />
        <p class="text-sm font-medium text-muted-foreground">Chargement de la discussion...</p>
      </div>

      <!-- Error State -->
      <div
        v-else-if="loadError"
        class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center"
      >
        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10">
          <RefreshCw class="h-8 w-8 text-destructive" />
        </div>
        <h3 class="text-lg font-semibold text-foreground">Une erreur est survenue</h3>
        <p class="mt-2 max-w-xs text-sm text-muted-foreground">
          {{ loadError }}
        </p>
        <button
          type="button"
          class="mt-6 rounded-full bg-primary px-6 py-2 text-sm font-medium text-primary-foreground shadow-lg transition-all hover:opacity-90 active:scale-95"
          @click="retryLoading"
        >
          Réessayer
        </button>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="messages.length === 0"
        class="flex h-full flex-col items-center justify-center p-8 text-center"
      >
        <div
          class="mb-6 flex h-24 w-24 items-center justify-center rounded-3xl border border-border bg-muted"
        >
          <MessageSquare class="h-10 w-10 text-primary" />
        </div>
        <h2 class="text-xl font-semibold tracking-tight">Commencez la discussion !</h2>
        <p class="mt-2 max-w-[280px] text-muted-foreground">
          Envoyez un message pour coordonner les détails de la mission avec
          {{ otherParticipant?.name }}.
        </p>
      </div>

      <!-- Messages List -->
      <div v-else class="mx-auto w-full max-w-4xl space-y-1 pb-4">
        <MessageBubble v-for="message in messages" :key="message.id" :message="message" />
      </div>
    </main>

    <!-- Sticky Input Section -->
    <MessageInput
      v-if="!isLoading && !loadError"
      v-model="newMessageText"
      :is-loading="isSending"
      :error="sendError"
      @submit="handleSendMessage"
    />
  </div>
</template>

<style scoped>
/* Custom Scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: var(--color-border);
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: var(--color-muted-foreground);
}

/* Prevent layout shift on mobile keyboards */
@supports (-webkit-touch-callout: none) {
  .h-screen {
    height: -webkit-fill-available;
  }
}
</style>
