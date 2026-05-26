<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { MapPin, Wallet, Calendar, MessageSquare, MessageCircle, ArrowUpRight, X, Loader2, CheckSquare, Square } from 'lucide-vue-next'
import type { ProducerCandidature } from '../types'
import { CandidatureStatusColor } from '../types'
import WBadge from '@/components/ui/WBadge.vue'

/**
 * Props
 */
const props = defineProps<{
  candidature: ProducerCandidature
  selectionMode?: boolean
  isSelected?: boolean
}>()

/**
 * Emits
 */
const emit = defineEmits<{
  reject: [candidatureId: string]
  'toggle-selection': [candidatureId: string]
}>()

/**
 * Local state for reject button / confirmation dialog
 */
const isRejecting = ref(false)
const showRejectConfirmation = ref(false)

/**
 * Computed: Can show reject button (only for pending candidatures outside selection mode).
 * Acceptation is now exclusively handled through the paid selection workflow —
 * there is no manual accept button on this card.
 */
const canTakeAction = computed(() => props.candidature.status === 'pending')

/**
 * Computed: Can show chat button (accepted, confirmed, in_progress, completed + has conversation)
 */
const canChat = computed(() => {
  const chatStatuses = ['accepted', 'confirmed', 'in_progress', 'completed']
  return chatStatuses.includes(props.candidature.status) && props.candidature.conversation_id != null
})

/**
 * Show reject confirmation dialog
 */
function showRejectDialog(): void {
  showRejectConfirmation.value = true
  // Focus the cancel button after dialog opens
  setTimeout(() => {
    const cancelButton = document.getElementById('reject-dialog-cancel')
    cancelButton?.focus()
  }, 50)
}

/**
 * Handle Escape key to close dialog
 */
function handleDialogKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    cancelReject()
  }
}

/**
 * Cancel reject action
 */
function cancelReject(): void {
  showRejectConfirmation.value = false
}

/**
 * Confirm and execute reject action
 */
function confirmReject(): void {
  if (isRejecting.value) return
  isRejecting.value = true
  showRejectConfirmation.value = false
  emit('reject', props.candidature.id)
}

/**
 * Reset rejecting state (called from parent after API response)
 */
function resetRejecting(): void {
  isRejecting.value = false
}

/**
 * Expose methods for parent component
 */
defineExpose({ resetRejecting })

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
  }
  return colorClasses[color] || colorClasses.yellow
})

/**
 * Computed: Format created_at for display
 */
const formattedCreatedAt = computed(() => {
  if (!props.candidature.created_at) return ''
  try {
    const date = new Date(props.candidature.created_at)
    if (isNaN(date.getTime())) return props.candidature.created_at
    return new Intl.DateTimeFormat('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(date)
  } catch {
    return props.candidature.created_at
  }
})

/**
 * Computed: Face initials for fallback avatar
 */
const initials = computed(() => {
  const name = props.candidature.face.display_name || ''
  return name
    .split(' ')
    .filter((n) => n.length > 0)
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2) || '?'
})

/**
 * Format currency as XOF
 */
function formatCurrency(amount: number | null): string | null {
  if (amount === null) return null
  return new Intl.NumberFormat('fr-FR').format(amount) + ' XOF'
}

/**
 * Computed: Motivation message preview
 */
const messagePreview = computed(() => {
  if (!props.candidature.message_motivation) return null
  // Already truncated by backend to 150 chars, but ensure safety
  return props.candidature.message_motivation
})

/**
 * Format category label
 */
const categoryLabel = computed(() => {
  if (!props.candidature.face.category) return null
  const labels: Record<string, string> = {
    acteur: 'Acteur',
    influenceur: 'Influenceur',
    createur: 'Créateur de contenu',
    mannequin: 'Mannequin',
    figurant: 'Figurant',
  }
  return labels[props.candidature.face.category] || props.candidature.face.category
})
</script>

