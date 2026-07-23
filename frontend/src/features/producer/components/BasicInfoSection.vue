<script setup lang="ts">
import { reactive, watch, onMounted, computed } from 'vue'
import { useProducerBasicInfo } from '../composables/useProducerBasicInfo'
import type { ProducerBasicInfoFormData } from '../types'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const { basicInfo, isLoading, isSaving, error, fetchBasicInfo, updateBasicInfo, clearError } =
  useProducerBasicInfo()

const form = reactive({
  // Agency fields
  agency_name: '',
  // Particulier fields
  first_name: '',
  last_name: '',
})

// Computed to check if this is an agency type
const isAgency = computed(() => basicInfo.value?.type === 'agency')

// Watch for basicInfo changes and update form
watch(
  () => basicInfo.value,
  (info) => {
    if (info) {
      if (info.type === 'agency') {
        form.agency_name = info.agency_name ?? ''
      } else {
        form.first_name = info.first_name ?? ''
        form.last_name = info.last_name ?? ''
      }
    }
  },
  { immediate: true },
)

onMounted(() => {
  fetchBasicInfo()
})

const handleSubmit = async () => {
  clearError()

  let data: ProducerBasicInfoFormData

  if (isAgency.value) {
    data = {
      agency_name: form.agency_name,
    }
  } else {
    data = {
      first_name: form.first_name,
      last_name: form.last_name,
    }
  }

  const result = await updateBasicInfo(data)

  if (result.success) {
    toast.success(result.message || 'Informations mises à jour avec succès')
  }
}
</script>

<template>
  <div data-testid="basic-info-editor">
    <h2 class="text-base font-semibold text-slate-800 mb-1">
      {{ isAgency ? "Nom de l'agence" : 'Votre nom' }}
    </h2>
    <p class="text-sm text-slate-500 mb-3">
      {{ isAgency ? "Modifiez le nom de votre agence" : 'Modifiez votre nom' }}
    </p>

    <!-- Loading State -->
      <div
        v-if="isLoading"
        class="flex items-center justify-center py-8"
        data-testid="loading-state"
      >
        <svg
          class="animate-spin h-8 w-8 text-weact-500"
          xmlns="http://www.w3.org/2000/svg"
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
      </div>

      <!-- Form -->
      <form v-else @submit.prevent="handleSubmit" class="space-y-3">
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

        <!-- Agency Name Field (for agencies) -->
        <div v-if="isAgency" class="space-y-1.5">
          <label for="agency_name" class="text-sm font-medium text-gray-900">Nom de l'agence</label>
          <input
            id="agency_name"
            type="text"
            v-model="form.agency_name"
            required
            placeholder="Production ABC"
            class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-weact-500 focus:border-weact-500 transition-colors"
            data-testid="agency-name-input"
          />
        </div>

        <!-- Name Fields (for particuliers) -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label for="first_name" class="text-sm font-medium text-gray-900">Prénom</label>
            <input
              id="first_name"
              type="text"
              v-model="form.first_name"
              required
              placeholder="Marie"
              class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-weact-500 focus:border-weact-500 transition-colors"
              data-testid="first-name-input"
            />
          </div>

          <div class="space-y-1.5">
            <label for="last_name" class="text-sm font-medium text-gray-900">Nom</label>
            <input
              id="last_name"
              type="text"
              v-model="form.last_name"
              required
              placeholder="Martin"
              class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-weact-500 focus:border-weact-500 transition-colors"
              data-testid="last-name-input"
            />
          </div>
        </div>

        <!-- Action Button -->
        <div class="pt-2">
          <button
            type="submit"
            :disabled="isSaving"
            class="inline-flex items-center justify-center px-4 py-2 bg-weact-600 text-white text-sm font-medium rounded-lg hover:bg-weact-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-weact-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
  </div>
</template>

<style scoped>
.bg-weact-600 {
  background-color: var(--color-weact, #198496);
}
.bg-weact-700:hover {
  background-color: #147585;
}
.text-weact-500 {
  color: var(--color-weact, #198496);
}
.focus\:ring-weact-500:focus {
  --tw-ring-color: var(--color-weact, #198496);
}
.focus\:border-weact-500:focus {
  border-color: var(--color-weact, #198496);
}
</style>
