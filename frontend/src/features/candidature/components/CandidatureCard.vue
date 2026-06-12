<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Calendar, MapPin, Wallet, User, Check, Loader2, MessageCircle, XCircle } from 'lucide-vue-next'
import type { FaceCandidature } from '../types'
import { CandidatureStatusColor } from '../types'
import { StatusPill, tunnelStatusToPillKind } from '@/components/ugc'

/**
 * Props
 */
const props = defineProps<{
  candidature: FaceCandidature
}>()

/**
 * Emits
 */
const emit = defineEmits<{
  confirm: [candidatureId: string]
  cancel: [candidatureId: string]
}>()

/**
 * Local state for confirm/cancel buttons
 */
const isConfirming = ref(false)
const isCancelling = ref(false)

/**
 * Computed: Show confirm button for accepted candidatures
 */
const canConfirm = computed(() => props.candidature.status === 'accepted')

/**
 * Computed: Show cancel button for pending candidatures
 */
const canCancel = computed(() => props.candidature.status === 'pending')

/**
 * Computed: Show chat button when candidature allows chat and has conversation
 */
const canChat = computed(() => {
  const chatStatuses = ['accepted', 'confirmed', 'in_progress', 'completed']
  return chatStatuses.includes(props.candidature.status) && props.candidature.conversation_id != null
})

/**
 * Handle confirm button click
 */
function handleConfirm(): void {
  if (isConfirming.value) return
  isConfirming.value = true
  emit('confirm', props.candidature.id)
}

/**
 * Handle cancel button click
 */
function handleCancel(): void {
  if (isCancelling.value) return
  isCancelling.value = true
  emit('cancel', props.candidature.id)
}

/**
 * Reset confirming state (called from parent after API response)
 */
function resetConfirming(): void {
  isConfirming.value = false
}

/**
 * Reset cancelling state (called from parent after API response)
 */
function resetCancelling(): void {
  isCancelling.value = false
}

/**
 * Expose methods for parent component
 */
defineExpose({ resetConfirming, resetCancelling })

/**
 * Computed: Format date for display
 */
const formattedDate = computed(() => {
  if (!props.candidature.mission.date_tournage) return ''
  try {
    const date = new Date(props.candidature.mission.date_tournage)
    if (isNaN(date.getTime())) return props.candidature.mission.date_tournage
    return new Intl.DateTimeFormat('fr-FR', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(date)
  } catch {
    return props.candidature.mission.date_tournage
  }
})

/**
 * Computed: Format budget for display
 */
const formattedBudget = computed(() => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  }).format(props.candidature.mission.budget)
})

/**
 * Computed: Format created_at for display
 */
const formattedCreatedAt = computed(() => {
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(props.candidature.created_at))
})

/**
 * Computed: Status badge class
 */
const statusBadgeClass = computed(() => {
  const color = CandidatureStatusColor[props.candidature.status]
  const colorClasses: Record<string, string> = {
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    gray: 'bg-gray-100 text-gray-600 dark:bg-gray-900/30 dark:text-gray-400',
  }
  return colorClasses[color] || colorClasses.yellow
})

/**
 * Computed: Producer initials for fallback avatar
 */
const producerInitials = computed(() => {
  return props.candidature.producer.display_name.charAt(0).toUpperCase()
})
</script>

<template>
  <RouterLink
    :to="{ name: 'face-mission-detail', params: { id: candidature.mission.id } }"
    class="block rounded-xl border border-border bg-card p-4 transition-all hover:border-primary/50 hover:shadow-md sm:p-5"
  >
    <!-- Status Badge -->
    <div class="mb-3 flex items-center justify-between">
      <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
        :class="statusBadgeClass"
      >
        {{ candidature.status_label }}
      </span>
      <span class="text-xs text-muted-foreground">
        Postulé le {{ formattedCreatedAt }}
      </span>
    </div>

    <!-- Mission Title -->
    <h3 class="mb-3 text-base font-semibold text-foreground line-clamp-2 sm:text-lg">
      {{ candidature.mission.titre }}
    </h3>

    <!-- Mission Details -->
    <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
      <div class="flex items-center gap-1.5">
        <Calendar class="h-4 w-4" />
        <span>{{ formattedDate }}</span>
      </div>
      <div class="flex items-center gap-1.5">
        <MapPin class="h-4 w-4" />
        <span>{{ candidature.mission.lieu }}</span>
      </div>
      <div class="flex items-center gap-1.5">
        <Wallet class="h-4 w-4" />
        <span>{{ formattedBudget }}</span>
      </div>
    </div>

    <!-- Producer Info -->
    <div class="flex items-center gap-3 border-t border-border pt-3">
      <div class="relative h-8 w-8 overflow-hidden rounded-full border border-border bg-muted">
        <img
          v-if="candidature.producer.profile_photo_url"
          :src="candidature.producer.profile_photo_url"
          :alt="candidature.producer.display_name"
          class="h-full w-full object-cover"
        />
        <div
          v-else
          class="flex h-full w-full items-center justify-center bg-primary/10 text-xs font-bold uppercase text-primary"
        >
          {{ producerInitials }}
        </div>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-foreground truncate">
          {{ candidature.producer.display_name }}
        </p>
        <p class="text-xs text-muted-foreground">
          {{ candidature.producer.type === 'agency' ? 'Agence' : 'Particulier' }}
        </p>
      </div>
      <User class="h-4 w-4 text-muted-foreground" />
    </div>

    <!-- Tracking expédition UGC (3.4) — bloc compact informatif, l'action vit sur le détail (D-3.4.h) -->
    <div
      v-if="candidature.shipment"
      class="mt-3 flex flex-wrap items-center gap-2 border-t border-border pt-3 text-xs text-muted-foreground"
      data-testid="candidature-shipment-info"
    >
      <StatusPill :kind="tunnelStatusToPillKind(candidature.shipment.tunnel_status)">
        {{ candidature.shipment.tunnel_status_label }}
      </StatusPill>
      <span>{{ candidature.shipment.transporteur }} · {{ candidature.shipment.numero_suivi }}</span>
    </div>

    <!-- Actions Section -->
    <div
      v-if="canCancel || canConfirm || canChat"
      class="mt-4 flex flex-col gap-2 border-t border-border pt-4"
    >
      <!-- Confirm Button -->
      <button
        v-if="canConfirm"
        type="button"
        class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="isConfirming"
        @click.stop.prevent="handleConfirm"
      >
        <Loader2 v-if="isConfirming" class="h-4 w-4 animate-spin" />
        <Check v-else class="h-4 w-4" />
        {{ isConfirming ? 'Confirmation...' : 'Confirmer ma participation' }}
      </button>

      <!-- Cancel Button -->
      <button
        v-if="canCancel"
        type="button"
        class="w-full inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="isCancelling"
        @click.stop.prevent="handleCancel"
      >
        <Loader2 v-if="isCancelling" class="h-4 w-4 animate-spin" />
        <XCircle v-else class="h-4 w-4" />
        {{ isCancelling ? 'Annulation...' : 'Annuler ma candidature' }}
      </button>

      <!-- Chat Button -->
      <RouterLink
        v-if="canChat"
        :to="{ name: 'face-conversation', params: { conversationId: candidature.conversation_id } }"
        class="w-full inline-flex items-center justify-center gap-2 rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-medium text-foreground transition-colors hover:bg-muted"
        @click.stop
      >
        <MessageCircle class="h-4 w-4 text-muted-foreground" />
        Discuter avec le producteur
      </RouterLink>
    </div>
  </RouterLink>
</template>
