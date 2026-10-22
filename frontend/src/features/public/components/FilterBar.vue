<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { watchDebounced } from '@vueuse/core'
import { Search, MapPin, Tag, Briefcase, X } from 'lucide-vue-next'
import { SimpleSelect } from '@/components/ui/select'
import { SearchableSelect } from '@/components/ui/combobox'
import type { FilterOption, FacesFilterParams } from '../services/publicFacesApi'

const props = defineProps<{
  categories: FilterOption[]
  niches: FilterOption[]
  cities: string[]
  currentFilters: FacesFilterParams
}>()

const emit = defineEmits<{
  'filter-change': [filters: FacesFilterParams]
}>()

// Local state synced from props
const selectedCategory = ref(props.currentFilters.categorie ?? '')
const selectedNiche = ref(props.currentFilters.niche ?? '')
const selectedCity = ref(props.currentFilters.ville ?? '')
const searchQuery = ref(props.currentFilters.search ?? '')

// Sync local state when props change (e.g. browser back/forward)
watch(
  () => props.currentFilters,
  (newFilters) => {
    selectedCategory.value = newFilters.categorie ?? ''
    selectedNiche.value = newFilters.niche ?? ''
    selectedCity.value = newFilters.ville ?? ''
    searchQuery.value = newFilters.search ?? ''
  }
)

// Debounced search: emit filter-change 300ms after typing stops
watchDebounced(
  searchQuery,
  (value) => {
    // Skip API call for single character (backend requires min:2)
    if (value && value.length < 2) return

    emit('filter-change', {
      categorie: selectedCategory.value || undefined,
      niche: selectedNiche.value || undefined,
      ville: selectedCity.value || undefined,
      search: value || undefined,
    })
  },
  { debounce: 300 }
)

const cityOptions = computed(() => props.cities.map((city) => ({ value: city, label: city })))

function onCityChange(value: string): void {
  selectedCity.value = value
  handleFilterChange()
}

function onCategoryChange(value: string): void {
  selectedCategory.value = value
  handleFilterChange()
}

function onNicheChange(value: string): void {
  selectedNiche.value = value
  handleFilterChange()
}

function handleFilterChange(): void {
  const search = searchQuery.value && searchQuery.value.length >= 2 ? searchQuery.value : undefined
  emit('filter-change', {
    categorie: selectedCategory.value || undefined,
    niche: selectedNiche.value || undefined,
    ville: selectedCity.value || undefined,
    search,
  })
}

function clearSearch(): void {
  searchQuery.value = ''
}
</script>

<template>
  <div
    class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
    data-testid="filter-bar"
  >
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-6">
      <!-- Search Input -->
      <div class="relative flex-1">
        <Search
          aria-hidden="true"
          class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher un talent..."
          class="h-10 w-full rounded-lg border border-gray-200 bg-white pl-10 text-sm text-gray-700 placeholder:text-gray-400 transition-colors hover:border-[#198496]/30 focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
          :class="searchQuery ? 'pr-9' : 'pr-4'"
          maxlength="255"
          data-testid="filter-search"
          aria-label="Rechercher un talent"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
          data-testid="filter-search-clear"
          aria-label="Effacer la recherche"
          @click="clearSearch"
        >
          <X class="h-4 w-4" aria-hidden="true" />
        </button>
      </div>

      <!-- Filter Dropdowns -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:flex lg:flex-wrap lg:items-center">
        <!-- City Filter -->
        <div class="lg:w-48">
          <SearchableSelect
            :model-value="selectedCity"
            :options="cityOptions"
            :icon="MapPin"
            placeholder="Ville"
            empty-label="Toutes les villes"
            search-placeholder="Rechercher une ville…"
            empty-text="Aucune ville trouvée"
            aria-label="Filtrer par ville"
            data-testid="filter-city"
            @update:model-value="onCityChange"
          />
        </div>

        <!-- Category Filter -->
        <div class="lg:w-48">
          <SimpleSelect
            :model-value="selectedCategory"
            :options="categories"
            :icon="Tag"
            placeholder="Catégorie"
            empty-label="Toutes les catégories"
            aria-label="Filtrer par catégorie"
            data-testid="filter-category"
            @update:model-value="onCategoryChange"
          />
        </div>

        <!-- Niche Filter -->
        <div class="lg:w-48">
          <SimpleSelect
            :model-value="selectedNiche"
            :options="niches"
            :icon="Briefcase"
            placeholder="Niche"
            empty-label="Toutes les niches"
            aria-label="Filtrer par niche"
            data-testid="filter-niche"
            @update:model-value="onNicheChange"
          />
        </div>
      </div>
    </div>
  </div>
</template>
