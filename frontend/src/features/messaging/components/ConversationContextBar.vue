<script setup lang="ts">
/**
 * Barre de contexte collante (mobile) : « Payé · <titre> · <date> », dépliable pour
 * afficher les étapes et les montants.
 */
import { computed, ref } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import RStatusDot from '@/components/regie/RStatusDot.vue'
import { candidatureStatusDot } from '@/components/regie/statusTone'
import ConversationContextDetails from './ConversationContextDetails.vue'
import type { ConversationContext } from '../types'
import { formatContextDate } from '../utils/messageFormat'

const props = defineProps<{
  context: ConversationContext
  role: 'face' | 'producer'
}>()

const open = ref(false)

const dot = computed(() => candidatureStatusDot(props.context.candidature_status))
const statusLabel = computed(() => props.context.step?.label ?? props.context.candidature_status_label)
const date = computed(() => formatContextDate(props.context.date_tournage))
</script>

<template>
  <div class="sticky top-0 z-10 shrink-0 border-b border-line bg-sidebar" data-testid="conversation-context-bar">
    <button
      type="button"
      class="flex min-h-11 w-full items-center gap-2 px-4 text-left text-[12.5px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-weact-600"
      :aria-expanded="open"
      aria-controls="conversation-context-bar-details"
      @click="open = !open"
    >
      <RStatusDot :tone="dot.tone" :label="statusLabel" class="shrink-0 font-semibold text-ink" />
      <span class="truncate font-semibold text-ink">{{ context.title }}</span>
      <span v-if="date" class="shrink-0 text-ink-3">· {{ date }}</span>
      <span class="ml-auto flex shrink-0 items-center gap-1 text-ink-3">
        Détails
        <ChevronDown class="size-3.5 transition-transform" :class="open ? 'rotate-180' : ''" />
      </span>
    </button>
    <div
      v-if="open"
      id="conversation-context-bar-details"
      class="max-h-[50vh] overflow-y-auto border-t border-line bg-white px-4 py-3"
      data-testid="conversation-context-bar-details"
    >
      <ConversationContextDetails :context="context" :role="role" />
    </div>
  </div>
</template>
