<script setup lang="ts">
/**
 * ProducerFacesListPage
 * Lists all faces with filters and pagination, reusing the same
 * composables/components as the public /faces page.
 */
import { ref, onMounted } from 'vue'
import { Users, AlertCircle, RefreshCw } from 'lucide-vue-next'
import { usePaginatedFaces } from '@/features/public/composables/usePaginatedFaces'
import { fetchFilterOptions, type FilterOption, type FacesFilterParams } from '@/features/public/services/publicFacesApi'
import FaceCard from '@/features/public/components/FaceCard.vue'
import FilterBar from '@/features/public/components/FilterBar.vue'
import { Pagination } from '@/components/ui/pagination'
import { Skeleton } from '@/components/ui/skeleton'

const {
  faces,
  isLoading,
  error,
  isEmpty,
  currentPage,
  totalPages,
  totalItems,
  filters,
  hasActiveFilters,
  loadPage,
  updateFilters,
  retry,
} = usePaginatedFaces(15)

const categories = ref<FilterOption[]>([])
const niches = ref<FilterOption[]>([])
const cities = ref<string[]>([])
const filterOptionsError = ref(false)

async function loadFilterOptions(): Promise<void> {
  filterOptionsError.value = false
  try {
    const options = await fetchFilterOptions()
    categories.value = options.data.categories
    niches.value = options.data.niches
    cities.value = options.data.cities
  } catch {
    filterOptionsError.value = true
    console.error('Failed to load filter options')
  }
}

onMounted(() => {
  loadFilterOptions()
})

function handleFilterChange(newFilters: FacesFilterParams): void {
  updateFilters(newFilters)
}

function handlePageChange(page: number): void {
  loadPage(page)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Liste des faces</h1>
      <p class="mt-1 text-sm text-slate-500">
        Recherchez et filtrez les talents disponibles sur la plateforme.
      </p>
    </div>

    <!-- Filters -->
    <div class="mb-8">
      <FilterBar
        :categories="categories"
        :niches="niches"
        :cities="cities"
        :current-filters="filters"
        @filter-change="handleFilterChange"
      />
      <p
        v-if="filterOptionsError"
        class="mt-2 text-center text-xs text-amber-600"
      >
        Les options de filtres n'ont pas pu être chargées.
        <button
          type="button"
          class="underline hover:text-amber-800"
          @click="loadFilterOptions"
        >
          Réessayer
        </button>
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="space-y-8">
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
        <div
          v-for="i in 8"
          :key="`skeleton-${i}`"
          class="rounded-2xl border border-gray-100 bg-white overflow-hidden"
        >
          <Skeleton class="aspect-[4/5] w-full" />
          <div class="p-4 space-y-2">
            <Skeleton class="h-5 w-2/3 mx-auto" />
            <Skeleton class="h-4 w-1/2 mx-auto" />
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="text-center py-16"
    >
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 text-red-600 mb-4">
        <AlertCircle class="w-8 h-8" />
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">
        Oups ! Une erreur est survenue
      </h2>
      <p class="text-gray-600 mb-6 max-w-md mx-auto">
        {{ error }}
      </p>
      <button
        type="button"
        class="inline-flex items-center gap-2 bg-[#198496] text-white px-6 py-3 rounded-lg font-medium hover:bg-[#146c7a] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
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
    >
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 text-gray-400 mb-4">
        <Users class="w-8 h-8" />
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">
        {{ hasActiveFilters ? 'Aucun talent ne correspond à vos critères' : 'Aucun talent disponible' }}
      </h2>
      <p class="text-gray-600 max-w-md mx-auto">
        {{ hasActiveFilters
          ? 'Essayez d\'élargir vos filtres pour voir plus de résultats.'
          : 'Il n\'y a pas encore de talents inscrits sur la plateforme. Revenez bientôt !'
        }}
      </p>
    </div>

    <!-- Faces Grid -->
    <div v-else class="space-y-8">
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
          <span class="font-medium text-gray-900">{{ totalItems }}</span>
          talent{{ totalItems > 1 ? 's' : '' }} trouvé{{ totalItems > 1 ? 's' : '' }}
        </p>
      </div>

      <div
        class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6"
      >
        <FaceCard
          v-for="face in faces"
          :key="face.id"
          :face="face"
        />
      </div>

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="flex justify-center pt-8"
        data-testid="faces-pagination"
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
