<script setup lang="ts">
import { reactive, watch } from 'vue'
import type { CategoryNicheInfo, CategoryOption, NicheOption } from '../types'
import { Tag, Layers, Check } from 'lucide-vue-next'

const props = defineProps<{
  categoryNicheInfo: CategoryNicheInfo | null
  categoryOptions: CategoryOption[]
  nicheOptions: NicheOption[]
  isSaving: boolean
  error: string | null
}>()

const emit = defineEmits<{
  (e: 'save', data: { categories: string[] | null; niches: string[] | null }): void
}>()

const form = reactive({
  categories: [] as string[],
  niches: [] as string[],
})

// Watch for categoryNicheInfo changes and update form
watch(
  () => props.categoryNicheInfo,
  (info) => {
    if (info) {
      form.categories = info.categories.map((c) => c.value)
      form.niches = info.niches.map((n) => n.value)
    }
  },
  { immediate: true },
)

function toggleCategory(value: string) {
  const index = form.categories.indexOf(value)
  if (index >= 0) {
    form.categories.splice(index, 1)
  } else {
    form.categories.push(value)
  }
}

function toggleNiche(value: string) {
  const index = form.niches.indexOf(value)
  if (index >= 0) {
    form.niches.splice(index, 1)
  } else {
    form.niches.push(value)
  }
}

const handleSubmit = () => {
  emit('save', {
    categories: form.categories.length > 0 ? [...form.categories] : null,
    niches: form.niches.length > 0 ? [...form.niches] : null,
  })
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-5" novalidate>
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

    <!-- Categories Section -->
    <div data-testid="categories-section">
      <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
        <Tag class="h-4 w-4 text-teal-600" />
        Catégories
      </label>
      <div class="flex flex-wrap gap-2" data-testid="categories-chips">
        <button
          v-for="option in categoryOptions"
          :key="option.value"
          type="button"
          :data-testid="`category-chip-${option.value}`"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium border transition-all duration-150"
          :class="
            form.categories.includes(option.value)
              ? 'bg-teal-600 text-white border-teal-600 shadow-sm'
              : 'bg-white text-gray-600 border-gray-300 hover:border-teal-400 hover:text-teal-700'
          "
          @click="toggleCategory(option.value)"
        >
          <Check
            v-if="form.categories.includes(option.value)"
            class="h-3.5 w-3.5"
          />
          {{ option.label }}
        </button>
      </div>
    </div>

    <!-- Niches Section -->
    <div data-testid="niches-section">
      <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
        <Layers class="h-4 w-4 text-purple-600" />
        Niches
      </label>
      <div class="flex flex-wrap gap-2" data-testid="niches-chips">
        <button
          v-for="option in nicheOptions"
          :key="option.value"
          type="button"
          :data-testid="`niche-chip-${option.value}`"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium border transition-all duration-150"
          :class="
            form.niches.includes(option.value)
              ? 'bg-purple-600 text-white border-purple-600 shadow-sm'
              : 'bg-white text-gray-600 border-gray-300 hover:border-purple-400 hover:text-purple-700'
          "
          @click="toggleNiche(option.value)"
        >
          <Check
            v-if="form.niches.includes(option.value)"
            class="h-3.5 w-3.5"
          />
          {{ option.label }}
        </button>
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
