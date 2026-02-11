<script setup lang="ts">
import { onMounted, onUnmounted, computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Search, Briefcase, AlertCircle, Loader2, X } from 'lucide-vue-next'
import { useAdminMissions } from '@/features/admin/composables/useAdminMissions'

const router = useRouter()
const { missions, pagination, isLoading, error, fetchMissions } = useAdminMissions()

const searchQuery = ref('')
const statusFilter = ref('')
const typeFilter = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const hasMissions = computed(() => missions.value.length > 0)
const totalPages = computed(() => pagination.value?.last_page ?? 1)
const currentPage = computed(() => pagination.value?.current_page ?? 1)

function buildParams(page: number = 1) {
  const params: Record<string, string | number> = { page }
  if (searchQuery.value) params.search = searchQuery.value
  if (statusFilter.value) params.status = statusFilter.value
  if (typeFilter.value) params.type_mission = typeFilter.value
  return params
}

function loadMissions(page: number = 1) {
  fetchMissions(buildParams(page))
}

onMounted(() => {
  loadMissions()
})

onUnmounted(() => {
  if (searchTimeout) clearTimeout(searchTimeout)
})

watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadMissions(1)
  }, 300)
})

watch([statusFilter, typeFilter], () => {
  loadMissions(1)
})

function goToPage(page: number): void {
  loadMissions(page)
}

function goToDetail(id: number): void {
  router.push({ name: 'admin-mission-detail', params: { id } })
}

function clearFilters(): void {
  searchQuery.value = ''
  statusFilter.value = ''
  typeFilter.value = ''
  loadMissions(1)
}

const hasActiveFilters = computed(
  () => searchQuery.value || statusFilter.value || typeFilter.value,
)

function getStatusClass(status: string): string {
  switch (status) {
    case 'draft': return 'bg-gray-100 text-gray-800'
    case 'published': return 'bg-green-100 text-green-800'
    case 'closed': return 'bg-orange-100 text-orange-800'
    case 'completed': return 'bg-blue-100 text-blue-800'
    default: return 'bg-gray-100 text-gray-800'
  }
}

function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
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
      <h1 class="text-2xl font-bold text-gray-900">Gestion des Missions</h1>
      <p class="mt-1 text-sm text-gray-500">
        Consultez toutes les missions de la plateforme
      </p>
    </div>

    <!-- Search & Filters -->
    <div class="flex flex-col sm:flex-row gap-3">
      <!-- Search -->
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher par titre, lieu ou producteur..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        />
      </div>

      <!-- Status filter -->
      <select
        v-model="statusFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
      >
        <option value="">Tous les statuts</option>
        <option value="draft">Brouillon</option>
        <option value="published">Publiée</option>
        <option value="closed">Clôturée</option>
        <option value="completed">Terminée</option>
      </select>

      <!-- Type mission filter -->
      <select
        v-model="typeFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
      >
        <option value="">Tous les types</option>
        <option value="publicite">Publicité</option>
        <option value="film">Film</option>
        <option value="court_metrage">Court-métrage</option>
        <option value="clip_musical">Clip musical</option>
        <option value="autre">Autre</option>
      </select>

      <!-- Clear filters -->
      <button
        v-if="hasActiveFilters"
        @click="clearFilters"
        class="inline-flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
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
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex items-center justify-center py-12"
    >
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!hasMissions && !error"
      class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center"
    >
      <Briefcase class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-4 text-lg font-medium text-gray-900">Aucune mission trouvée</h3>
      <p class="mt-2 text-sm text-gray-500">
        {{ hasActiveFilters ? 'Aucun résultat pour ces critères de recherche.' : 'Aucune mission sur la plateforme.' }}
      </p>
    </div>

    <!-- Missions Table -->
    <div
      v-else-if="hasMissions"
      class="overflow-x-auto rounded-xl border border-gray-200 bg-white"
    >
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Titre
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Producteur
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Statut
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Type
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Lieu
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Candidatures
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Tournage
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Créée le
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="m in missions"
            :key="m.id"
            class="hover:bg-gray-50 transition-colors cursor-pointer"
            @click="goToDetail(m.id)"
          >
            <td class="whitespace-nowrap px-6 py-4">
              <p class="text-sm font-medium text-gray-900 max-w-[200px] truncate">{{ m.titre }}</p>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ m.producer?.display_name ?? '—' }}
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="getStatusClass(m.status)"
              >
                {{ m.status_label }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ m.type_mission_label ?? '—' }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 max-w-[150px] truncate">
              {{ m.lieu ?? '—' }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 text-center">
              {{ m.candidatures_count }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ formatDate(m.date_tournage) }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ formatDate(m.created_at) }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div
        v-if="totalPages > 1"
        class="flex items-center justify-between border-t border-gray-200 bg-white px-6 py-3"
      >
        <p class="text-sm text-gray-700">
          Page {{ currentPage }} sur {{ totalPages }}
          <span class="text-gray-500">({{ pagination?.total }} résultats)</span>
        </p>
        <div class="flex gap-2">
          <button
            :disabled="currentPage <= 1"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            @click="goToPage(currentPage - 1)"
          >
            Précédent
          </button>
          <button
            :disabled="currentPage >= totalPages"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            @click="goToPage(currentPage + 1)"
          >
            Suivant
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
