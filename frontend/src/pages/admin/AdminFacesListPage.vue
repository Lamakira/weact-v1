<script setup lang="ts">
import { onMounted, onUnmounted, computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Search, Users, AlertCircle, Loader2, X } from 'lucide-vue-next'
import { useAdminFaces } from '@/features/admin/composables/useAdminFaces'
import { getCategoryLabel, getCategoryColor } from '@/features/admin/utils/faceLabels'

const router = useRouter()
const { faces, pagination, isLoading, error, fetchFaces } = useAdminFaces()

const searchQuery = ref('')
const categoryFilter = ref('')
const availabilityFilter = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const hasFaces = computed(() => faces.value.length > 0)
const totalPages = computed(() => pagination.value?.last_page ?? 1)
const currentPage = computed(() => pagination.value?.current_page ?? 1)

function buildParams(page: number = 1) {
  const params: Record<string, string | number> = { page }
  if (searchQuery.value) params.search = searchQuery.value
  if (categoryFilter.value) params.category = categoryFilter.value
  if (availabilityFilter.value) params.is_available = availabilityFilter.value
  return params
}

function loadFaces(page: number = 1) {
  fetchFaces(buildParams(page))
}

onMounted(() => {
  loadFaces()
})

onUnmounted(() => {
  if (searchTimeout) clearTimeout(searchTimeout)
})

watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadFaces(1)
  }, 300)
})

watch([categoryFilter, availabilityFilter], () => {
  loadFaces(1)
})

function goToPage(page: number): void {
  loadFaces(page)
}

function goToDetail(id: number): void {
  router.push({ name: 'admin-face-detail', params: { id } })
}

function clearFilters(): void {
  searchQuery.value = ''
  categoryFilter.value = ''
  availabilityFilter.value = ''
  loadFaces(1)
}

const hasActiveFilters = computed(
  () => searchQuery.value || categoryFilter.value || availabilityFilter.value,
)

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}


</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Gestion des Faces</h1>
      <p class="mt-1 text-sm text-gray-500">
        Gérez les comptes Face de la plateforme
      </p>
    </div>

    <!-- Search & Filters -->
    <div class="flex flex-col sm:flex-row gap-3" data-testid="filters-bar">
      <!-- Search -->
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher par nom, username ou email..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          data-testid="search-input"
        />
      </div>

      <!-- Category filter -->
      <select
        v-model="categoryFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        data-testid="category-filter"
      >
        <option value="">Toutes catégories</option>
        <option value="acteur">Acteur</option>
        <option value="influenceur">Influenceur</option>
        <option value="createur">Créateur</option>
        <option value="mannequin">Mannequin</option>
        <option value="figurant">Figurant</option>
      </select>

      <!-- Availability filter -->
      <select
        v-model="availabilityFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        data-testid="availability-filter"
      >
        <option value="">Toutes disponibilités</option>
        <option value="1">Disponible</option>
        <option value="0">Indisponible</option>
      </select>

      <!-- Clear filters -->
      <button
        v-if="hasActiveFilters"
        @click="clearFilters"
        class="inline-flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
        data-testid="clear-filters"
      >
        <X class="h-4 w-4" />
        Effacer
      </button>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
      data-testid="error-message"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex items-center justify-center py-12"
      data-testid="loading-state"
    >
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!hasFaces && !error"
      class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center"
      data-testid="empty-state"
    >
      <Users class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-4 text-lg font-medium text-gray-900">Aucune Face trouvée</h3>
      <p class="mt-2 text-sm text-gray-500">
        {{ hasActiveFilters ? 'Aucun résultat pour ces critères de recherche.' : 'Aucun compte Face sur la plateforme.' }}
      </p>
    </div>

    <!-- Faces Table -->
    <div
      v-else-if="hasFaces"
      class="overflow-hidden rounded-xl border border-gray-200 bg-white"
      data-testid="faces-table"
    >
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Face
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Username
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Catégorie
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Disponibilité
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Profil
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Date
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="f in faces"
            :key="f.id"
            class="hover:bg-gray-50 transition-colors cursor-pointer"
            data-testid="face-row"
            @click="goToDetail(f.id)"
          >
            <td class="whitespace-nowrap px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-full bg-primary-100 flex items-center justify-center text-sm font-medium text-primary-700 shrink-0">
                  {{ (f.prenom?.[0] ?? '').toUpperCase() }}{{ (f.nom?.[0] ?? '').toUpperCase() }}
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900">{{ f.prenom }} {{ f.nom }}</p>
                </div>
              </div>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              @{{ f.username }}
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="getCategoryColor(f.categorie)"
              >
                {{ getCategoryLabel(f.categorie) }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="f.is_available ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
              >
                {{ f.is_available ? 'Disponible' : 'Indisponible' }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <div class="flex items-center gap-2">
                <div class="h-2 w-16 rounded-full bg-gray-200 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="f.profile_completion_percentage >= 80 ? 'bg-green-500' : f.profile_completion_percentage >= 50 ? 'bg-amber-500' : 'bg-red-400'"
                    :style="{ width: f.profile_completion_percentage + '%' }"
                  />
                </div>
                <span class="text-xs text-gray-500">{{ f.profile_completion_percentage }}%</span>
              </div>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ formatDate(f.created_at) }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="flex items-center justify-between border-t border-gray-200 bg-white px-6 py-3"
        data-testid="pagination"
      >
        <p class="text-sm text-gray-700">
          Page {{ currentPage }} sur {{ totalPages }}
          <span class="text-gray-500">({{ pagination?.total }} résultats)</span>
        </p>
        <div class="flex gap-2">
          <button
            :disabled="currentPage <= 1"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            data-testid="pagination-prev"
            @click="goToPage(currentPage - 1)"
          >
            Précédent
          </button>
          <button
            :disabled="currentPage >= totalPages"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            data-testid="pagination-next"
            @click="goToPage(currentPage + 1)"
          >
            Suivant
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
