<script setup lang="ts">
import { onMounted, onUnmounted, onDeactivated, computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Search, Users, AlertCircle, Loader2, X } from 'lucide-vue-next'
import { useAdminProducers } from '@/features/admin/composables/useAdminProducers'
import { getProducerTypeLabel, getProducerTypeColor } from '@/features/admin/utils/producerLabels'
import { useRefreshOnReturn } from '@/composables/useRefreshOnReturn'

// Explicit name (devtools). Caching is driven by the route's meta.keepAlive flag.
defineOptions({ name: 'AdminProducersListPage' })

const router = useRouter()
const { producers, pagination, isLoading, error, fetchProducers } = useAdminProducers()

const searchQuery = ref('')
const typeFilter = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const hasProducers = computed(() => producers.value.length > 0)
const totalPages = computed(() => pagination.value?.last_page ?? 1)
const currentPage = computed(() => pagination.value?.current_page ?? 1)

function buildParams(page: number = 1) {
  const params: Record<string, string | number> = { page }
  if (searchQuery.value) params.search = searchQuery.value
  if (typeFilter.value) params.type = typeFilter.value
  return params
}

function loadProducers(page: number = 1) {
  fetchProducers(buildParams(page))
}

onMounted(() => {
  loadProducers()
})

// Cached by keep-alive: refresh on return so detail-page changes are reflected.
useRefreshOnReturn(() => loadProducers(currentPage.value))

onUnmounted(() => {
  if (searchTimeout) clearTimeout(searchTimeout)
})

// Cached by keep-alive: onUnmounted no longer fires when leaving the page —
// cancel a pending search debounce on deactivation too, so it can't fetch
// off-screen and race the return refresh.
onDeactivated(() => {
  if (searchTimeout) clearTimeout(searchTimeout)
})

watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadProducers(1)
  }, 300)
})

watch(typeFilter, () => {
  loadProducers(1)
})

function goToPage(page: number): void {
  loadProducers(page)
}

function goToDetail(id: string): void {
  router.push({ name: 'admin-producer-detail', params: { id } })
}

function clearFilters(): void {
  searchQuery.value = ''
  typeFilter.value = ''
  loadProducers(1)
}

const hasActiveFilters = computed(() => searchQuery.value || typeFilter.value)

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
      <h1 class="text-2xl font-bold text-gray-900">Gestion des Producteurs</h1>
      <p class="mt-1 text-sm text-gray-500">
        Gérez les comptes Producteur de la plateforme
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
          placeholder="Rechercher par nom, agence ou email..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          data-testid="search-input"
        />
      </div>

      <!-- Type filter -->
      <select
        v-model="typeFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        data-testid="type-filter"
      >
        <option value="">Tous types</option>
        <option value="agency">Agence</option>
        <option value="particulier">Particulier</option>
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
      v-else-if="!hasProducers && !error"
      class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center"
      data-testid="empty-state"
    >
      <Users class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-4 text-lg font-medium text-gray-900">Aucun Producteur trouvé</h3>
      <p class="mt-2 text-sm text-gray-500">
        {{ hasActiveFilters ? 'Aucun résultat pour ces critères de recherche.' : 'Aucun compte Producteur sur la plateforme.' }}
      </p>
    </div>

    <!-- Producers Table -->
    <div
      v-else-if="hasProducers"
      class="overflow-x-auto rounded-xl border border-gray-200 bg-white"
      data-testid="producers-table"
    >
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Producteur
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Type
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Email
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Statut
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Missions
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Note
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Date
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="p in producers"
            :key="p.id"
            class="hover:bg-gray-50 transition-colors cursor-pointer"
            data-testid="producer-row"
            @click="goToDetail(p.id)"
          >
            <td class="whitespace-nowrap px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-full bg-purple-100 flex items-center justify-center text-sm font-medium text-purple-700 shrink-0">
                  {{ (p.display_name?.[0] ?? '').toUpperCase() }}
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900">{{ p.display_name }}</p>
                </div>
              </div>
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="getProducerTypeColor(p.type)"
              >
                {{ getProducerTypeLabel(p.type) }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ p.email || '—' }}
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="p.is_active !== false ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                data-testid="status-badge"
              >
                {{ p.is_active !== false ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
              {{ p.missions_count ?? '—' }}
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <div class="flex items-center gap-1">
                <span v-if="p.average_rating" class="text-sm text-gray-900">{{ p.average_rating.toFixed(1) }}</span>
                <span v-else class="text-sm text-gray-400">—</span>
                <span v-if="p.ratings_count" class="text-xs text-gray-500">({{ p.ratings_count }})</span>
              </div>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ formatDate(p.created_at) }}
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
