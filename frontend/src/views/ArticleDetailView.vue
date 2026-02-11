<script setup lang="ts">
import { ArrowLeft, AlertCircle, FileText, Loader2 } from 'lucide-vue-next'
import { useArticleDetail } from '@/features/public/composables/useArticleDetail'
import DOMPurify from 'dompurify'
import { computed } from 'vue'

const { article, isLoading, error, notFound } = useArticleDetail()

const sanitizedContent = computed((): string => {
  if (!article.value?.content) return ''
  return DOMPurify.sanitize(article.value.content)
})

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
  <div class="py-8" data-testid="article-detail-view">
    <!-- Back link -->
    <RouterLink
      :to="{ name: 'ressources-list' }"
      class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-[#198496] transition-colors mb-6"
    >
      <ArrowLeft class="h-4 w-4" />
      Retour aux articles
    </RouterLink>

    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center py-20">
      <Loader2 class="h-8 w-8 text-[#198496] animate-spin" />
    </div>

    <!-- Not Found -->
    <div
      v-else-if="notFound"
      class="text-center py-20"
      data-testid="article-not-found"
    >
      <FileText class="mx-auto h-12 w-12 text-gray-300 mb-4" />
      <h2 class="text-xl font-semibold text-gray-900 mb-2">Article non trouvé</h2>
      <p class="text-gray-600 mb-6">
        Cet article n'existe pas ou a été retiré.
      </p>
      <RouterLink
        :to="{ name: 'ressources-list' }"
        class="inline-flex items-center gap-2 rounded-lg bg-[#198496] px-5 py-2.5 text-sm font-medium text-white hover:bg-[#146d7d] transition-colors"
      >
        Voir tous les articles
      </RouterLink>
    </div>

    <!-- Error -->
    <div
      v-else-if="error && !notFound"
      class="text-center py-20"
      data-testid="article-error"
    >
      <AlertCircle class="mx-auto h-12 w-12 text-red-400 mb-4" />
      <h2 class="text-xl font-semibold text-gray-900 mb-2">Erreur de chargement</h2>
      <p class="text-gray-600">{{ error }}</p>
    </div>

    <!-- Article Content -->
    <article v-else-if="article" class="max-w-3xl mx-auto" data-testid="article-content">
      <!-- Featured Image -->
      <div v-if="article.featured_image" class="rounded-xl overflow-hidden mb-8">
        <img
          :src="article.featured_image"
          :alt="article.title"
          class="w-full rounded-xl"
        />
      </div>

      <!-- Meta -->
      <div class="flex items-center gap-3 mb-4">
        <span
          class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
          :class="getCategoryColor(article.category.value)"
        >
          {{ article.category.label }}
        </span>
        <time
          :datetime="article.published_at"
          class="text-sm text-gray-500"
        >
          {{ formatDate(article.published_at) }}
        </time>
      </div>

      <!-- Title -->
      <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
        {{ article.title }}
      </h1>

      <!-- Author -->
      <p v-if="article.author_name" class="text-sm text-gray-600 mb-8">
        Par {{ article.author_name }}
      </p>

      <!-- Content (sanitized HTML) -->
      <div
        class="prose prose-lg max-w-none prose-headings:text-gray-900 prose-p:text-gray-700 prose-a:text-[#198496] prose-img:rounded-lg"
        v-html="sanitizedContent"
      />
    </article>
  </div>
</template>
