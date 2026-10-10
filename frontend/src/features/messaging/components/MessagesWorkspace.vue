<script setup lang="ts">
/**
 * Messagerie « Régie » partagée Face / Producteur : liste, fil et panneau de
 * contexte (desktop >= lg), liste puis fil plein écran avec barre de contexte
 * collante (mobile). Le basculement est piloté par une media query réactive.
 *
 * Desktop : la conversation sélectionnée est un état local (aucun changement de
 * route, donc aucun remontage). Mobile : la sélection pousse la route
 * `<role>-conversation`, ce qui garde le bouton retour du navigateur cohérent ;
 * ce même composant sert les deux routes (liste et deep link vers un fil).
 */
import { computed, onActivated, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { MessageSquare } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useMediaQuery } from '@/composables/useMediaQuery'
import EmailVerificationRequired from '@/components/EmailVerificationRequired.vue'
import { useConversationsList } from '../composables/useConversationsList'
import { useProducerConversationsList } from '../composables/useProducerConversationsList'
import { useConversation } from '../composables/useConversation'
import { useProducerConversation } from '../composables/useProducerConversation'
import { useSendMessage } from '../composables/useSendMessage'
import { useSendProducerMessage } from '../composables/useSendProducerMessage'
import { useConversationRealtime } from '../composables/useConversationRealtime'
import { useConversationListRealtime } from '../composables/useConversationListRealtime'
import { messagingApi } from '../services/messagingApi'
import { applyConversationUpdate } from '../utils/conversationList'
import ConversationContextPanel from './ConversationContextPanel.vue'
import ConversationListPane from './ConversationListPane.vue'
import MessageThread from './MessageThread.vue'
import type {
  ConversationListItem,
  ConversationUpdatedBroadcast,
  MessageBroadcast,
  MessagesReadBroadcast,
} from '../types'

const props = defineProps<{ role: 'face' | 'producer' }>()

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const isDesktop = useMediaQuery('(min-width: 1024px)')

const list = props.role === 'face' ? useConversationsList() : useProducerConversationsList()
const active = props.role === 'face' ? useConversation() : useProducerConversation()
const sender = props.role === 'face' ? useSendMessage() : useSendProducerMessage()

const {
  conversations,
  isLoading: isListLoading,
  isRefreshing: isListRefreshing,
  error: listError,
  loadConversations,
  refreshConversations,
  syncConversations,
} = list

const {
  messages,
  otherParticipant,
  context,
  isLoading: isConversationLoading,
  isRefreshing: isConversationRefreshing,
  error: conversationError,
  refreshError,
  loadConversation,
  refreshConversation,
  addMessage,
  markOwnMessagesRead,
  syncConversation,
  clearRefreshError,
  reset: resetConversation,
} = active

const { isSending, error: sendError, sendMessage, resetError: resetSendError } = sender

function routeConversationId(): string | null {
  const param = route.params.conversationId
  const value = Array.isArray(param) ? param[0] : param
  return typeof value === 'string' && value.length > 0 ? value : null
}

const selectedId = ref<string | null>(routeConversationId())
const newMessageText = ref('')

const selectedItem = computed(
  () => conversations.value.find((conversation) => conversation.id === selectedId.value) ?? null,
)
const participant = computed(
  () => otherParticipant.value ?? selectedItem.value?.other_participant ?? null,
)

const showList = computed(() => isDesktop.value || selectedId.value === null)
const showThread = computed(() => isDesktop.value || selectedId.value !== null)

const emptyMessage = computed(() =>
  props.role === 'face'
    ? "Les discussions apparaîtront ici lorsqu'une candidature sera acceptée."
    : 'Les discussions apparaîtront ici lorsque vous accepterez des candidatures.',
)
const emptyActionLabel = computed(() =>
  props.role === 'face' ? 'Parcourir les missions' : 'Voir mes missions',
)

// L'ouverture d'un fil marque les messages reçus comme lus côté serveur
function markListItemRead(id: string): void {
  const item = conversations.value.find((conversation) => conversation.id === id)
  if (item) item.unread_count = 0
}

async function openConversation(id: string): Promise<void> {
  selectedId.value = id
  newMessageText.value = ''
  resetSendError()
  resetConversation()
  const ok = await loadConversation(id)
  if (ok && selectedId.value === id) markListItemRead(id)
}

function selectConversation(conversation: ConversationListItem): void {
  if (!isDesktop.value) {
    void router.push({ name: `${props.role}-conversation`, params: { conversationId: conversation.id } })
    return
  }
  if (conversation.id === selectedId.value) return
  void openConversation(conversation.id)
}

function handleBack(): void {
  selectedId.value = null
  resetConversation()
  if (routeConversationId()) void router.push({ name: `${props.role}-messages` })
}

async function handleSend(): Promise<void> {
  const id = selectedId.value
  const content = newMessageText.value.trim()
  if (!content || isSending.value || !id) return

  resetSendError()
  const sent = await sendMessage(id, content)
  if (sent) {
    addMessage(sent)
    newMessageText.value = ''
    const item = conversations.value.find((conversation) => conversation.id === id)
    if (item) {
      item.latest_message = {
        content: sent.content,
        sender_name: sent.sender_name,
        is_mine: true,
        created_at: sent.created_at,
      }
    }
  }
}

async function handleRefreshConversation(): Promise<void> {
  if (!selectedId.value) return
  clearRefreshError()
  await refreshConversation(selectedId.value)
}

function retryConversation(): void {
  if (selectedId.value) void openConversation(selectedId.value)
}

// ---------------------------------------------------------------------------
// Temps réel (Reverb) : fil ouvert + liste, repli par polling géré par les composables
// ---------------------------------------------------------------------------
function currentUserId(): number | null {
  return authStore.user?.id ?? null
}

