<script setup lang="ts">
import { onMounted } from 'vue'
import {
  Loader2,
  AlertCircle,
  FileText,
  ChevronLeft,
  ChevronRight,
  Inbox,
} from 'lucide-vue-next'
import { useProducerCandidatures } from '../composables'
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
 * Composable
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
          :candidature="candidature"
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
  </section>
</template>
