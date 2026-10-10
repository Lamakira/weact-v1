<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { ArrowLeft, Loader2, MessageSquare, RefreshCw } from 'lucide-vue-next'
import ConversationContextBar from './ConversationContextBar.vue'
import MessageBubble from './MessageBubble.vue'
import MessageInput from './MessageInput.vue'
import ParticipantAvatar from './ParticipantAvatar.vue'
import type { ConversationContext, Message, OtherParticipant } from '../types'
import { dayKey, formatDayLabel } from '../utils/messageFormat'

const props = defineProps<{
  participant: OtherParticipant | null
  context: ConversationContext | null
  role: 'face' | 'producer'
  messages: Message[]
  isLoading: boolean
  isRefreshing: boolean
  error: string | null
  refreshError: string | null
  isSending: boolean
  sendError: string | null
  modelValue: string
  /** Mobile : bouton retour + barre de contexte collante */
  compact: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'submit'): void
  (e: 'back'): void
  (e: 'refresh'): void
  (e: 'retry'): void
}>()

const scrollContainer = ref<HTMLElement | null>(null)

const scrollToBottom = async (behavior: ScrollBehavior = 'smooth') => {
  await nextTick()
  scrollContainer.value?.scrollTo?.({ top: scrollContainer.value.scrollHeight, behavior })
}

type ThreadItem =
  | { kind: 'day'; key: string; label: string }
  | { kind: 'message'; key: string; message: Message }

const items = computed<ThreadItem[]>(() => {
  const list: ThreadItem[] = []
  let currentDay = ''
  for (const message of props.messages) {
    const key = dayKey(message.created_at)
    if (key !== currentDay) {
      currentDay = key
      list.push({ kind: 'day', key: `day-${key}`, label: formatDayLabel(message.created_at) })
    }
    list.push({ kind: 'message', key: `msg-${message.id}`, message })
  }
  return list
})

// « Lu » uniquement sur le dernier message envoyé que l'autre a lu
const lastReadOwnId = computed(() => {
  for (let i = props.messages.length - 1; i >= 0; i--) {
    const message = props.messages[i]!
    if (message.is_own_message && message.read_at) return message.id
  }
  return null
})

const roleLabel = computed(() => (props.participant?.type === 'producer' ? 'Producteur' : 'Face'))

watch(
  () => props.messages,
  () => void scrollToBottom('smooth'),
  { deep: true },
)
watch(
  () => props.isLoading,
  (loading, wasLoading) => {
    if (wasLoading && !loading) void scrollToBottom('auto')
  },
)
onMounted(() => void scrollToBottom('auto'))
</script>

<template>
  <div class="flex min-h-0 min-w-0 flex-1 flex-col bg-white" data-testid="message-thread">
    <header
      v-if="participant"
      class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-2 lg:gap-3 lg:px-5"
    >
      <button
        v-if="compact"
        type="button"
        class="flex size-10 items-center justify-center rounded-full text-ink-2 hover:bg-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600"
        aria-label="Retour aux conversations"
        data-testid="thread-back"
        @click="emit('back')"
      >
        <ArrowLeft class="size-5" />
      </button>
      <ParticipantAvatar :participant="participant" size-class="size-8" />
      <div class="min-w-0 flex-1">
        <h2 class="truncate text-[14.5px] font-semibold leading-tight text-ink lg:text-[14px]">
          {{ participant.name }}
        </h2>
        <p class="truncate text-[12px] text-ink-3">
          {{ roleLabel }}<template v-if="context"> · {{ context.title }}</template>
        </p>
      </div>
      <button
        type="button"
        :disabled="isRefreshing"
        class="flex size-10 shrink-0 items-center justify-center rounded-full text-ink-3 transition-colors hover:bg-sidebar hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 disabled:pointer-events-none disabled:opacity-50 lg:size-8"
        aria-label="Actualiser les messages"
        data-testid="thread-refresh"
        @click="emit('refresh')"
      >
        <RefreshCw :class="['size-4', isRefreshing && 'animate-spin']" />
      </button>
    </header>

    <ConversationContextBar v-if="compact && context && !isLoading" :context="context" :role="role" />

    <div
      v-if="refreshError"
      class="mx-4 mt-2 rounded-lg bg-destructive/10 px-4 py-2 text-sm text-destructive"
      role="alert"
    >
      {{ refreshError }}
    </div>

    <div
      ref="scrollContainer"
      class="relative min-h-0 flex-1 space-y-2 overflow-y-auto bg-thread px-3 py-4 lg:px-5"
      data-testid="thread-scroll"
    >
      <div v-if="isLoading" class="absolute inset-0 flex flex-col items-center justify-center gap-3">
        <Loader2 class="size-8 animate-spin text-weact-600" />
        <p class="text-sm text-ink-3">Chargement de la discussion...</p>
      </div>

      <div
        v-else-if="error"
        class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center"
        data-testid="thread-error"
      >
        <h3 class="font-semibold text-ink">Une erreur est survenue</h3>
        <p class="mt-2 max-w-xs text-sm text-ink-3">{{ error }}</p>
        <button
          type="button"
          class="mt-4 inline-flex h-10 items-center rounded-[10px] bg-weact-600 px-4 text-[13px] font-semibold text-white hover:bg-weact-700"
          @click="emit('retry')"
        >
          Réessayer
        </button>
      </div>

      <div
        v-else-if="messages.length === 0"
        class="flex h-full flex-col items-center justify-center p-8 text-center"
        data-testid="thread-empty"
      >
        <div class="mb-4 flex size-16 items-center justify-center rounded-full bg-white ring-1 ring-line">
          <MessageSquare class="size-8 text-weact-600" />
        </div>
        <h3 class="font-semibold text-ink">Commencez la discussion !</h3>
        <p class="mt-2 max-w-xs text-sm text-ink-3">
          Envoyez un message pour coordonner les détails avec {{ participant?.name }}.
        </p>
      </div>

      <template v-else>
        <template v-for="item in items" :key="item.key">
          <div
            v-if="item.kind === 'day'"
            class="flex items-center gap-3 py-1 text-[11.5px] text-ink-3"
            data-testid="day-separator"
          >
            <span class="h-px flex-1 bg-line" />
            {{ item.label }}
            <span class="h-px flex-1 bg-line" />
          </div>
          <MessageBubble
            v-else
            :message="item.message"
            :show-read-receipt="item.message.id === lastReadOwnId"
          />
        </template>
      </template>
    </div>

    <MessageInput
      v-if="!isLoading && !error"
      :model-value="modelValue"
      :is-loading="isSending"
      :error="sendError"
      :placeholder="participant ? `Répondre à ${participant.name}…` : 'Écrivez votre message...'"
      @update:model-value="emit('update:modelValue', $event)"
      @submit="emit('submit')"
    />
  </div>
</template>
