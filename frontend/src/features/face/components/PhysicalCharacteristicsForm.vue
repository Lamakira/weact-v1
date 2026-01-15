<script setup lang="ts">
import { reactive, watch } from 'vue'
import type { PhysicalCharacteristicsInfo } from '../types'

const props = defineProps<{
  physicalCharacteristicsInfo: PhysicalCharacteristicsInfo | null
  isSaving: boolean
  error: string | null
}>()

const emit = defineEmits<{
  (e: 'save', data: { taille: number | null; poids: number | null }): void
}>()

const form = reactive({
  taille: '' as string | number,
  poids: '' as string | number,
})

// Watch for physicalCharacteristicsInfo changes and update form
watch(
  () => props.physicalCharacteristicsInfo,
  (info) => {
    if (info) {
      form.taille = info.taille ?? ''
      form.poids = info.poids ?? ''
    }
  },
  { immediate: true },
)

const handleSubmit = () => {
  emit('save', {
    taille: form.taille === '' ? null : Number(form.taille),
    poids: form.poids === '' ? null : Number(form.poids),
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

    <!-- Physical Characteristics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="space-y-1.5">
        <label for="taille" class="text-sm font-medium text-gray-900">Taille (cm)</label>
        <input
          id="taille"
          type="number"
          v-model="form.taille"
          min="50"
          max="300"
          step="1"
          placeholder="175"
          class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition-colors"
          data-testid="taille-input"
        />
      </div>

      <div class="space-y-1.5">
        <label for="poids" class="text-sm font-medium text-gray-900">Poids (kg)</label>
        <input
          id="poids"
          type="number"
          v-model="form.poids"
          min="20"
          max="500"
          step="1"
          placeholder="70"
          class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition-colors"
          data-testid="poids-input"
        />
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
