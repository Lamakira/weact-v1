<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Loader2, AlertCircle, MessageSquare, RefreshCw, ArrowLeft } from 'lucide-vue-next'
import { useConversationsList } from '@/features/messaging/composables/useConversationsList'
import { useConversation } from '@/features/messaging/composables/useConversation'
import { useSendMessage } from '@/features/messaging/composables/useSendMessage'
import ConversationHeader from '@/features/messaging/components/ConversationHeader.vue'
import MessageBubble from '@/features/messaging/components/MessageBubble.vue'
import MessageInput from '@/features/messaging/components/MessageInput.vue'
import type { ConversationListItem } from '@/features/messaging/types'

const route = useRoute()
const router = useRouter()

// Conversations list composable
const {
  conversations,
  isLoading: isListLoading,
  isRefreshing: isListRefreshing,
  error: listError,
  hasConversations,
  loadConversations,
  refreshConversations,
} = useConversationsList()

// Active conversation composable
const {
  messages,
  otherParticipant,
  missionTitle,
  isLoading: isConversationLoading,
  isRefreshing: isConversationRefreshing,
  error: conversationError,
  refreshError,
  loadConversation,
  refreshConversation,
  addMessage,
  clearRefreshError,
} = useConversation()

// Send message composable
const {
  isSending,
  error: sendError,
  sendMessage,
  resetError: resetSendError,
} = useSendMessage()

// Local state
const selectedConversationId = ref<number | null>(null)
const scrollContainer = ref<HTMLElement | null>(null)
const newMessageText = ref('')

// Check if we're on mobile
const isMobile = ref(window.innerWidth < 1024)

// Update isMobile on resize
function handleResize() {
  isMobile.value = window.innerWidth < 1024
}

// Computed: Is a conversation selected
const hasSelectedConversation = computed(() => selectedConversationId.value !== null)

// Computed: Selected conversation data
const selectedConversation = computed(() => {
  if (!selectedConversationId.value) return null
  return conversations.value.find(c => c.id === selectedConversationId.value) || null
})

// Scroll to bottom of messages
const scrollToBottom = async (behavior: ScrollBehavior = 'smooth') => {
  await nextTick()
  if (scrollContainer.value) {
    scrollContainer.value.scrollTo({
      top: scrollContainer.value.scrollHeight,
      behavior,
    })
  }
}

// Select a conversation
async function selectConversation(conversation: ConversationListItem) {
  // On mobile, navigate to separate page
  if (isMobile.value) {
    router.push({ name: 'face-conversation', params: { conversationId: conversation.id } })
    return
  }

  // On desktop, load in split view
  selectedConversationId.value = conversation.id
  await loadConversation(conversation.id)
  scrollToBottom('auto')
}

// Close conversation (mobile back button)
function closeConversation() {
  selectedConversationId.value = null
}

// Handle send message
async function handleSendMessage() {
  if (!newMessageText.value.trim() || isSending.value || !selectedConversationId.value) return

  const content = newMessageText.value.trim()
  resetSendError()

  const sentMessage = await sendMessage(selectedConversationId.value, content)
  if (sentMessage) {
    addMessage(sentMessage)
    newMessageText.value = ''
    scrollToBottom('smooth')
  }
}

// Handle refresh list
async function handleRefreshList() {
  await refreshConversations()
}

// Handle refresh conversation
async function handleRefreshConversation() {
  if (!selectedConversationId.value) return
  clearRefreshError()
  const success = await refreshConversation(selectedConversationId.value)
  if (success) {
    scrollToBottom('smooth')
  }
}

// Handle retry loading conversation
function retryLoadingConversation() {
  if (!selectedConversationId.value) return
  loadConversation(selectedConversationId.value)
}

// Navigate to browse missions
function goToBrowseMissions() {
  router.push({ name: 'face-missions' })
}

// Get participant initials
function getInitials(name: string): string {
  return name.charAt(0).toUpperCase()
}

// Format relative time
function formatTime(dateString: string): string {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / (1000 * 60))
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

  if (diffMins < 1) return "À l'instant"
  if (diffMins < 60) return `${diffMins} min`
  if (diffHours < 24) return `${diffHours}h`
  if (diffDays < 7) return `${diffDays}j`

  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit' }).format(date)
}

