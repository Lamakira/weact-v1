<script setup lang="ts">
import { reactive, watch } from 'vue'
import type { CategoryNicheInfo, CategoryOption, NicheOption, FaceCategory, FaceNiche } from '../types'

const props = defineProps<{
  categoryNicheInfo: CategoryNicheInfo | null
  categoryOptions: CategoryOption[]
  nicheOptions: NicheOption[]
  isSaving: boolean
  error: string | null
}>()

const emit = defineEmits<{
  (e: 'save', data: { categorie: FaceCategory | null; niche: FaceNiche | null }): void
}>()

const form = reactive({
  categorie: '' as string,
  niche: '' as string,
})

// Watch for categoryNicheInfo changes and update form
watch(
  () => props.categoryNicheInfo,
  (info) => {
    if (info) {
      form.categorie = info.categorie ?? ''
      form.niche = info.niche ?? ''
    }
  },
  { immediate: true },
)

const handleSubmit = () => {
  emit('save', {
    categorie: form.categorie === '' ? null : (form.categorie as FaceCategory),
    niche: form.niche === '' ? null : (form.niche as FaceNiche),
  })
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4" novalidate>
    <!-- Error State -->
    <div
      v-if="error"
      class="p-3 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2"
      role="alert"
      data-testid="error-message"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-5 w-5 text-red-500 flex-shrink-0"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
          clip-rule="evenodd"
        />
      </svg>
      <span class="text-sm text-red-700 font-medium">{{ error }}</span>
    </div>

    <!-- Current Selection Badges -->
    <div
      v-if="categoryNicheInfo?.categorie_label || categoryNicheInfo?.niche_label"
      class="flex flex-wrap gap-2"
      data-testid="current-selection"
    >
      <span
        v-if="categoryNicheInfo?.categorie_label"
        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-teal-100 text-teal-800"
        data-testid="category-badge"
      >
        {{ categoryNicheInfo.categorie_label }}
      </span>
      <span
        v-if="categoryNicheInfo?.niche_label"
        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800"
        data-testid="niche-badge"
      >
        {{ categoryNicheInfo.niche_label }}
      </span>
    </div>

    <!-- Category and Niche Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="space-y-1.5">
        <label for="categorie" class="text-sm font-medium text-gray-900">Catégorie</label>
        <select
          id="categorie"
          v-model="form.categorie"
          class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition-colors"
          data-testid="categorie-select"
        >
          <option value="">Sélectionnez une catégorie</option>
          <option
            v-for="option in categoryOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </div>

      <div class="space-y-1.5">
        <label for="niche" class="text-sm font-medium text-gray-900">Niche</label>
        <select
          id="niche"
          v-model="form.niche"
          class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition-colors"
          data-testid="niche-select"
        >
          <option value="">Sélectionnez une niche</option>
          <option
            v-for="option in nicheOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- Action Button -->
    <div class="pt-4 flex justify-end">
      <button
        type="submit"
        :disabled="isSaving"
        class="inline-flex items-center justify-center px-6 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm"
        data-testid="save-button"
      >
        <svg
          v-if="isSaving"
          class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          ></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
        {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
      </button>
    </div>
  </form>
</template>
