<script setup lang="ts">
import { computed } from 'vue'
import RStatusDot from '@/components/regie/RStatusDot.vue'
import { candidatureStatusDot } from '@/components/regie/statusTone'
import ParticipantAvatar from './ParticipantAvatar.vue'
import type { ConversationListItem } from '../types'
import { formatListTime } from '../utils/messageFormat'

const props = defineProps<{
  conversation: ConversationListItem
  selected?: boolean
}>()

const emit = defineEmits<{ (e: 'select', conversation: ConversationListItem): void }>()

const hasUnread = computed(() => props.conversation.unread_count > 0)

const time = computed(() =>
  props.conversation.latest_message
    ? formatListTime(props.conversation.latest_message.created_at)
    : '',
)

const dot = computed(() =>
  props.conversation.context ? candidatureStatusDot(props.conversation.context.candidature_status) : null,
)

const contextLine = computed(() => {
  const context = props.conversation.context
  return context ? `${context.type_label} · ${context.title}` : props.conversation.mission_title
})

const preview = computed(() => {
  const latest = props.conversation.latest_message
  if (!latest) return 'Nouvelle conversation'
  return (latest.is_mine ? 'Vous : ' : '') + latest.content
})
</script>

<template>
  <li class="relative">
    <span
      v-if="selected"
      class="absolute inset-y-0 left-0 w-[3px] bg-weact-600"
      aria-hidden="true"
    />
    <button
      type="button"
      class="flex w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-weact-600"
      :class="selected ? 'bg-weact-600/5' : ''"
      :aria-current="selected ? 'true' : undefined"
      data-testid="conversation-item"
      :data-unread="hasUnread"
      @click="emit('select', conversation)"
    >
      <ParticipantAvatar
        :participant="conversation.other_participant"
        size-class="size-11 lg:size-9"
      />
      <span class="min-w-0 flex-1">
        <span class="flex items-baseline justify-between gap-2">
          <span
            class="truncate text-[14.5px] text-ink lg:text-[13.5px]"
            :class="hasUnread ? 'font-semibold' : 'font-medium'"
          >
            {{ conversation.other_participant.name }}
          </span>
          <span
            class="shrink-0 text-[12px] lg:text-[11.5px]"
            :class="hasUnread ? 'font-semibold text-weact-700' : 'text-ink-3'"
          >
            {{ time }}
          </span>
        </span>
        <span class="flex items-center gap-1.5 text-[12px] text-ink-3 lg:text-[11.5px]">
          <RStatusDot v-if="dot" :tone="dot.tone" :label="dot.label" hide-label />
          <span class="truncate">{{ contextLine }}</span>
        </span>
        <span class="mt-0.5 flex items-center gap-2">
          <span
            class="flex-1 truncate text-[13.5px] lg:text-[12.5px]"
            :class="hasUnread ? 'font-semibold text-ink' : 'text-ink-3'"
          >
            {{ preview }}
          </span>
          <span
            v-if="hasUnread"
            class="inline-flex h-[18px] min-w-[18px] shrink-0 items-center justify-center rounded-full bg-weact-600 px-1.5 text-[11px] font-semibold text-white"
            data-testid="unread-badge"
          >
            {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
            <span class="sr-only">message{{ conversation.unread_count > 1 ? 's' : '' }} non lu{{ conversation.unread_count > 1 ? 's' : '' }}</span>
          </span>
        </span>
      </span>
    </button>
  </li>
</template>