<template>
  <div
    class="group relative block rounded-xl border bg-card p-4 transition-all hover:shadow-md sm:p-5"
    :class="[
      selectionMode && candidature.status === 'pending'
        ? isSelected
          ? 'border-primary ring-2 ring-primary/30 cursor-pointer'
          : 'border-border hover:border-primary/50 cursor-pointer'
        : 'border-border hover:border-primary/50',
    ]"
    @click="selectionMode && candidature.status === 'pending' ? emit('toggle-selection', candidature.id) : undefined"
  >
    <!-- Header: Status & Date -->
    <div class="mb-4 flex items-center justify-between">
      <!-- Selection checkbox (only shown in selection mode for pending candidatures) -->
      <div
        v-if="selectionMode && candidature.status === 'pending'"
        class="flex items-center gap-2"
        @click.stop="emit('toggle-selection', candidature.id)"
      >
        <CheckSquare v-if="isSelected" class="h-5 w-5 text-primary" />
        <Square v-else class="h-5 w-5 text-muted-foreground" />
      </div>
      <span
        v-else
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
        :class="statusBadgeClass"
      >
        {{ candidature.status_label }}
      </span>
      <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
        <Calendar class="h-3.5 w-3.5" />
        Postulé le {{ formattedCreatedAt }}
      </div>
    </div>

    <!-- Face Profile Info -->
    <div class="flex items-start gap-4">
      <RouterLink
        :to="{ name: 'producer-candidate-profile', params: { id: candidature.face.id } }"
        class="relative h-14 w-14 shrink-0 overflow-hidden rounded-full border border-border bg-muted lg:h-16 lg:w-16"
      >
        <img
          v-if="candidature.face.profile_photo_url"
          :src="candidature.face.profile_photo_url"
          :alt="candidature.face.display_name"
          class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
        />
        <div
          v-else
          class="flex h-full w-full items-center justify-center bg-primary/10 text-lg font-bold text-primary"
        >
          {{ initials }}
        </div>
      </RouterLink>

      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
          <RouterLink
            :to="{ name: 'producer-candidate-profile', params: { id: candidature.face.id } }"
            class="inline-flex items-center gap-1 text-base font-semibold text-foreground transition-colors hover:text-primary sm:text-lg"
          >
            <span class="truncate">{{ candidature.face.display_name }}</span>
            <ArrowUpRight
              class="h-4 w-4 shrink-0 opacity-0 transition-all group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:opacity-100"
            />
          </RouterLink>
          <WBadge v-if="candidature.face.has_elite_badge" tier="elite" :size="14" />
        </div>

        <div
          class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
        >
          <span
            v-if="categoryLabel"
            class="text-xs font-medium uppercase tracking-tight text-primary/80"
          >
            {{ categoryLabel }}
          </span>
          <div v-if="candidature.face.city" class="flex items-center gap-1">
            <MapPin class="h-3.5 w-3.5" />
            {{ candidature.face.city }}
          </div>
        </div>
      </div>
    </div>

    <!-- Pricing Row -->
    <div class="mt-4 grid grid-cols-2 gap-3 border-y border-border/50 py-3">
      <div class="flex flex-col gap-0.5">
        <span class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
          Tarif Demi-journée
        </span>
        <div class="flex items-center gap-1.5 text-sm font-medium text-foreground">
          <Wallet class="h-3.5 w-3.5 text-primary" />
          <span v-if="candidature.face.tarif_journalier">
            {{ formatCurrency(Math.round(candidature.face.tarif_journalier / 2)) }}/½j
          </span>
          <span v-else class="text-xs font-normal italic text-muted-foreground">
            Non spécifié
          </span>
        </div>
      </div>
      <div class="flex flex-col gap-0.5 border-l border-border/50 pl-3">
        <span class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
          Tarif Journalier
        </span>
        <div class="flex items-center gap-1.5 text-sm font-medium text-foreground">
          <Wallet class="h-3.5 w-3.5 text-primary" />
          <span v-if="candidature.face.tarif_journalier">
            {{ formatCurrency(candidature.face.tarif_journalier) }}/j
          </span>
          <span v-else class="text-xs font-normal italic text-muted-foreground">
            Non spécifié
          </span>
        </div>
      </div>
    </div>

    <!-- Motivation Message Excerpt -->
    <div v-if="messagePreview" class="mt-3">
      <div
        class="relative rounded-lg bg-muted/50 p-3 transition-colors group-hover:bg-muted/70"
      >
        <MessageSquare class="absolute -left-1 -top-2 h-4 w-4 fill-primary/10 text-primary/40" />
        <p class="text-xs italic leading-relaxed text-muted-foreground">
          "{{ messagePreview }}"
        </p>
      </div>
    </div>

    <!-- Actions -->
    <div class="mt-4 flex items-center justify-between gap-3">
      <!-- Reject Button (only for pending and NOT in selection mode) -->
      <div v-if="canTakeAction && !selectionMode" class="flex gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="isRejecting"
          @click="showRejectDialog"
        >
          <Loader2 v-if="isRejecting" class="h-4 w-4 animate-spin" />
          <X v-else class="h-4 w-4" />
          {{ isRejecting ? 'Refus...' : 'Refuser' }}
        </button>
      </div>

      <!-- Chat Button (for accepted/confirmed/in_progress/completed candidatures) -->
      <div v-else-if="canChat">
        <RouterLink
          :to="{ name: 'producer-conversation', params: { conversationId: candidature.conversation_id } }"
          class="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
        >
          <MessageCircle class="h-4 w-4 text-muted-foreground" />
          Discuter
        </RouterLink>
      </div>
      <div v-else></div>

      <!-- Profile Link -->
      <RouterLink
        :to="{ name: 'producer-candidate-profile', params: { id: candidature.face.id } }"
        class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
      >
        Voir le profil complet
        <ArrowUpRight class="h-3 w-3" />
      </RouterLink>
    </div>

    <!-- Reject Confirmation Dialog -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showRejectConfirmation"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          @click.self="cancelReject"
          @keydown="handleDialogKeydown"
        >
          <div
            class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reject-dialog-title"
          >
            <h3 id="reject-dialog-title" class="text-lg font-semibold text-foreground">
              Refuser cette candidature ?
            </h3>
            <p class="mt-2 text-sm text-muted-foreground">
              Êtes-vous sûr de vouloir refuser la candidature de
              <span class="font-medium text-foreground">{{ candidature.face.display_name }}</span> ?
            </p>
            <p class="mt-1 text-xs text-red-600">
              Cette action est irréversible.
            </p>
            <div class="mt-6 flex justify-end gap-3">
              <button
                id="reject-dialog-cancel"
                type="button"
                class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                @click="cancelReject"
              >
                Annuler
              </button>
              <button
                type="button"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700"
                @click="confirmReject"
              >
                Confirmer le refus
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
