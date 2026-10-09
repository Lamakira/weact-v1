<script setup lang="ts">
/**
 * UnreadMessagesPanel — module « Messages non lus » du dashboard Producteur.
 * Les 3 dernières conversations avec des messages non lus (avatar, nom, mission,
 * extrait, heure) ; chaque ligne ouvre la conversation.
 */
import { RouterLink } from 'vue-router'
import { MessageCircle } from 'lucide-vue-next'
import RPanel from '@/components/regie/RPanel.vue'
import { Skeleton } from '@/components/ui/skeleton'
import { formatMessageTime } from '@/lib/formatAge'
import type { ConversationListItem } from '@/features/messaging/types'

interface Props {
  items: ConversationListItem[]
  /** Nombre de conversations non lues connues */
  total: number
  isLoading?: boolean
  error?: string | null
  now?: Date
}

withDefaults(defineProps<Props>(), {
  isLoading: false,
  error: null,
  now: () => new Date(),
})

defineEmits<{ retry: [] }>()

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  const first = parts[0]?.charAt(0) ?? ''
  const second = parts.length > 1 ? (parts[parts.length - 1]?.charAt(0) ?? '') : ''
  return (first + second).toUpperCase() || '?'
}

function time(item: ConversationListItem, now: Date): string {
  return formatMessageTime(item.latest_message?.created_at ?? item.updated_at, now)
}
</script>

<template>
  <RPanel title="Messages non lus" data-testid="module-unread-messages">
    <template #actions>
      <span
        v-if="!isLoading && !error && total > 0"
        class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-weact-50 px-1.5 text-[11.5px] font-semibold text-weact-700"
        data-testid="unread-messages-count"
      >{{ total }}</span>
      <RouterLink
        :to="{ name: 'producer-messages' }"
        class="text-[12.5px] font-medium text-ink-2 hover:text-ink hover:underline"
        data-testid="unread-messages-see-all"
      >Tout voir</RouterLink>
    </template>

    <div v-if="isLoading" class="space-y-3 p-4" data-testid="unread-messages-loading">
      <Skeleton class="h-10 w-full" />
      <Skeleton class="h-10 w-full" />
    </div>

    <p v-else-if="error" class="p-4 text-dash text-ink-2" role="alert" data-testid="unread-messages-error">
      {{ error }}
      <button type="button" class="font-semibold text-weact-700 hover:underline" @click="$emit('retry')">
        Réessayer
      </button>
    </p>

    <div
      v-else-if="items.length === 0"
      class="flex flex-col items-center gap-2 px-4 py-8 text-center"
      data-testid="unread-messages-empty"
    >
      <MessageCircle class="size-5 text-ink-3" aria-hidden="true" />
      <p class="text-dash text-ink-2">Aucun message non lu.</p>
    </div>

    <ul v-else class="divide-y divide-line" data-testid="unread-messages-list">
      <li v-for="item in items" :key="item.id" data-testid="unread-message-row">
        <RouterLink
          :to="{ name: 'producer-conversation', params: { conversationId: item.id } }"
          class="flex items-center gap-3 px-4 py-3 hover:bg-sidebar sm:px-5"
        >
          <span
            class="grid size-8 shrink-0 place-items-center overflow-hidden rounded-full bg-sidebar text-[11px] font-semibold text-ink-2"
            aria-hidden="true"
          >
            <img
              v-if="item.other_participant.profile_photo_thumbnail_url ?? item.other_participant.photo_url"
              :src="(item.other_participant.profile_photo_thumbnail_url ?? item.other_participant.photo_url)!"
              alt=""
              class="size-full object-cover"
            />
            <template v-else>{{ initials(item.other_participant.name) }}</template>
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-dash font-semibold text-ink">
              {{ item.other_participant.name }}
              <span class="font-normal text-ink-3">· {{ item.mission_title }}</span>
            </p>
            <p v-if="item.latest_message" class="truncate text-[12.5px] text-ink-2">
              {{ item.latest_message.content }}
            </p>
          </div>
          <span class="shrink-0 text-[11.5px] text-ink-3" data-testid="unread-message-time">{{ time(item, now) }}</span>
        </RouterLink>
      </li>
    </ul>
  </RPanel>
</template>