function isTabVisible(): boolean {
  return typeof document === 'undefined' || document.visibilityState !== 'hidden'
}

// Le destinataire a le fil ouvert et l'onglet visible : on marque lu côté serveur
async function markOpenThreadRead(): Promise<void> {
  const id = selectedId.value
  if (!id || !isTabVisible()) return
  try {
    await messagingApi.markConversationRead(props.role, id)
    if (selectedId.value === id) markListItemRead(id)
  } catch {
    // Non bloquant : le prochain message ou le retour sur l'onglet réessaiera
  }
}

function handleIncomingMessage(message: MessageBroadcast): void {
  addMessage({
    id: message.id,
    content: message.content,
    sender_id: message.sender_id,
    sender_type: message.sender_type,
    sender_name: message.sender_name,
    // is_own_message absent du broadcast : calculé côté client
    is_own_message: message.sender_id === currentUserId(),
    read_at: message.read_at,
    created_at: message.created_at,
  })
  if (message.sender_id !== currentUserId()) void markOpenThreadRead()
}

function handleReadReceipt(receipt: MessagesReadBroadcast): void {
  markOwnMessagesRead(receipt.last_read_message_id, receipt.read_at)
}

function handleConversationUpdated(update: ConversationUpdatedBroadcast): void {
  const me = currentUserId()
  const viewing =
    update.conversation_id === selectedId.value
    && isTabVisible()
    && update.latest_message.sender_id !== me
  const known = applyConversationUpdate(conversations.value, update, me, viewing ? 0 : undefined)
  // Conversation absente de la liste chargée (nouvelle, ou page suivante) : on recharge
  if (!known) void syncConversations()
}

useConversationRealtime(selectedId, props.role, {
  onMessage: handleIncomingMessage,
  onRead: handleReadReceipt,
  poll: async (uuid) => {
    await syncConversation(uuid)
    if (selectedId.value === uuid) void markOpenThreadRead()
  },
})

useConversationListRealtime({
  onUpdated: handleConversationUpdated,
  poll: syncConversations,
})

// Retour sur l'onglet : les messages reçus pendant l'absence sont marqués lus
function handleVisibilityChange(): void {
  if (!isTabVisible()) return
  if (messages.value.some((message) => !message.is_own_message && !message.read_at)) {
    void markOpenThreadRead()
  }
}

// Page en cache (keep-alive) : à la réactivation, resynchroniser ce qui a pu changer
// (la toute première activation suit le montage, déjà chargé).
let isFirstActivation = true
onActivated(() => {
  if (isFirstActivation) {
    isFirstActivation = false
    return
  }
  void syncConversations()
  if (selectedId.value) void syncConversation(selectedId.value)
})

function goToMissions(): void {
  void router.push({ name: props.role === 'face' ? 'face-missions' : 'producer-missions' })
}

onMounted(() => {
  document.addEventListener('visibilitychange', handleVisibilityChange)
  void loadConversations()
  if (selectedId.value) void openConversation(selectedId.value)
})

onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', handleVisibilityChange)
})

// Auto-dismiss refresh error
watch(refreshError, (newError) => {
  if (newError) setTimeout(() => clearRefreshError(), 5000)
})
</script>

<template>
  <div data-testid="messages-workspace" :data-role="role">
    <EmailVerificationRequired
      v-if="!authStore.isEmailVerified"
      title="Messagerie non disponible"
      message="Vous devez vérifier votre adresse email pour accéder à vos messages."
    />

    <div
      v-else
      class="flex h-[calc(100dvh-10rem)] min-h-[480px] overflow-hidden rounded-panel bg-white ring-1 ring-line lg:h-[calc(100dvh-8.5rem)]"
    >
      <div
        v-if="showList"
        class="flex min-h-0 w-full shrink-0 flex-col lg:w-[296px] lg:border-r lg:border-line"
        data-testid="messages-list-column"
      >
        <ConversationListPane
          :conversations="conversations"
          :selected-id="selectedId"
          :is-loading="isListLoading"
          :is-refreshing="isListRefreshing"
          :error="listError"
          :empty-message="emptyMessage"
          :empty-action-label="emptyActionLabel"
          @select="selectConversation"
          @refresh="refreshConversations"
          @retry="loadConversations()"
          @empty-action="goToMissions"
        />
      </div>

      <template v-if="showThread">
        <div
          v-if="selectedId === null"
          class="flex min-w-0 flex-1 flex-col items-center justify-center bg-thread p-8 text-center"
          data-testid="thread-placeholder"
        >
          <div class="mb-5 flex size-16 items-center justify-center rounded-full bg-white ring-1 ring-line">
            <MessageSquare class="size-8 text-ink-3" />
          </div>
          <h2 class="text-lg font-semibold text-ink">Sélectionnez une conversation</h2>
          <p class="mt-2 max-w-xs text-sm text-ink-3">
            Choisissez une conversation dans la liste pour afficher les messages.
          </p>
        </div>

        <template v-else>
          <MessageThread
            v-model="newMessageText"
            :participant="participant"
            :context="context"
            :role="role"
            :messages="messages"
            :is-loading="isConversationLoading"
            :is-refreshing="isConversationRefreshing"
            :error="conversationError"
            :refresh-error="refreshError"
            :is-sending="isSending"
            :send-error="sendError"
            :compact="!isDesktop"
            @submit="handleSend"
            @back="handleBack"
            @refresh="handleRefreshConversation"
            @retry="retryConversation"
          />
          <ConversationContextPanel
            v-if="isDesktop && participant && !isConversationLoading && !conversationError"
            :context="context"
            :participant="participant"
            :role="role"
          />
        </template>
      </template>
    </div>
  </div>
</template>
