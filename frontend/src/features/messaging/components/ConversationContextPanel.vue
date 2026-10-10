<script setup lang="ts">
/**
 * Panneau de contexte (desktop, volet droit). Sans entité liée, il se limite à
 * l'autre participant.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import RStatusDot from '@/components/regie/RStatusDot.vue'
import { candidatureStatusDot } from '@/components/regie/statusTone'
import ConversationContextDetails from './ConversationContextDetails.vue'
import ParticipantAvatar from './ParticipantAvatar.vue'
import type { ConversationContext, OtherParticipant } from '../types'

const props = defineProps<{
  context: ConversationContext | null
  participant: OtherParticipant
  role: 'face' | 'producer'
}>()

const dot = computed(() =>
  props.context ? candidatureStatusDot(props.context.candidature_status) : null,
)

// Face -> détail de la mission ; Producteur -> candidatures de la mission
const missionLink = computed(() => {
  if (!props.context) return null
  return props.role === 'face'
    ? { name: 'face-mission-detail', params: { id: props.context.mission_id } }
    : { name: 'producer-mission-candidatures', params: { id: props.context.mission_id } }
})
const participantLabel = computed(() => (props.participant.type === 'producer' ? 'Producteur' : 'Face'))
</script>

<template>
  <aside
    class="flex w-72 shrink-0 flex-col overflow-y-auto border-l border-line bg-white"
    aria-label="Contexte de la conversation"
    data-testid="conversation-context-panel"
  >
    <template v-if="context">
      <div class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-line px-4">
        <h2 class="text-[13.5px] font-semibold text-ink">{{ context.type_label }}</h2>
        <RStatusDot
          v-if="dot"
          :tone="dot.tone"
          :label="context.step?.label ?? context.candidature_status_label"
          class="text-[12px] text-ink-2"
        />
      </div>
      <div class="space-y-4 p-4">
        <p class="text-[13.5px] font-semibold text-ink" data-testid="context-title">
          {{ context.title }}
        </p>
        <ConversationContextDetails :context="context" :role="role" />
        <RouterLink
          v-if="missionLink"
          :to="missionLink"
          class="inline-flex h-8 w-full items-center justify-center rounded-[10px] bg-white text-[13px] font-semibold text-ink ring-1 ring-line hover:bg-sidebar focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600"
          data-testid="context-open-link"
        >
          Ouvrir la mission
        </RouterLink>
      </div>
    </template>

    <template v-else>
      <div class="flex h-14 shrink-0 items-center border-b border-line px-4">
        <h2 class="text-[13.5px] font-semibold text-ink">Participant</h2>
      </div>
      <div class="flex items-center gap-3 p-4" data-testid="context-participant-only">
        <ParticipantAvatar :participant="participant" size-class="size-10" />
        <div class="min-w-0">
          <p class="truncate text-[13.5px] font-semibold text-ink">{{ participant.name }}</p>
          <p class="text-[12px] text-ink-3">{{ participantLabel }}</p>
        </div>
      </div>
    </template>
  </aside>
</template>
