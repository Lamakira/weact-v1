<script setup lang="ts">
import { computed } from 'vue'
import type { Message } from '../types'
import { formatClock } from '../utils/messageFormat'

const props = defineProps<{
  message: Message
  /** Affiche « Lu HH:mm » sous la bulle (dernier message envoyé lu par l'autre) */
  showReadReceipt?: boolean
}>()

const time = computed(() => formatClock(props.message.created_at))
const readTime = computed(() => (props.message.read_at ? formatClock(props.message.read_at) : ''))
</script>

<template>
  <div
    class="flex w-full flex-col"
    :class="message.is_own_message ? 'items-end' : 'items-start'"
    data-testid="message-bubble"
    :data-own="message.is_own_message"
  >
    <div
      class="max-w-[82%] whitespace-pre-wrap break-words rounded-[12px] px-3.5 py-2 text-[14px] leading-[1.45] lg:max-w-[72%] lg:text-[13.5px]"
      :class="
        message.is_own_message
          ? 'bg-weact-600 text-white'
          : 'bg-white text-ink ring-1 ring-line'
      "
    >
      {{ message.content }}
    </div>
    <p
      v-if="showReadReceipt && message.is_own_message && message.read_at"
      class="mt-1 text-[11px] text-ink-3"
      data-testid="read-receipt"
    >
      Lu {{ readTime }}
    </p>
    <p v-else class="mt-1 text-[11px] text-ink-3">{{ time }}</p>
  </div>
</template>
