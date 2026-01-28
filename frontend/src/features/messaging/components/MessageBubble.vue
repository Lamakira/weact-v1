<script setup lang="ts">
import { computed } from 'vue'
import type { Message } from '../types'

const props = defineProps<{
  message: Message
}>()

const formattedTime = computed(() => {
  try {
    return new Intl.DateTimeFormat('fr-FR', {
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(props.message.created_at))
  } catch {
    return ''
  }
})
</script>

<template>
  <div
    :class="[
      'flex w-full mb-3 px-2',
      message.is_own_message ? 'justify-end' : 'justify-start',
    ]"
  >
    <div
      :class="[
        'flex flex-col max-w-[80%]',
        message.is_own_message ? 'items-end' : 'items-start',
      ]"
    >
      <!-- Sender name for other's messages -->
      <span
        v-if="!message.is_own_message"
        class="text-[11px] font-semibold text-muted-foreground ml-2 mb-1"
      >
        {{ message.sender_name }}
      </span>

      <!-- Message Bubble -->
      <div
        :class="[
          'px-3 py-2 text-sm leading-relaxed shadow-sm break-words whitespace-pre-wrap',
          message.is_own_message
            ? 'bg-primary text-primary-foreground rounded-l-xl rounded-tr-xl'
            : 'bg-muted text-foreground rounded-r-xl rounded-tl-xl',
        ]"
      >
        {{ message.content }}
      </div>

      <!-- Footer: Timestamp + Status -->
      <div
        :class="[
          'flex items-center gap-1 mt-1 px-1',
          message.is_own_message ? 'flex-row' : 'flex-row-reverse',
        ]"
      >
        <span class="text-[10px] text-muted-foreground/80">
          {{ formattedTime }}
        </span>

        <!-- Read Status Indicators (own messages only) -->
        <div v-if="message.is_own_message" class="flex items-center ml-0.5">
          <template v-if="message.read_at">
            <!-- Double Check (Read) -->
            <svg
              class="w-3 h-3 text-primary"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="20 6 9 17 4 12" />
              <polyline points="22 10 13.5 18.5 11 16" />
            </svg>
          </template>
          <template v-else>
            <!-- Single Check (Sent) -->
            <svg
              class="w-3 h-3 text-muted-foreground/50"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
