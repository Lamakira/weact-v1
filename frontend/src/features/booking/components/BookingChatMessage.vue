<script setup lang="ts">
import { computed } from 'vue'
import type { BookingMessage } from '../types'

interface Props {
  message: BookingMessage
  isOwn: boolean
}

const props = defineProps<Props>()

const formattedTime = computed((): string => {
  const date = new Date(props.message.created_at)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

  if (diffDays === 0) {
    return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  }
  if (diffDays === 1) {
    return `Hier ${date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`
  }
  return date.toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
})
</script>

<template>
  <div
    class="flex items-end gap-2"
    :class="isOwn ? 'flex-row-reverse' : 'flex-row'"
    data-testid="chat-message"
  >
    <div class="flex flex-col" :class="isOwn ? 'items-end' : 'items-start'" style="max-width: 75%">
      <!-- Sender name (only for other party's messages) -->
      <span v-if="!isOwn" class="text-xs text-gray-500 mb-1 px-1">
        {{ message.sender_name }}
      </span>

      <!-- Message bubble -->
      <div
        class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed"
        :class="
          isOwn
            ? 'bg-[#198496] text-white rounded-br-sm'
            : 'bg-white text-gray-800 border border-gray-100 shadow-sm rounded-bl-sm'
        "
      >
        <p class="whitespace-pre-wrap break-words">{{ message.content }}</p>
      </div>

      <!-- Timestamp -->
      <span
        class="text-xs mt-1 px-1"
        :class="isOwn ? 'text-gray-400' : 'text-gray-400'"
      >
        {{ formattedTime }}
      </span>
    </div>
  </div>
</template>
