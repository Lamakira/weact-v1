<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Inbox, AlertCircle, RefreshCw, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { useFaceMissions } from '@/features/mission/composables'
import { AvailableMissionCard } from '@/features/mission/components'

/**
 * LOGIC & STATE MANAGEMENT
 */
const router = useRouter()
const {
  missions,
  isLoading,
  error,
  isEmpty,
  currentPage,
  lastPage,
  totalCount,
  hasNextPage,
  hasPrevPage,
  fetchMissions,
  nextPage,
  prevPage,
  refreshMissions,
} = useFaceMissions()

/**
 * ACTIONS
 */
onMounted(() => {
  fetchMissions()
})

function handleMissionClick(id: number): void {
  // TODO: Navigate to mission detail page (Story 5-10)
  router.push({ name: 'face-mission-detail', params: { id } })
}
</script>

<template>
  <div class="min-h-screen bg-background pb-20">
    <!-- Header Section -->
    <header class="sticky top-0 z-20 border-b border-border bg-background/80 backdrop-blur-md">
      <div class="container mx-auto flex h-20 flex-col justify-center px-4 sm:px-6">
        <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
          Missions disponibles
        </h1>
        <p class="text-sm text-muted-foreground">
          Découvrez les opportunités qui correspondent à votre profil
        </p>
      </div>
    </header>

    <main class="container mx-auto mt-8 px-4 sm:px-6">
      <!-- Loading State -->
      <div v-if="isLoading && !missions.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="i in 6"
          :key="i"
          class="relative overflow-hidden rounded-lg border border-border bg-card p-5"
        >
          <div class="mb-4 flex flex-col gap-2">
            <div class="h-5 w-24 animate-pulse rounded-full bg-muted" />
            <div class="h-6 w-3/4 animate-pulse rounded bg-muted" />
          </div>
          <div class="mb-6 space-y-2">
            <div class="h-4 w-full animate-pulse rounded bg-muted" />
            <div class="h-4 w-2/3 animate-pulse rounded bg-muted" />
          </div>
          <div class="mb-6 grid grid-cols-2 gap-3">
            <div class="h-4 w-full animate-pulse rounded bg-muted" />
            <div class="h-4 w-full animate-pulse rounded bg-muted" />
            <div class="h-4 w-full animate-pulse rounded bg-muted" />
            <div class="h-4 w-full animate-pulse rounded bg-muted" />
          </div>
          <div class="h-px w-full bg-border" />
          <div class="mt-4 flex items-center gap-3">
            <div class="h-8 w-8 animate-pulse rounded-full bg-muted" />
            <div class="flex-1 space-y-1">
              <div class="h-3 w-16 animate-pulse rounded bg-muted" />
              <div class="h-4 w-24 animate-pulse rounded bg-muted" />
            </div>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 py-16 text-center"
      >
        <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
          <AlertCircle class="h-10 w-10" />
        </div>
        <h3 class="text-xl font-bold text-foreground">Oups ! Une erreur est survenue</h3>
        <p class="mt-2 max-w-xs text-muted-foreground">
          Impossible de charger les missions pour le moment.
        </p>
        <p class="mt-2 text-sm text-destructive">{{ error }}</p>
        <button
          type="button"
          class="mt-6 flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-sm font-medium transition-colors hover:bg-muted"
          @click="refreshMissions"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isLoading }" />
          Réessayer
        </button>
      </div>

      <!-- Empty State -->
      <div v-else-if="isEmpty" class="flex flex-col items-center justify-center py-24 text-center">
        <div class="relative mb-6">
          <div class="absolute -inset-4 animate-pulse rounded-full bg-primary/5 blur-2xl" />
          <div
            class="relative flex h-24 w-24 items-center justify-center rounded-full bg-muted text-muted-foreground"
          >
            <Inbox class="h-12 w-12 opacity-50" />
          </div>
        </div>
        <h3 class="text-2xl font-bold text-foreground">Aucune mission disponible</h3>
        <p class="mt-3 max-w-sm text-muted-foreground">
          Revenez plus tard pour découvrir de nouvelles opportunités.
        </p>
      </div>

      <!-- Content Grid -->
      <div v-else>
        <div class="mb-6 flex items-center justify-between">
          <div class="text-sm font-medium text-muted-foreground">
            {{ totalCount }} mission{{ totalCount > 1 ? 's' : '' }} disponible{{ totalCount > 1 ? 's' : '' }}
          </div>
          <button
            type="button"
            class="group p-2 text-muted-foreground transition-colors hover:text-primary"
            title="Rafraîchir la liste"
            @click="refreshMissions"
          >
            <RefreshCw class="h-5 w-5" :class="{ 'animate-spin': isLoading }" />
          </button>
        </div>

        <TransitionGroup
          tag="div"
          enter-active-class="transition duration-500 ease-out"
          enter-from-class="opacity-0 translate-y-4"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-300 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
          class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
        >
          <AvailableMissionCard
            v-for="mission in missions"
            :key="mission.id"
            :mission="mission"
            @click="handleMissionClick"
          />
        </TransitionGroup>

        <!-- Pagination -->
        <div
          v-if="lastPage > 1"
          class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row sm:justify-between"
        >
          <p class="text-sm text-muted-foreground">
            Page {{ currentPage }} sur {{ lastPage }}
          </p>

          <div class="flex items-center gap-2">
            <button
              type="button"
              :disabled="!hasPrevPage || isLoading"
              class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
              @click="prevPage"
            >
              <ChevronLeft class="h-4 w-4" />
              Précédent
            </button>
            <button
              type="button"
              :disabled="!hasNextPage || isLoading"
              class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
              @click="nextPage"
            >
              Suivant
              <ChevronRight class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