// Lifecycle
onMounted(() => {
  loadConversations()
  window.addEventListener('resize', handleResize)
})

// Watch for new messages
watch(messages, () => {
  scrollToBottom('smooth')
}, { deep: true })

// Auto-dismiss refresh error
watch(refreshError, (newError) => {
  if (newError) {
    setTimeout(() => clearRefreshError(), 5000)
  }
})
</script>

<template>
  <div data-testid="face-conversations-page">
    <!-- Page Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Messages</h1>
        <p class="mt-1 text-slate-500">Vos conversations avec les producteurs</p>
      </div>

      <!-- Refresh Button (list) -->
      <button
        v-if="hasConversations && !isListLoading"
        type="button"
        :disabled="isListRefreshing"
        class="flex h-10 w-10 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 disabled:pointer-events-none disabled:opacity-50"
        aria-label="Actualiser les conversations"
        @click="handleRefreshList"
      >
        <RefreshCw :class="['h-5 w-5', isListRefreshing && 'animate-spin']" />
      </button>
    </div>

    <!-- Main Content -->
    <div class="flex gap-6 h-[calc(100vh-220px)] min-h-[500px]">
      <!-- Left Panel: Conversations List -->
      <div
        class="w-full lg:w-80 flex-shrink-0 flex flex-col bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
        :class="{ 'hidden lg:flex': hasSelectedConversation && !isMobile }"
      >
        <!-- List Loading -->
        <div v-if="isListLoading" class="flex-1 flex flex-col items-center justify-center py-12">
          <Loader2 class="h-8 w-8 animate-spin text-primary" />
          <p class="mt-4 text-sm text-slate-500">Chargement...</p>
        </div>

        <!-- List Error -->
        <div v-else-if="listError && !hasConversations" class="flex-1 flex flex-col items-center justify-center p-6 text-center">
          <div class="mb-4 rounded-full bg-red-100 p-3">
            <AlertCircle class="h-6 w-6 text-red-500" />
          </div>
          <p class="text-sm text-slate-600">{{ listError }}</p>
          <button
            type="button"
            class="mt-4 text-sm font-medium text-primary hover:underline"
            @click="loadConversations()"
          >
            Réessayer
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="!hasConversations" class="flex-1 flex flex-col items-center justify-center p-6 text-center">
          <div class="mb-4 rounded-full bg-slate-100 p-4">
            <MessageSquare class="h-8 w-8 text-slate-400" />
          </div>
          <h3 class="font-semibold text-slate-800">Aucune conversation</h3>
          <p class="mt-1 text-sm text-slate-500 max-w-xs">
            Les discussions apparaîtront ici lorsqu'une candidature sera acceptée.
          </p>
          <button
            type="button"
            class="mt-4 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors"
            @click="goToBrowseMissions"
          >
            Parcourir les missions
          </button>
        </div>

        <!-- Conversations List -->
        <div v-else class="flex-1 overflow-y-auto">
          <div class="p-2 space-y-1">
            <button
              v-for="conversation in conversations"
              :key="conversation.id"
              type="button"
              class="w-full flex items-center gap-3 p-3 rounded-xl text-left transition-all hover:bg-slate-50"
              :class="{
                'bg-primary/5 border-l-4 border-primary': selectedConversationId === conversation.id,
                'bg-primary/5': conversation.unread_count > 0 && selectedConversationId !== conversation.id
              }"
              @click="selectConversation(conversation)"
            >
              <!-- Avatar -->
              <div
                class="relative h-12 w-12 shrink-0 overflow-hidden rounded-full"
                :class="conversation.unread_count > 0 ? 'ring-2 ring-primary' : 'ring-1 ring-gray-200'"
              >
                <img
                  v-if="conversation.other_participant.photo_url"
                  :src="conversation.other_participant.photo_url"
                  :alt="conversation.other_participant.name"
                  class="h-full w-full object-cover"
                />
                <div
                  v-else
                  class="flex h-full w-full items-center justify-center bg-primary/10 text-sm font-bold text-primary"
                >
                  {{ getInitials(conversation.other_participant.name) }}
                </div>
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <p class="truncate text-sm font-medium text-slate-800">
                    {{ conversation.other_participant.name }}
                  </p>
                  <span v-if="conversation.latest_message" class="text-xs text-slate-400">
                    {{ formatTime(conversation.latest_message.created_at) }}
                  </span>
                </div>
                <p class="truncate text-xs text-slate-500 mt-0.5">
                  {{ conversation.mission_title }}
                </p>
                <div class="flex items-center justify-between gap-2 mt-1">
                  <p class="truncate text-sm" :class="conversation.unread_count > 0 ? 'font-medium text-slate-700' : 'text-slate-500'">
                    {{ conversation.latest_message ? (conversation.latest_message.is_mine ? 'Vous: ' : '') + conversation.latest_message.content : 'Nouvelle conversation' }}
                  </p>
                  <span
                    v-if="conversation.unread_count > 0"
                    class="shrink-0 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-white"
                  >
                    {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
                  </span>
                </div>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Right Panel: Conversation View (Desktop only) -->
      <div class="hidden lg:flex flex-1 flex-col bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- No conversation selected -->
        <div v-if="!hasSelectedConversation" class="flex-1 flex flex-col items-center justify-center p-8 text-center">
          <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-slate-100">
            <MessageSquare class="h-10 w-10 text-slate-400" />
          </div>
          <h2 class="text-lg font-semibold text-slate-800">Sélectionnez une conversation</h2>
          <p class="mt-2 text-sm text-slate-500 max-w-xs">
            Choisissez une conversation dans la liste pour afficher les messages.
          </p>
        </div>

        <!-- Conversation Loading -->
        <div v-else-if="isConversationLoading" class="flex-1 flex flex-col items-center justify-center">
          <Loader2 class="h-10 w-10 animate-spin text-primary" />
          <p class="mt-4 text-sm text-slate-500">Chargement de la discussion...</p>
        </div>

        <!-- Conversation Error -->
        <div v-else-if="conversationError" class="flex-1 flex flex-col items-center justify-center p-6 text-center">
          <div class="mb-4 rounded-full bg-red-100 p-4">
            <AlertCircle class="h-8 w-8 text-red-500" />
          </div>
          <h3 class="font-semibold text-slate-800">Une erreur est survenue</h3>
          <p class="mt-2 text-sm text-slate-500">{{ conversationError }}</p>
          <button
            type="button"
            class="mt-4 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors"
            @click="retryLoadingConversation"
          >
            Réessayer
          </button>
        </div>

        <!-- Conversation Content -->
        <template v-else>
          <!-- Header -->
          <ConversationHeader
            v-if="otherParticipant"
            :participant="otherParticipant"
            :mission-title="missionTitle"
            :is-refreshing="isConversationRefreshing"
            :show-back="false"
            @refresh="handleRefreshConversation"
          />

          <!-- Refresh Error Toast -->
          <div
            v-if="refreshError"
            class="mx-4 mt-2 rounded-lg bg-red-100 px-4 py-2 text-sm text-red-700"
          >
            {{ refreshError }}
          </div>

          <!-- Messages Area -->
          <div ref="scrollContainer" class="flex-1 overflow-y-auto px-4 py-6">
            <!-- Empty messages -->
            <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center text-center">
              <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                <MessageSquare class="h-8 w-8 text-primary" />
              </div>
              <h3 class="font-semibold text-slate-800">Commencez la discussion !</h3>
              <p class="mt-2 text-sm text-slate-500 max-w-xs">
                Envoyez un message pour coordonner les détails de la mission.
              </p>
            </div>

            <!-- Messages list -->
            <div v-else class="space-y-1">
              <MessageBubble v-for="message in messages" :key="message.id" :message="message" />
            </div>
          </div>

          <!-- Message Input -->
          <MessageInput
            v-model="newMessageText"
            :is-loading="isSending"
            :error="sendError"
            @submit="handleSendMessage"
          />
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
.bg-primary {
  background-color: var(--color-weact, #198496);
}
</style>
