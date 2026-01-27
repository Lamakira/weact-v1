<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Calendar, MapPin, Wallet, User, Check, Loader2 } from 'lucide-vue-next'
import type { FaceCandidature } from '../types'
import { CandidatureStatusColor } from '../types'

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
  confirm: [candidatureId: number]
}>()

/**
 * Local state for confirm button
 */
const isConfirming = ref(false)

/**
 * Computed: Show confirm button for accepted candidatures
 */
const canConfirm = computed(() => props.candidature.status === 'accepted')

/**
 * Handle confirm button click
 */
function handleConfirm(): void {
  if (isConfirming.value) return
  isConfirming.value = true
  emit('confirm', props.candidature.id)
}

/**
 * Reset confirming state (called from parent after API response)
 */
function resetConfirming(): void {
  isConfirming.value = false
}

/**
 * Expose methods for parent component
 */
defineExpose({ resetConfirming })

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

    <!-- Confirm Button (only for accepted candidatures) -->
    <div v-if="canConfirm" class="mt-4 pt-4 border-t border-border">
      <button
        type="button"
        class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="isConfirming"
        @click.stop.prevent="handleConfirm"
      >
        <Loader2 v-if="isConfirming" class="h-4 w-4 animate-spin" />
        <Check v-else class="h-4 w-4" />
        {{ isConfirming ? 'Confirmation...' : 'Confirmer ma participation' }}
      </button>
    </div>
  </RouterLink>
</template>
