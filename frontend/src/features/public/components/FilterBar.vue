<script setup lang="ts">
import { ref, watch } from 'vue'
import { Search, SlidersHorizontal, MapPin, Tag, Briefcase } from 'lucide-vue-next'
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

// Sync local state when props change (e.g. browser back/forward)
watch(
  () => props.currentFilters,
  (newFilters) => {
    selectedCategory.value = newFilters.categorie ?? ''
    selectedNiche.value = newFilters.niche ?? ''
    selectedCity.value = newFilters.ville ?? ''
  }
)

function handleFilterChange(): void {
  emit('filter-change', {
    categorie: selectedCategory.value || undefined,
    niche: selectedNiche.value || undefined,
    ville: selectedCity.value || undefined,
  })
}
</script>

<template>
  <div
    class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
    data-testid="filter-bar"
  >
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-6">
      <!-- Search Input (still disabled - out of scope) -->
      <div class="relative flex-1">
        <Search
          class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
        />
        <input
          type="text"
          placeholder="Rechercher un talent..."
          class="h-10 w-full rounded-lg border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-700 placeholder:text-gray-400 transition-colors focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
          data-testid="filter-search"
          disabled
          title="La recherche sera disponible prochainement"
        />
      </div>

      <!-- Filter Dropdowns -->
      <div class="flex flex-wrap items-center gap-3">
        <!-- City Filter -->
        <div class="relative">
          <MapPin
            aria-hidden="true"
            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
          />
          <select
            v-model="selectedCity"
            aria-label="Filtrer par ville"
            class="h-10 appearance-none rounded-lg border border-gray-200 bg-white pl-10 pr-8 text-sm text-gray-700 transition-colors hover:border-[#198496]/30 focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
            data-testid="filter-city"
            @change="handleFilterChange"
          >
            <option value="">Ville</option>
            <option v-for="city in cities" :key="city" :value="city">
              {{ city }}
            </option>
          </select>
        </div>

        <!-- Category Filter -->
        <div class="relative">
          <Tag
            aria-hidden="true"
            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
          />
          <select
            v-model="selectedCategory"
            aria-label="Filtrer par catégorie"
            class="h-10 appearance-none rounded-lg border border-gray-200 bg-white pl-10 pr-8 text-sm text-gray-700 transition-colors hover:border-[#198496]/30 focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
            data-testid="filter-category"
            @change="handleFilterChange"
          >
            <option value="">Catégorie</option>
            <option v-for="cat in categories" :key="cat.value" :value="cat.value">
              {{ cat.label }}
            </option>
          </select>
        </div>

        <!-- Niche Filter -->
        <div class="relative">
          <Briefcase
            aria-hidden="true"
            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
          />
          <select
            v-model="selectedNiche"
            aria-label="Filtrer par niche"
            class="h-10 appearance-none rounded-lg border border-gray-200 bg-white pl-10 pr-8 text-sm text-gray-700 transition-colors hover:border-[#198496]/30 focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
            data-testid="filter-niche"
            @change="handleFilterChange"
          >
            <option value="">Niche</option>
            <option v-for="niche in niches" :key="niche.value" :value="niche.value">
              {{ niche.label }}
            </option>
          </select>
        </div>

        <!-- Advanced Filters Button (Coming Soon) -->
        <button
          type="button"
          class="inline-flex h-10 cursor-not-allowed items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 text-sm font-medium text-gray-400 transition-colors"
          disabled
          title="Les filtres avancés seront disponibles prochainement"
          data-testid="filter-advanced"
        >
          <SlidersHorizontal class="h-4 w-4" />
          <span class="hidden sm:inline">Filtres avancés</span>
          <span
            class="rounded bg-[#198496]/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#198496]"
          >
            Bientôt
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
