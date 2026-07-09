<script setup lang="ts">
import { onMounted, onUnmounted, computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Search, FileText, AlertCircle, Loader2, X, Plus, Pencil, Trash2 } from 'lucide-vue-next'
import { useAdminArticles } from '@/features/admin/composables/useAdminArticles'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { useToast } from '@/composables/useToast'

// Named so <keep-alive :include> in AdminLayout can cache this listing.
defineOptions({ name: 'AdminArticlesListPage' })

const router = useRouter()
const { articles, pagination, isLoading, error, fetchArticles, toggleStatus, deleteArticle } =
  useAdminArticles()

const toast = useToast()

const searchQuery = ref('')
const categoryFilter = ref('')
const statusFilter = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const showDeleteModal = ref(false)
const articleToDelete = ref<{ id: string; title: string } | null>(null)
const isDeleting = ref(false)
const isTogglingId = ref<string | null>(null)

const hasArticles = computed(() => articles.value.length > 0)
const totalPages = computed(() => pagination.value?.last_page ?? 1)
const currentPage = computed(() => pagination.value?.current_page ?? 1)

function buildParams(page: number = 1): Record<string, string | number> {
  const params: Record<string, string | number> = { page }
  if (searchQuery.value) params.search = searchQuery.value
  if (categoryFilter.value) params.category = categoryFilter.value
  if (statusFilter.value) params.status = statusFilter.value
  return params
}

function loadArticles(page: number = 1): void {
  fetchArticles(buildParams(page))
}

onMounted(() => {
  loadArticles()
})

onUnmounted(() => {
  if (searchTimeout) clearTimeout(searchTimeout)
})

watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadArticles(1)
  }, 300)
})

watch([categoryFilter, statusFilter], () => {
  loadArticles(1)
})

function goToPage(page: number): void {
  loadArticles(page)
}

function goToCreate(): void {
  router.push({ name: 'admin-articles-create' })
}

function goToEdit(id: string): void {
  router.push({ name: 'admin-articles-edit', params: { id } })
}

function clearFilters(): void {
  searchQuery.value = ''
  categoryFilter.value = ''
  statusFilter.value = ''
  loadArticles(1)
}

const hasActiveFilters = computed(
  () => searchQuery.value || categoryFilter.value || statusFilter.value,
)

function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function getCategoryColor(categoryValue: string): string {
  switch (categoryValue) {
    case 'conseils-face':
      return 'bg-blue-100 text-blue-700'
    case 'guide-producteur':
      return 'bg-purple-100 text-purple-700'
    case 'actualites':
      return 'bg-amber-100 text-amber-700'
    default:
      return 'bg-gray-100 text-gray-700'
  }
}

async function handleToggleStatus(id: string, currentStatus: string): Promise<void> {
  isTogglingId.value = id
  const result = await toggleStatus(id, currentStatus)
  isTogglingId.value = null
  if (result.success) {
    toast.success(result.message ?? 'Statut mis à jour')
  }
}

function openDeleteModal(id: string, title: string): void {
  articleToDelete.value = { id, title }
  showDeleteModal.value = true
}

async function confirmDelete(): Promise<void> {
  if (!articleToDelete.value) return
  showDeleteModal.value = false
  isDeleting.value = true

  const result = await deleteArticle(articleToDelete.value.id)
  isDeleting.value = false
  articleToDelete.value = null

  if (result.success) {
    toast.success(result.message ?? 'Article supprimé')
    loadArticles(currentPage.value)
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Gestion des Articles</h1>
        <p class="mt-1 text-sm text-gray-500">
          Gérez les articles du blog de la plateforme
        </p>
      </div>
      <button
        class="inline-flex items-center gap-2 self-start rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors sm:self-auto"
        data-testid="create-article-btn"
        @click="goToCreate"
      >
        <Plus class="h-4 w-4" />
        Nouvel article
      </button>
    </div>

    <!-- Search & Filters -->
    <div class="flex flex-col sm:flex-row gap-3" data-testid="filters-bar">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher par titre..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          data-testid="search-input"
        />
      </div>

      <select
        v-model="categoryFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        data-testid="category-filter"
      >
        <option value="">Toutes catégories</option>
        <option value="conseils-face">Conseils Face</option>
        <option value="guide-producteur">Guide Producteur</option>
        <option value="actualites">Actualités</option>
      </select>

      <select
        v-model="statusFilter"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        data-testid="status-filter"
      >
        <option value="">Tous statuts</option>
        <option value="draft">Brouillon</option>
        <option value="published">Publié</option>
      </select>

      <button
        v-if="hasActiveFilters"
        class="inline-flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
        data-testid="clear-filters"
        @click="clearFilters"
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
      v-if="isLoading && !isTogglingId && !isDeleting"
      class="flex items-center justify-center py-12"
      data-testid="loading-state"
    >
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!hasArticles && !error"
      class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center"
      data-testid="empty-state"
    >
      <FileText class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-4 text-lg font-medium text-gray-900">Aucun article trouvé</h3>
      <p class="mt-2 text-sm text-gray-500">
        {{ hasActiveFilters ? 'Aucun résultat pour ces critères de recherche.' : 'Commencez par créer un article.' }}
      </p>
    </div>

    <!-- Articles Table -->
    <div
      v-else-if="hasArticles"
      class="overflow-x-auto rounded-xl border border-gray-200 bg-white"
      data-testid="articles-table"
    >
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Titre
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Catégorie
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Statut
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Auteur
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Date
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="a in articles"
            :key="a.id"
            class="hover:bg-gray-50 transition-colors"
            data-testid="article-row"
          >
            <td class="px-6 py-4">
              <p class="text-sm font-medium text-gray-900 line-clamp-1">{{ a.title }}</p>
              <p v-if="a.excerpt" class="mt-0.5 text-xs text-gray-500 line-clamp-1">{{ a.excerpt }}</p>
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="getCategoryColor(a.category.value)"
              >
                {{ a.category.label }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <button
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors cursor-pointer"
                :class="a.status.value === 'published' ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                :disabled="isTogglingId === a.id"
                data-testid="status-toggle"
                @click.stop="handleToggleStatus(a.id, a.status.value)"
              >
                <Loader2 v-if="isTogglingId === a.id" class="h-3 w-3 animate-spin mr-1" />
                {{ a.status.label }}
              </button>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ a.admin?.name ?? '—' }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ formatDate(a.published_at ?? a.created_at) }}
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <div class="flex items-center gap-2">
                <button
                  class="rounded-lg p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 transition-colors"
                  title="Modifier"
                  data-testid="edit-btn"
                  @click.stop="goToEdit(a.id)"
                >
                  <Pencil class="h-4 w-4" />
                </button>
                <button
                  class="rounded-lg p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                  title="Supprimer"
                  data-testid="delete-btn"
                  @click.stop="openDeleteModal(a.id, a.title)"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
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

    <!-- Delete Confirmation Modal -->
    <ConfirmModal
      :is-open="showDeleteModal"
      title="Supprimer l'article"
      :message="`Êtes-vous sûr de vouloir supprimer l'article « ${articleToDelete?.title ?? ''} » ? Cette action est irréversible.`"
      confirm-text="Supprimer"
      cancel-text="Annuler"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDeleteModal = false"
    />
  </div>
</template>
