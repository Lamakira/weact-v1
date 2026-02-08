<script setup lang="ts">
import { Briefcase, AlertCircle, RefreshCw } from 'lucide-vue-next'
import { usePaginatedMissions } from '@/features/public/composables/usePaginatedMissions'
import PublicMissionCard from '@/features/public/components/PublicMissionCard.vue'
import RegistrationCta from '@/features/public/components/RegistrationCta.vue'
import { Pagination } from '@/components/ui/pagination'
import { Skeleton } from '@/components/ui/skeleton'

// Initialize pagination composable with 15 items per page
const {
  missions,
  isLoading,
  error,
  isEmpty,
  currentPage,
  totalPages,
  totalMissions,
  loadPage,
  retry,
} = usePaginatedMissions(15)

function handlePageChange(page: number): void {
  loadPage(page)
}
</script>

<template>
  <div class="py-8" data-testid="public-missions-view">
    <!-- Page Header -->
    <header class="mb-8 text-center">
      <div class="flex items-center justify-center gap-2 mb-4">
        <div class="bg-[#198496] text-white p-1.5 rounded">
          <Briefcase class="w-4 h-4" aria-hidden="true" />
        </div>
        <span class="text-xs font-bold tracking-widest uppercase text-[#198496]">
          Missions
        </span>
      </div>
      <h1
        class="text-3xl sm:text-4xl font-bold text-gray-900 mb-3"
        data-testid="missions-page-title"
      >
        Missions disponibles
      </h1>
      <p class="text-gray-600 text-lg max-w-2xl mx-auto">
        Découvrez les opportunités de casting au Bénin.
        Publicités, films, courts-métrages, clips musicaux et plus encore.
      </p>
    </header>

    <!-- Loading State -->
    <div v-if="isLoading" class="space-y-8" data-testid="missions-loading">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        <div
          v-for="i in 6"
          :key="`skeleton-${i}`"
          class="rounded-lg border border-gray-100 bg-white overflow-hidden p-5"
        >
          <Skeleton class="h-5 w-20 mb-3 rounded-full" />
          <Skeleton class="h-6 w-3/4 mb-4" />
          <Skeleton class="h-16 w-full mb-6" />
          <div class="grid grid-cols-2 gap-3 mb-6">
            <Skeleton class="h-4 w-full" />
            <Skeleton class="h-4 w-full" />
            <Skeleton class="h-4 w-full" />
            <Skeleton class="h-4 w-full" />
          </div>
          <Skeleton class="h-px w-full mb-4" />
          <div class="flex items-center gap-3">
            <Skeleton class="h-8 w-8 rounded-full" />
            <Skeleton class="h-4 w-24" />
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="text-center py-16"
      data-testid="missions-error"
    >
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 text-red-600 mb-4">
        <AlertCircle class="w-8 h-8" />
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">
        Oops ! Une erreur est survenue
      </h2>
      <p class="text-gray-600 mb-6 max-w-md mx-auto">
        {{ error }}
      </p>
      <button
        type="button"
        class="inline-flex items-center gap-2 bg-[#198496] text-white px-6 py-3 rounded-lg font-medium hover:bg-[#146c7a] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
        data-testid="missions-retry-button"
        @click="retry"
      >
        <RefreshCw class="w-4 h-4" />
        Réessayer
      </button>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="isEmpty"
      class="text-center py-16"
      data-testid="missions-empty"
    >
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 text-gray-400 mb-4">
        <Briefcase class="w-8 h-8" />
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">
        Aucune mission disponible pour le moment.
      </h2>
      <p class="text-gray-600 max-w-md mx-auto">
        Il n'y a pas encore de missions publiées sur la plateforme. Revenez bientôt !
      </p>
      <RegistrationCta variant="missions" class="mt-8" />
    </div>

    <!-- Missions Grid -->
    <div v-else class="space-y-8">
      <!-- Results Count -->
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500" data-testid="missions-count">
          <span class="font-medium text-gray-900">{{ totalMissions }}</span>
          mission{{ totalMissions > 1 ? 's' : '' }} disponible{{ totalMissions > 1 ? 's' : '' }}
        </p>
      </div>

      <!-- Grid -->
      <div
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6"
        data-testid="missions-grid"
      >
        <PublicMissionCard
          v-for="mission in missions"
          :key="mission.id"
          :mission="mission"
        />
      </div>

      <!-- Registration CTA -->
      <RegistrationCta variant="missions" />

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="flex justify-center pt-8"
        data-testid="missions-pagination"
      >
        <Pagination
          :current-page="currentPage"
          :total-pages="totalPages"
          @page-change="handlePageChange"
        />
      </div>
    </div>
  </div>
</template>
