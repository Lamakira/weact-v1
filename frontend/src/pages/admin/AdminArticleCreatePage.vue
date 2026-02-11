<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeft, Loader2, AlertCircle, ImagePlus } from 'lucide-vue-next'
import { useAdminArticles } from '@/features/admin/composables/useAdminArticles'

const router = useRouter()
const { isLoading, createArticle } = useAdminArticles()

const title = ref('')
const content = ref('')
const excerpt = ref('')
const category = ref('')
const status = ref('draft')
const featuredImage = ref<File | null>(null)
const imagePreview = ref<string | null>(null)

const formErrors = ref<Record<string, string[]>>({})
const errorMessage = ref('')

function handleImageChange(event: Event): void {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    featuredImage.value = file
    imagePreview.value = URL.createObjectURL(file)
  }
}

function removeImage(): void {
  featuredImage.value = null
  if (imagePreview.value) {
    URL.revokeObjectURL(imagePreview.value)
    imagePreview.value = null
  }
}

async function handleSubmit(): Promise<void> {
  formErrors.value = {}
  errorMessage.value = ''

  const result = await createArticle({
    title: title.value,
    content: content.value,
    excerpt: excerpt.value || undefined,
    category: category.value,
    status: status.value,
    featured_image: featuredImage.value ?? undefined,
  })

  if (result.success) {
    router.push({ name: 'admin-articles-list', query: { success: 'created' } })
  } else {
    formErrors.value = result.errors ?? {}
    errorMessage.value = result.message ?? 'Une erreur est survenue'
  }
}

function goBack(): void {
  router.push({ name: 'admin-articles-list' })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center gap-4">
      <button
        class="rounded-lg p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
        @click="goBack"
      >
        <ArrowLeft class="h-5 w-5" />
      </button>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Créer un article</h1>
        <p class="mt-1 text-sm text-gray-500">Rédigez un nouvel article pour le blog</p>
      </div>
    </div>

    <!-- Error Message -->
    <div
      v-if="errorMessage"
      class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3"
      role="alert"
    >
      <AlertCircle class="h-5 w-5 text-red-500 mt-0.5 shrink-0" />
      <p class="text-sm text-red-700">{{ errorMessage }}</p>
    </div>

    <!-- Form -->
    <form class="space-y-6 max-w-3xl" @submit.prevent="handleSubmit">
      <!-- Title -->
      <div>
        <label for="title" class="block text-sm font-medium text-gray-700">Titre *</label>
        <input
          id="title"
          v-model="title"
          type="text"
          required
          class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          :class="{ 'border-red-300': formErrors.title }"
          placeholder="Titre de l'article"
          data-testid="title-input"
        />
        <p v-if="formErrors.title" class="mt-1 text-xs text-red-600">{{ formErrors.title[0] }}</p>
      </div>

      <!-- Category & Status -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="category" class="block text-sm font-medium text-gray-700">Catégorie *</label>
          <select
            id="category"
            v-model="category"
            required
            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            :class="{ 'border-red-300': formErrors.category }"
            data-testid="category-select"
          >
            <option value="" disabled>Choisir une catégorie</option>
            <option value="conseils-face">Conseils Face</option>
            <option value="guide-producteur">Guide Producteur</option>
            <option value="actualites">Actualités</option>
          </select>
          <p v-if="formErrors.category" class="mt-1 text-xs text-red-600">{{ formErrors.category[0] }}</p>
        </div>

        <div>
          <label for="status" class="block text-sm font-medium text-gray-700">Statut</label>
          <select
            id="status"
            v-model="status"
            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            data-testid="status-select"
          >
            <option value="draft">Brouillon</option>
            <option value="published">Publié</option>
          </select>
        </div>
      </div>

      <!-- Excerpt -->
      <div>
        <label for="excerpt" class="block text-sm font-medium text-gray-700">Extrait</label>
        <textarea
          id="excerpt"
          v-model="excerpt"
          rows="2"
          class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          :class="{ 'border-red-300': formErrors.excerpt }"
          placeholder="Résumé court de l'article (optionnel)"
          data-testid="excerpt-input"
        />
        <p v-if="formErrors.excerpt" class="mt-1 text-xs text-red-600">{{ formErrors.excerpt[0] }}</p>
      </div>

      <!-- Content -->
      <div>
        <label for="content" class="block text-sm font-medium text-gray-700">Contenu *</label>
        <textarea
          id="content"
          v-model="content"
          rows="12"
          required
          class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          :class="{ 'border-red-300': formErrors.content }"
          placeholder="Contenu de l'article (HTML supporté)..."
          data-testid="content-input"
        />
        <p v-if="formErrors.content" class="mt-1 text-xs text-red-600">{{ formErrors.content[0] }}</p>
      </div>

      <!-- Featured Image -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Image à la une</label>
        <div v-if="imagePreview" class="mt-2 relative inline-block">
          <img :src="imagePreview" alt="Preview" class="h-32 w-auto rounded-lg object-cover" />
          <button
            type="button"
            class="absolute -top-2 -right-2 rounded-full bg-red-500 p-1 text-white hover:bg-red-600 transition-colors"
            @click="removeImage"
          >
            <span class="sr-only">Supprimer l'image</span>
            &times;
          </button>
        </div>
        <label
          v-else
          class="mt-2 flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 hover:border-primary-400 hover:text-primary-600 transition-colors"
        >
          <ImagePlus class="h-5 w-5" />
          <span>Choisir une image (JPEG, PNG, max 2Mo)</span>
          <input
            type="file"
            accept="image/jpeg,image/png"
            class="hidden"
            data-testid="image-input"
            @change="handleImageChange"
          />
        </label>
        <p v-if="formErrors.featured_image" class="mt-1 text-xs text-red-600">{{ formErrors.featured_image[0] }}</p>
      </div>

      <!-- Submit -->
      <div class="flex items-center gap-3 pt-4 border-t border-gray-200">
        <button
          type="submit"
          :disabled="isLoading"
          class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          data-testid="submit-btn"
        >
          <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin" />
          Créer l'article
        </button>
        <button
          type="button"
          class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          @click="goBack"
        >
          Annuler
        </button>
      </div>
    </form>
  </div>
</template>
