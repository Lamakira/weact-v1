<script setup lang="ts">
import { FileText, AlertCircle, RefreshCw } from 'lucide-vue-next'
import { usePaginatedArticles } from '@/features/public/composables/usePaginatedArticles'

// This name must match App.vue's public-branch <keep-alive :include> so this
// listing is cached across a Group A browse-and-return to an article detail. (That
// branch stays name-based because its <Transition> can't host a meta-driven v-if
// without unmounting the keep-alive — see App.vue. The dashboard layouts use meta.)
defineOptions({ name: 'RessourcesView' })
import { Pagination } from '@/components/ui/pagination'
import { Skeleton } from '@/components/ui/skeleton'

const {
  articles,
  isLoading,
  error,
  isEmpty,
  currentPage,
  currentCategory,
  totalPages,
  totalArticles,
  loadPage,
  filterByCategory,
  retry,
} = usePaginatedArticles(15)

const categories = [
  { value: '', label: 'Tous' },
  { value: 'conseils-face', label: 'Conseils Face' },
  { value: 'guide-producteur', label: 'Guide Producteur' },
  { value: 'actualites', label: 'Actualités' },
]

function handlePageChange(page: number): void {
  loadPage(page, currentCategory.value)
}

function handleCategoryChange(category: string): void {
  filterByCategory(category || undefined)
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
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
</script>

<template>
  <div data-testid="ressources-view">
    <!-- Page Header -->
    <header class="mb-8 text-center">
      <p class="text-gray-600 text-lg max-w-2xl mx-auto">
        Conseils, guides et actualités pour les talents et producteurs du Bénin.
      </p>
    </header>

    <!-- Category Filters -->
    <div class="flex justify-center gap-2 mb-8" data-testid="category-filters">
      <button
        v-for="cat in categories"
        :key="cat.value"
        class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
        :class="(currentCategory ?? '') === cat.value
          ? 'bg-[#198496] text-white'
          : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
        @click="handleCategoryChange(cat.value)"
      >
        {{ cat.label }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="space-y-8" data-testid="articles-loading">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="i in 6"
          :key="`skeleton-${i}`"
          class="rounded-xl border border-gray-100 bg-white overflow-hidden"
        >
          <Skeleton class="h-48 w-full" />
          <div class="p-5">
            <Skeleton class="h-4 w-20 mb-3 rounded-full" />
            <Skeleton class="h-6 w-3/4 mb-3" />
            <Skeleton class="h-12 w-full mb-4" />
            <Skeleton class="h-4 w-32" />
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="text-center py-16"
      data-testid="articles-error"
    >
      <AlertCircle class="mx-auto h-12 w-12 text-red-400 mb-4" />
      <h2 class="text-xl font-semibold text-gray-900 mb-2">Erreur de chargement</h2>
      <p class="text-gray-600 mb-6">{{ error }}</p>
      <button
        class="inline-flex items-center gap-2 rounded-lg bg-[#198496] px-5 py-2.5 text-sm font-medium text-white hover:bg-[#146d7d] transition-colors"
        @click="retry"
      >
        <RefreshCw class="h-4 w-4" />
        Réessayer
      </button>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="isEmpty"
      class="text-center py-16"
      data-testid="articles-empty"
    >
      <FileText class="mx-auto h-12 w-12 text-gray-300 mb-4" />
      <h2 class="text-xl font-semibold text-gray-900 mb-2">Aucun article</h2>
      <p class="text-gray-600">
        {{ currentCategory ? 'Aucun article dans cette catégorie pour le moment.' : 'Les articles arrivent bientôt !' }}
      </p>
    </div>

    <!-- Articles Grid -->
    <template v-else>
      <!-- Results count -->
      <p class="text-sm text-gray-500 mb-4">
        {{ totalArticles }} article{{ totalArticles > 1 ? 's' : '' }}
      </p>

      <div
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
        data-testid="articles-grid"
      >
        <RouterLink
          v-for="article in articles"
          :key="article.id"
          :to="{ name: 'ressources-detail', params: { slug: article.slug } }"
          class="group rounded-xl border border-gray-100 bg-white overflow-hidden hover:shadow-md transition-shadow"
          data-testid="article-card"
        >
          <!-- Featured Image -->
          <div class="aspect-video bg-gray-100 overflow-hidden">
            <img
              v-if="article.featured_image"
              :src="article.featured_image"
              :alt="article.title"
              class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="h-full w-full flex items-center justify-center">
              <FileText class="h-10 w-10 text-gray-300" />
            </div>
          </div>

          <!-- Content -->
          <div class="p-5">
            <span
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium mb-3"
              :class="getCategoryColor(article.category.value)"
            >
              {{ article.category.label }}
            </span>

            <h2 class="text-lg font-semibold text-gray-900 line-clamp-2 mb-2 group-hover:text-[#198496] transition-colors">
              {{ article.title }}
            </h2>

            <p v-if="article.excerpt" class="text-sm text-gray-600 line-clamp-3 mb-4">
              {{ article.excerpt }}
            </p>

            <div class="flex items-center gap-2 text-xs text-gray-500">
              <span v-if="article.author_name">{{ article.author_name }}</span>
              <span v-if="article.author_name">&middot;</span>
              <time :datetime="article.published_at">{{ formatDate(article.published_at) }}</time>
            </div>
          </div>
        </RouterLink>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="mt-8 flex justify-center">
        <Pagination
          :current-page="currentPage"
          :total-pages="totalPages"
          @page-change="handlePageChange"
        />
      </div>
    </template>
  </div>
</template>
