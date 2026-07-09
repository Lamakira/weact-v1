<script setup lang="ts">
import { computed } from 'vue'
import { Briefcase, AlertCircle, RefreshCw } from 'lucide-vue-next'
import { UgcDiscoveryBanner } from '@/components/ugc'
import { usePaginatedMissions } from '@/features/public/composables/usePaginatedMissions'
import type { PublicMissionFilters } from '@/features/public/services/publicMissionsApi'
import PublicMissionCard from '@/features/public/components/PublicMissionCard.vue'
import PublicMissionFiltersBar from '@/features/public/components/PublicMissionFiltersBar.vue'
import RegistrationCta from '@/features/public/components/RegistrationCta.vue'
import { Pagination } from '@/components/ui/pagination'
import { Skeleton } from '@/components/ui/skeleton'
import { getMissionTypeOptions } from '@/features/mission/types'
import { useAuthStore } from '@/stores/auth'

// Named so App.vue's <keep-alive :include> can cache this listing across a
// browse-and-return to a mission detail (Group A). Must match the :include entry.
defineOptions({ name: 'PublicMissionsView' })

const {
  missions,
  isLoading,
  error,
  isEmpty,
  currentPage,
  totalPages,
  totalMissions,
  filters,
  hasActiveFilters,
  loadPage,
  retry,
  updateFilters,
} = usePaginatedMissions(15)

const missionTypes = getMissionTypeOptions()

// ugc-disc-2: UGC discovery banner shown to anonymous visitors + logged-in Faces,
// hidden for logged-in non-Face users (Producer/Admin would be bounced by the role guard).
const authStore = useAuthStore()
const showUgcBanner = computed(
  () => !authStore.isAuthenticated || authStore.user?.userable_type === 'Face',
)

// ugc-disc-2 (CTA target): anonymous visitors → Face registration. The role guard would
// otherwise bounce an anonymous click to a login wall (useless for someone with no account);
// sending them to sign-up — carrying a redirect so they land on the gated UGC page right after —
// is the lower-friction acquisition path. Logged-in Faces (the only other audience the banner
// shows to) go straight to the UGC page.
const ugcCtaTarget = computed(() =>
  authStore.isAuthenticated
    ? { name: 'face-ugc-missions' }
    : { name: 'register-face', query: { redirect: '/face/ugc-missions' } },
)

const resultsLabel = computed(() => {
  const plural = totalMissions.value > 1 ? 's' : ''

  if (hasActiveFilters.value) {
    return `${plural ? 'missions trouvées' : 'mission trouvée'}`
  }

  return `mission${plural} disponible${plural}`
})

function handlePageChange(page: number): void {
  loadPage(page)
}

function handleFilterChange(newFilters: PublicMissionFilters): void {
  updateFilters(newFilters)
}
</script>

<template>
  <div data-testid="public-missions-view">
    <!-- Page Header -->
    <header class="mb-8 text-center">
      <h1
        class="mb-3 text-3xl font-bold tracking-tight text-gray-900"
        data-testid="missions-page-title"
      >
        Missions disponibles
      </h1>
      <p class="text-gray-600 text-lg max-w-2xl mx-auto">
        Découvrez les opportunités de casting au Bénin.
        Publicités, films, courts-métrages, clips musicaux et plus encore.
      </p>
    </header>

    <!-- UGC Discovery Entry Point (ugc-disc-2) — public-page door into the conversion tunnel.
         Anonymous click → Face registration (redirect-back) → gated UGC teaser/paywall after sign-up.
         Logged-in Face → straight to the gated UGC page. -->
    <UgcDiscoveryBanner
      v-if="showUgcBanner"
      :to="ugcCtaTarget"
      test-id="ugc-discovery-cta-public"
      class="mb-8"
    />

    <PublicMissionFiltersBar
      :current-filters="filters"
      :mission-types="missionTypes"
      class="mb-8"
      @filter-change="handleFilterChange"
    />

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
        {{ hasActiveFilters ? 'Aucun résultat pour ces filtres.' : 'Aucune mission disponible pour le moment.' }}
      </h2>
      <p class="text-gray-600 max-w-md mx-auto">
        {{
          hasActiveFilters
            ? 'Aucune mission ne correspond à vos critères.'
            : 'Il n\'y a pas encore de missions publiées sur la plateforme.'
        }}
      </p>
      <button
        v-if="hasActiveFilters"
        type="button"
        class="mt-8 inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-6 py-3 text-sm font-medium text-gray-700 transition-colors hover:border-[#198496]/30 hover:bg-[#198496]/5 hover:text-[#198496] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
        data-testid="missions-empty-reset"
        @click="handleFilterChange({})"
      >
        Réinitialiser les filtres
      </button>
      <RegistrationCta v-else variant="missions" class="mt-8" />
    </div>

    <!-- Missions Grid -->
    <div v-else class="space-y-8">
      <!-- Results Count -->
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500" data-testid="missions-count">
          <span class="font-medium text-gray-900">{{ totalMissions }}</span>
          {{ resultsLabel }}
        </p>
        <button
          v-if="hasActiveFilters"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-[#198496]/30 hover:bg-[#198496]/5 hover:text-[#198496] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
          data-testid="missions-reset-inline"
          @click="handleFilterChange({})"
        >
          Réinitialiser
        </button>
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
      <RegistrationCta v-if="!hasActiveFilters" variant="missions" />

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
