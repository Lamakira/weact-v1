<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  Loader2,
  AlertCircle,
  FileText,
  ChevronLeft,
  ChevronRight,
  Inbox,
} from 'lucide-vue-next'
import { useProducerCandidatures, useAcceptCandidature } from '../composables'
import ProducerCandidatureCard from './ProducerCandidatureCard.vue'
import StatusFilter from './StatusFilter.vue'
import { CandidatureStatusLabel } from '../types'
import type { CandidatureStatusType } from '../types'

/**
 * Props
 */
const props = defineProps<{
  missionId: number
}>()

/**
 * Composable for candidatures list
 */
const {
  candidatures,
  isLoading,
  error,
  currentPage,
  lastPage,
  total,
  hasNextPage,
  hasPrevPage,
  isEmpty,
  statusFilter,
  fetchCandidatures,
  nextPage,
  prevPage,
  goToPage,
  setStatusFilter,
  refresh,
} = useProducerCandidatures(props.missionId)

/**
 * Composable for accepting candidatures
 */
const {
  error: acceptError,
  successMessage,
  acceptCandidature,
  reset: resetAccept,
} = useAcceptCandidature()

/**
 * Toast state for notifications
 */
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref<'success' | 'error'>('success')

/**
 * Card refs for resetting loading state
 */
const cardRefs = ref<Record<number, InstanceType<typeof ProducerCandidatureCard>>>({})

/**
 * Show toast notification
 */
function displayToast(message: string, type: 'success' | 'error'): void {
  toastMessage.value = message
  toastType.value = type
  showToast.value = true
  setTimeout(() => {
    showToast.value = false
  }, 4000)
}

/**
 * Handle accept candidature
 */
async function handleAccept(candidatureId: number): Promise<void> {
  const result = await acceptCandidature(candidatureId)

  // Reset the card's loading state
  cardRefs.value[candidatureId]?.resetAccepting()

  if (result) {
    displayToast(successMessage.value || 'Candidature acceptée', 'success')
    // Refresh the list to show updated status
    await refresh()
  } else {
    displayToast(acceptError.value || "Erreur lors de l'acceptation", 'error')
  }

  resetAccept()
}

/**
 * Handle filter change
 */
async function handleFilterChange(status: CandidatureStatusType | ''): Promise<void> {
  await setStatusFilter(status)
}

/**
 * Generate page numbers for pagination
 */
function getPageNumbers(): number[] {
  const pages: number[] = []
  const maxVisiblePages = 5
  let startPage = Math.max(1, currentPage.value - Math.floor(maxVisiblePages / 2))
  const endPage = Math.min(lastPage.value, startPage + maxVisiblePages - 1)

  if (endPage - startPage + 1 < maxVisiblePages) {
    startPage = Math.max(1, endPage - maxVisiblePages + 1)
  }

  for (let i = startPage; i <= endPage; i++) {
    if (i > 0) pages.push(i)
  }
  return pages
}

/**
 * Lifecycle
 */
onMounted(() => {
  fetchCandidatures(1)
})
</script>

<template>
  <section class="space-y-6">
    <!-- Header & Filter -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div class="space-y-1">
        <h2 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
          Candidatures
        </h2>
        <p v-if="!isLoading && !error" class="text-sm text-muted-foreground">
          {{ total }} candidature{{ total > 1 ? 's' : '' }} reçue{{ total > 1 ? 's' : '' }}
        </p>
      </div>
    </div>

    <!-- Status Filter -->
    <StatusFilter :model-value="statusFilter" @update:model-value="handleFilterChange" />

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex flex-col items-center justify-center py-24 text-center"
    >
      <Loader2 class="h-12 w-12 animate-spin text-primary" />
      <p class="mt-4 text-muted-foreground">Chargement des candidatures...</p>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 px-6 py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
        <AlertCircle class="h-10 w-10" />
      </div>
      <h3 class="text-xl font-bold text-foreground">Oups ! Une erreur est survenue</h3>
      <p class="mt-2 max-w-sm text-muted-foreground">{{ error }}</p>
      <button
        type="button"
        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90"
        @click="refresh"
      >
        Réessayer
      </button>
    </div>

    <!-- Empty State (No Candidatures at all) -->
    <div
      v-else-if="isEmpty && !statusFilter"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-muted p-4">
        <Inbox class="h-10 w-10 text-muted-foreground" />
      </div>
      <h3 class="text-xl font-bold text-foreground">Aucune candidature reçue</h3>
      <p class="mt-2 max-w-xs text-muted-foreground">
        Les Faces n'ont pas encore postulé à cette mission.
      </p>
    </div>

    <!-- Empty State (Filter with no results) -->
    <div
      v-else-if="isEmpty && statusFilter"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
    >
      <div class="mb-4 rounded-full bg-muted p-4">
        <FileText class="h-10 w-10 text-muted-foreground" />
      </div>
      <h3 class="text-xl font-bold text-foreground">Aucun résultat</h3>
      <p class="mt-2 max-w-xs text-muted-foreground">
        Aucune candidature avec le statut
        <span class="font-semibold">"{{ CandidatureStatusLabel[statusFilter] }}"</span>
        trouvée.
      </p>
      <button
        type="button"
        class="mt-6 text-sm font-medium text-primary hover:underline"
        @click="handleFilterChange('')"
      >
        Réinitialiser le filtre
      </button>
    </div>

    <!-- Results List -->
    <template v-else>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <ProducerCandidatureCard
          v-for="candidature in candidatures"
          :key="candidature.id"
          :ref="(el) => { if (el) cardRefs[candidature.id] = el as InstanceType<typeof ProducerCandidatureCard> }"
          :candidature="candidature"
          @accept="handleAccept"
        />
      </div>

      <!-- Pagination Controls -->
      <div
        v-if="lastPage > 1"
        class="mt-8 flex items-center justify-center gap-2"
      >
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!hasPrevPage"
          @click="prevPage"
        >
          <ChevronLeft class="h-5 w-5" />
        </button>

        <div class="flex items-center gap-1">
          <button
            v-for="page in getPageNumbers()"
            :key="page"
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium transition-colors"
            :class="[
              page === currentPage
                ? 'bg-primary text-white'
                : 'border border-border bg-card hover:bg-muted',
            ]"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!hasNextPage"
          @click="nextPage"
        >
          <ChevronRight class="h-5 w-5" />
        </button>
      </div>
    </template>

    <!-- Toast Notification -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
      >
        <div
          v-if="showToast"
          class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-lg px-4 py-3 shadow-lg"
          :class="[
            toastType === 'success'
              ? 'bg-green-600 text-white'
              : 'bg-red-600 text-white',
          ]"
        >
          <span class="text-sm font-medium">{{ toastMessage }}</span>
          <button
            type="button"
            aria-label="Fermer"
            class="ml-2 text-white/80 hover:text-white"
            @click="showToast = false"
          >
            &times;
          </button>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>
