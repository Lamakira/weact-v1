<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { ConversationListItem } from '../types'

/**
 * Props
 */
const props = defineProps<{
  conversation: ConversationListItem
  routeName: 'face-conversation' | 'producer-conversation'
}>()

/**
 * Computed: Has unread messages
 */
const hasUnread = computed(() => props.conversation.unread_count > 0)

/**
 * Computed: Participant initials for fallback avatar
 */
const participantInitials = computed(() => {
  return props.conversation.other_participant.name.charAt(0).toUpperCase()
})

/**
 * Computed: Format relative time for last message
 */
const formattedTime = computed(() => {
  if (!props.conversation.latest_message) return ''

  const messageDate = new Date(props.conversation.latest_message.created_at)
  const now = new Date()
  const diffMs = now.getTime() - messageDate.getTime()
  const diffMins = Math.floor(diffMs / (1000 * 60))
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

  if (diffMins < 1) return "À l'instant"
  if (diffMins < 60) return `${diffMins} min`
  if (diffHours < 24) return `${diffHours}h`
  if (diffDays < 7) return `${diffDays}j`

  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: '2-digit',
  }).format(messageDate)
})

/**
 * Computed: Last message preview text
 */
const lastMessagePreview = computed(() => {
  if (!props.conversation.latest_message) return 'Nouvelle conversation'

  const prefix = props.conversation.latest_message.is_mine ? 'Vous: ' : ''
  return prefix + props.conversation.latest_message.content
})
</script>

<template>
  <RouterLink
    :to="{ name: routeName, params: { conversationId: conversation.id } }"
    class="flex items-center gap-3 rounded-xl border border-border bg-card p-3 transition-all hover:border-primary/50 hover:shadow-md sm:p-4"
    :class="{ 'bg-primary/5 border-primary/20': hasUnread }"
  >
    <!-- Avatar -->
    <div
      class="relative h-12 w-12 shrink-0 overflow-hidden rounded-full border-2"
      :class="hasUnread ? 'border-primary' : 'border-border'"
    >
      <img
        v-if="conversation.other_participant.photo_url"
        :src="conversation.other_participant.photo_url"
        :alt="conversation.other_participant.name"
        class="h-full w-full object-cover"
      />
      <div
        v-else
        class="flex h-full w-full items-center justify-center bg-primary/10 text-sm font-bold uppercase text-primary"
      >
        {{ participantInitials }}
      </div>
    </div>

    <!-- Content Area -->
    <div class="min-w-0 flex-1">
      <!-- Name and Time -->
      <div class="flex items-center justify-between gap-2">
        <p
          class="truncate text-sm"
          :class="hasUnread ? 'font-semibold text-foreground' : 'font-medium text-foreground'"
        >
          {{ conversation.other_participant.name }}
        </p>
        <span class="shrink-0 text-xs text-muted-foreground">
          {{ formattedTime }}
        </span>
      </div>

      <!-- Mission Title -->
      <p class="mt-0.5 truncate text-xs text-muted-foreground">
        {{ conversation.mission_title }}
      </p>

      <!-- Last Message Preview and Unread Badge -->
      <div class="mt-1 flex items-center justify-between gap-2">
        <p
          class="truncate text-sm"
          :class="hasUnread ? 'font-medium text-foreground' : 'text-muted-foreground'"
        >
          {{ lastMessagePreview }}
        </p>

        <!-- Unread Badge -->
        <span
          v-if="hasUnread"
          class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground"
        >
          {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
        </span>
      </div>
    </div>
  </RouterLink>
</template>
