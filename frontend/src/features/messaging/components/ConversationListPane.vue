<script setup lang="ts">
import { computed, ref } from 'vue'
import { Loader2, AlertCircle, MessageSquare, RefreshCw } from 'lucide-vue-next'
import RPillGroup, { type RPillOption } from '@/components/regie/RPillGroup.vue'
import ConversationListItem from './ConversationListItem.vue'
import type { ConversationListItem as ConversationListItemType } from '../types'

const props = defineProps<{
  conversations: ConversationListItemType[]
  selectedId: string | null
  isLoading: boolean
  isRefreshing: boolean
  error: string | null
  /** Libellé du bouton de l'état vide (parcourir les missions / voir mes missions) */
  emptyActionLabel: string
  emptyMessage: string
}>()

const emit = defineEmits<{
  (e: 'select', conversation: ConversationListItemType): void
  (e: 'refresh'): void
  (e: 'retry'): void
  (e: 'empty-action'): void
}>()

type Filter = string // 'all' | 'unread' | 'mission' | 'ugc'
const filter = ref<Filter>('all')

const unreadCount = computed(() => props.conversations.filter((c) => c.unread_count > 0).length)

// Une pilule par type de contexte réellement présent
const typeOptions = computed(() => {
  const seen = new Map<string, string>()
  for (const conversation of props.conversations) {
    const context = conversation.context
    if (context && !seen.has(context.type)) seen.set(context.type, context.type)
  }
  const labels: Record<string, string> = { mission: 'Missions', ugc: 'UGC' }
  return ['mission', 'ugc']
    .filter((type) => seen.has(type))
    .map((type) => ({
      value: type,
      label: labels[type] ?? type,
      count: props.conversations.filter((c) => c.context?.type === type).length,
    }))
})

const options = computed<RPillOption[]>(() => [
  { value: 'all', label: 'Toutes' },
  { value: 'unread', label: 'Non lues', count: unreadCount.value },
  ...typeOptions.value,
])

const visible = computed(() => {
  if (filter.value === 'unread') return props.conversations.filter((c) => c.unread_count > 0)
  if (filter.value === 'all') return props.conversations
  return props.conversations.filter((c) => c.context?.type === filter.value)
})

const hasConversations = computed(() => props.conversations.length > 0)
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col" data-testid="conversation-list-pane">
    <div class="flex h-14 shrink-0 items-center justify-between border-b border-line px-4">
      <h1 class="text-[18px] font-semibold text-ink lg:text-[16px]">Messages</h1>
      <button
        v-if="hasConversations && !isLoading"
        type="button"
        :disabled="isRefreshing"
        class="flex size-10 items-center justify-center rounded-full text-ink-3 transition-colors hover:bg-sidebar hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 disabled:pointer-events-none disabled:opacity-50 lg:size-8"
        aria-label="Actualiser les conversations"
        @click="emit('refresh')"
      >
        <RefreshCw :class="['size-4', isRefreshing && 'animate-spin']" />
      </button>
    </div>

    <div v-if="hasConversations" class="shrink-0 overflow-x-auto border-b border-line p-3">
      <RPillGroup v-model="filter" :options="options" group-label="Filtrer les conversations" class="flex-nowrap" />
    </div>

    <div v-if="isLoading" class="flex flex-1 flex-col items-center justify-center py-12">
      <Loader2 class="size-8 animate-spin text-weact-600" />
      <p class="mt-4 text-sm text-ink-3">Chargement...</p>
    </div>

    <div
      v-else-if="error && !hasConversations"
      class="flex flex-1 flex-col items-center justify-center p-6 text-center"
    >
      <AlertCircle class="mb-3 size-6 text-destructive" />
      <p class="text-sm text-ink-2">{{ error }}</p>
      <button
        type="button"
        class="mt-4 text-sm font-semibold text-weact-700 hover:underline"
        @click="emit('retry')"
      >
        Réessayer
      </button>
    </div>

    <div
      v-else-if="!hasConversations"
      class="flex flex-1 flex-col items-center justify-center p-6 text-center"
      data-testid="conversations-empty"
    >
      <div class="mb-4 rounded-full bg-sidebar p-4">
        <MessageSquare class="size-8 text-ink-3" />
      </div>
      <h2 class="font-semibold text-ink">Aucune conversation</h2>
      <p class="mt-1 max-w-xs text-sm text-ink-3">{{ emptyMessage }}</p>
      <button
        type="button"
        class="mt-4 inline-flex h-10 items-center rounded-[10px] bg-weact-600 px-4 text-[13px] font-semibold text-white hover:bg-weact-700"
        @click="emit('empty-action')"
      >
        {{ emptyActionLabel }}
      </button>
    </div>

    <template v-else>
      <p
        v-if="visible.length === 0"
        class="p-6 text-center text-sm text-ink-3"
        data-testid="conversations-filter-empty"
      >
        Aucune conversation pour ce filtre.
      </p>
      <ul v-else class="min-h-0 flex-1 divide-y divide-line overflow-y-auto" data-testid="conversation-list">
        <ConversationListItem
          v-for="conversation in visible"
          :key="conversation.id"
          :conversation="conversation"
          :selected="conversation.id === selectedId"
          @select="emit('select', $event)"
        />
      </ul>
    </template>
  </div>
</template>
