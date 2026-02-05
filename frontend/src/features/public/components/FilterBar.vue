<script setup lang="ts">
import { ref } from 'vue'
import { Search, SlidersHorizontal, MapPin, Tag, Briefcase } from 'lucide-vue-next'

// Placeholder state - actual filtering logic is in Story 11-5
const searchQuery = ref('')
const selectedCity = ref('')
const selectedCategory = ref('')
const selectedNiche = ref('')

// Placeholder options - will be populated from API in Story 11-5
const cities = ['Cotonou', 'Porto-Novo', 'Abomey-Calavi', 'Parakou', 'Djougou']
const categories = ['Acteur', 'Mannequin', 'Influenceur', 'Créateur', 'Figurant']
const niches = ['Publicité', 'Cinéma', 'Mode', 'Télévision', 'Digital']

// Placeholder function - will trigger actual filtering in Story 11-5
function handleFilterChange(): void {
  // Filtering logic will be implemented in Story 11-5
  // Currently disabled - all filter inputs are non-functional
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
          class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher un talent..."
          class="h-10 w-full rounded-lg border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-700 placeholder:text-gray-400 transition-colors focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
          data-testid="filter-search"
          disabled
          title="La recherche sera disponible prochainement"
          @input="handleFilterChange"
        />
      </div>

      <!-- Filter Dropdowns -->
      <div class="flex flex-wrap items-center gap-3">
        <!-- City Filter -->
        <div class="relative">
          <MapPin
            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
          />
          <select
            v-model="selectedCity"
            class="h-10 cursor-not-allowed appearance-none rounded-lg border border-gray-200 bg-gray-50 pl-10 pr-8 text-sm text-gray-400 transition-colors focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
            data-testid="filter-city"
            disabled
            title="Le filtre par ville sera disponible prochainement"
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
            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
          />
          <select
            v-model="selectedCategory"
            class="h-10 cursor-not-allowed appearance-none rounded-lg border border-gray-200 bg-gray-50 pl-10 pr-8 text-sm text-gray-400 transition-colors focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
            data-testid="filter-category"
            disabled
            title="Le filtre par catégorie sera disponible prochainement"
            @change="handleFilterChange"
          >
            <option value="">Catégorie</option>
            <option v-for="cat in categories" :key="cat" :value="cat">
              {{ cat }}
            </option>
          </select>
        </div>

        <!-- Niche Filter -->
        <div class="relative">
          <Briefcase
            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none"
          />
          <select
            v-model="selectedNiche"
            class="h-10 cursor-not-allowed appearance-none rounded-lg border border-gray-200 bg-gray-50 pl-10 pr-8 text-sm text-gray-400 transition-colors focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
            data-testid="filter-niche"
            disabled
            title="Le filtre par niche sera disponible prochainement"
            @change="handleFilterChange"
          >
            <option value="">Niche</option>
            <option v-for="niche in niches" :key="niche" :value="niche">
              {{ niche }}
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
