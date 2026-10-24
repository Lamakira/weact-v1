<script setup lang="ts">
/**
 * FaceResponseNotice — ligne d'information côté Producteur sous l'en-tête d'une discussion :
 * comment la Face est prévenue d'un message non lu, son délai de réponse habituel, et
 * (sans réponse au-delà du seuil) l'information que l'équipe WeAct prend contact.
 * N'affiche rien pendant le chargement ni en cas d'erreur.
 */
import { computed, ref, watch } from 'vue'
import { Bell, Clock } from 'lucide-vue-next'
import { messagingApi } from '../services/messagingApi'
import type { FaceResponseNotice } from '../types'
import { formatTypicalResponse } from '../utils/formatTypicalResponse'

const props = defineProps<{
  kind: 'booking' | 'conversation'
  threadId: string
  /** Change quand le fil évolue (ex. nombre de messages) : relit l'état. */
  refreshKey?: number
}>()

const notice = ref<FaceResponseNotice | null>(null)
// Numéro de la requête en cours : une réponse périmée (fil changé entre-temps) est ignorée.
let requestSeq = 0

async function load(): Promise<void> {
  const seq = ++requestSeq
  const id = props.threadId
  try {
    const data =
      props.kind === 'booking'
        ? await messagingApi.getBookingFaceResponse(id)
        : await messagingApi.getConversationFaceResponse(id)
    if (seq === requestSeq) notice.value = data
  } catch {
    if (seq === requestSeq) notice.value = null
  }
}

// Changement de fil : on masque l'ancien état avant de recharger.
watch(
  () => [props.kind, props.threadId] as const,
  () => {
    notice.value = null
  },
)
watch(() => [props.kind, props.threadId, props.refreshKey] as const, load, { immediate: true })

const state = computed<'normal' | 'urgent' | 'overdue'>(() => {
  if (notice.value?.overdue) return 'overdue'
  return notice.value?.urgent ? 'urgent' : 'normal'
})

const delayLabel = computed(() =>
  notice.value?.unread_email_after_minutes === 15 ? '15 minutes' : '1 heure',
)

const typicalLabel = computed(() =>
  notice.value?.typical_response_minutes != null
    ? formatTypicalResponse(notice.value.typical_response_minutes)
    : null,
)
</script>

<template>
  <div
    v-if="notice"
    role="status"
    class="flex shrink-0 items-start gap-2.5 border-b border-line px-4 py-2.5 text-[13px] leading-snug text-ink lg:px-5"
    :class="state === 'overdue' ? 'bg-amber-50' : 'bg-weact-50'"
    data-testid="face-response-notice"
    :data-state="state"
  >
    <Clock
      v-if="state === 'overdue'"
      :size="16"
      class="mt-0.5 shrink-0 text-amber-600"
      aria-hidden="true"
    />
    <Bell v-else :size="16" class="mt-0.5 shrink-0 text-weact-600" aria-hidden="true" />
    <p v-if="state === 'overdue'" class="min-w-0">
      {{ notice.face_first_name }} n'a pas encore répondu. L'équipe WeAct va prendre contact
      directement.
    </p>
    <p v-else class="min-w-0">
      <template v-if="state === 'urgent'">Tournage proche : </template
      >{{ notice.face_first_name }} reçoit un e-mail si votre message reste non lu pendant
      {{ delayLabel }}.
      <span v-if="typicalLabel" class="text-ink-3" data-testid="face-response-typical"
        >Délai de réponse habituel : {{ typicalLabel }}.</span
      >
    </p>
  </div>
</template>
