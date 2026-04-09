<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Inbox,
  AlertCircle,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
  SlidersHorizontal,
  X,
} from 'lucide-vue-next'
import { useFaceMissions, useMissionFilters } from '@/features/mission/composables'
import { AvailableMissionCard, MissionFiltersPanel } from '@/features/mission/components'

/**
 * LOGIC & STATE MANAGEMENT
 */
const router = useRouter()

// Mission list state
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
  hasFiltersApplied,
  fetchMissions,
  nextPage,
  prevPage,
  refreshMissions,
} = useFaceMissions()

// Filter state
const {
  lieu,
  budgetMin,
  budgetMax,
  dateTournage,
  typeMission,
  filters,
  activeFilterCount,
  hasActiveFilters,
  resetFilters,
  syncToUrl,
  initFromUrl,
} = useMissionFilters()

// Mobile filter panel visibility
const showFiltersPanel = ref(false)

/**
 * ACTIONS
 */
onMounted(() => {
  // Initialize filters from URL then fetch
  initFromUrl()
  fetchMissions(1, filters.value)
})

function handleApplyFilters(): void {
  syncToUrl()
  fetchMissions(1, filters.value)
  showFiltersPanel.value = false
}

function handleResetFilters(): void {
  resetFilters()
  fetchMissions(1, filters.value)
  showFiltersPanel.value = false
}

function handleMissionClick(id: string): void {
  router.push({ name: 'face-mission-detail', params: { id } })
}

function handleNextPage(): void {
  nextPage(filters.value)
}

function handlePrevPage(): void {
  prevPage(filters.value)
}

function handleRefresh(): void {
  refreshMissions(filters.value)
}

function toggleFiltersPanel(): void {
  showFiltersPanel.value = !showFiltersPanel.value
}
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8 flex items-start justify-between gap-4">
      <div>
        <h1 class="text-xl min-[376px]:text-2xl font-bold text-slate-800">
          Missions disponibles
        </h1>
        <p class="mt-1 text-xs min-[376px]:text-sm text-slate-500">
          Découvrez les opportunités qui correspondent à votre profil
        </p>
      </div>

      <!-- Mobile Filter Toggle -->
      <button
        type="button"
        class="relative lg:hidden inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted shrink-0"
        @click="toggleFiltersPanel"
        data-testid="toggle-filters-mobile"
      >
        <SlidersHorizontal class="h-4 w-4" />
        <span class="hidden min-[376px]:inline">Filtres</span>
        <span
          v-if="activeFilterCount > 0"
          class="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-xs font-bold text-white"
        >
          {{ activeFilterCount }}
        </span>
      </button>
    </div>

    <!-- Mobile Filters Panel (Overlay) -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showFiltersPanel"
          class="fixed inset-0 z-50 lg:hidden"
          @click.self="showFiltersPanel = false"
        >
          <!-- Backdrop -->
          <div class="absolute inset-0 bg-black/50" />

          <!-- Panel -->
          <div class="absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-2xl bg-background p-4 shadow-xl">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold">Filtres</h3>
              <button
                type="button"
                class="p-2 rounded-full hover:bg-muted transition-colors"
                @click="showFiltersPanel = false"
              >
                <X class="h-5 w-5" />
              </button>
            </div>
            <MissionFiltersPanel
              v-model:lieu="lieu"
              v-model:budget-min="budgetMin"
              v-model:budget-max="budgetMax"
              v-model:date-tournage="dateTournage"
              v-model:type-mission="typeMission"
              :is-loading="isLoading"
              @apply="handleApplyFilters"
              @reset="handleResetFilters"
            />
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Content -->
    <div class="flex flex-col lg:flex-row gap-6">
      <!-- Desktop Filters Sidebar -->
      <aside class="hidden lg:block w-80 flex-shrink-0">
        <div class="sticky top-28">
          <MissionFiltersPanel
            v-model:lieu="lieu"
            v-model:budget-min="budgetMin"
            v-model:budget-max="budgetMax"
            v-model:date-tournage="dateTournage"
            v-model:type-mission="typeMission"
            :is-loading="isLoading"
            @apply="handleApplyFilters"
            @reset="handleResetFilters"
          />
        </div>
      </aside>

      <!-- Content Area -->
      <div class="flex-1 min-w-0">
        <!-- Loading State -->
        <div v-if="isLoading && !missions.length" class="grid gap-4 min-[376px]:gap-6 sm:grid-cols-2 xl:grid-cols-3">
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
          <h3 class="text-lg min-[376px]:text-xl font-bold text-foreground">Oups ! Une erreur est survenue</h3>
          <p class="mt-2 max-w-xs text-xs min-[376px]:text-sm text-muted-foreground">
            Impossible de charger les missions pour le moment.
          </p>
          <p class="mt-2 text-xs min-[376px]:text-sm text-destructive">{{ error }}</p>
          <button
            type="button"
            class="mt-6 flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted"
            @click="handleRefresh"
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
          <h3 class="text-xl min-[376px]:text-2xl font-bold text-foreground">
            {{ hasFiltersApplied ? 'Aucun résultat' : 'Aucune mission disponible' }}
          </h3>
          <p class="mt-3 max-w-sm text-xs min-[376px]:text-sm text-muted-foreground">
            {{
              hasFiltersApplied
                ? 'Aucune mission ne correspond à vos critères. Essayez de modifier vos filtres.'
                : 'Revenez plus tard pour découvrir de nouvelles opportunités.'
            }}
          </p>
          <button
            v-if="hasActiveFilters"
            type="button"
            class="mt-6 flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted"
            @click="handleResetFilters"
          >
            Réinitialiser les filtres
          </button>
        </div>

        <!-- Content Grid -->
        <div v-else>
          <div class="mb-6 flex items-center justify-between">
            <div class="text-xs min-[376px]:text-sm font-medium text-muted-foreground">
              {{ totalCount }} mission{{ totalCount > 1 ? 's' : '' }} disponible{{
                totalCount > 1 ? 's' : ''
              }}
              <span v-if="hasActiveFilters" class="text-primary">
                ({{ activeFilterCount }} filtre{{ activeFilterCount > 1 ? 's' : '' }} actif{{
                  activeFilterCount > 1 ? 's' : ''
                }})
              </span>
            </div>
            <button
              type="button"
              class="group p-2 text-muted-foreground transition-colors hover:text-primary"
              title="Rafraîchir la liste"
              @click="handleRefresh"
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
            class="grid gap-4 min-[376px]:gap-6 sm:grid-cols-2 xl:grid-cols-3"
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
            <p class="text-xs min-[376px]:text-sm text-muted-foreground">
              Page {{ currentPage }} sur {{ lastPage }}
            </p>

            <div class="flex items-center gap-2">
              <button
                type="button"
                :disabled="!hasPrevPage || isLoading"
                class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 min-[376px]:px-4 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                @click="handlePrevPage"
              >
                <ChevronLeft class="h-4 w-4" />
                Précédent
              </button>
              <button
                type="button"
                :disabled="!hasNextPage || isLoading"
                class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 min-[376px]:px-4 py-2 text-xs min-[376px]:text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                @click="handleNextPage"
              >
                Suivant
                <ChevronRight class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bg-primary {
  background-color: #198496;
}
.text-primary {
  color: #198496;
}
</style>
