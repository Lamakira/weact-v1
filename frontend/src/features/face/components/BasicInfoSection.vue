<script setup lang="ts">
import { reactive, watch, onMounted } from 'vue'
import { useBasicInfo } from '../composables/useBasicInfo'
import type { BasicInfoFormData } from '../types'

const { basicInfo, isLoading, isSaving, error, fetchBasicInfo, updateBasicInfo, clearError } =
  useBasicInfo()

const form = reactive({
  nom: '',
  prenom: '',
  username: '',
})

const successMessage = reactive({
  show: false,
  text: '',
})

// Watch for basicInfo changes and update form
watch(
  () => basicInfo.value,
  (info) => {
    if (info) {
      form.nom = info.nom ?? ''
      form.prenom = info.prenom ?? ''
      form.username = info.username ?? ''
    }
  },
  { immediate: true },
)

onMounted(() => {
  fetchBasicInfo()
})

const handleSubmit = async () => {
  clearError()
  successMessage.show = false

  const data: BasicInfoFormData = {
    nom: form.nom,
    prenom: form.prenom,
    username: form.username,
  }

  const result = await updateBasicInfo(data)

  if (result.success) {
    successMessage.show = true
    successMessage.text = result.message || 'Informations mises à jour avec succès'

    // Hide success message after 3 seconds
    setTimeout(() => {
      successMessage.show = false
    }, 3000)
  }
}
</script>

<template>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-gray-100">
      <h2 class="text-lg font-semibold text-gray-900">Informations personnelles</h2>
      <p class="text-sm text-gray-500 mt-1">Modifiez votre nom et nom d'utilisateur</p>
    </div>

    <!-- Content -->
    <div class="p-6">
      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="flex items-center justify-center py-8"
        data-testid="loading-state"
      >
        <svg
          class="animate-spin h-8 w-8 text-teal-600"
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
      <form v-else @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Success Message -->
        <div
          v-if="successMessage.show"
          class="p-3 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2"
          role="status"
          data-testid="success-message"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5 text-green-500 flex-shrink-0"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clip-rule="evenodd"
            />
          </svg>
          <span class="text-sm text-green-700 font-medium">{{ successMessage.text }}</span>
        </div>

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

        <!-- Name Fields Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label for="prenom" class="text-sm font-medium text-gray-900">Prénom</label>
            <input
              id="prenom"
              type="text"
              v-model="form.prenom"
              required
              placeholder="Jean"
              class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition-colors"
              data-testid="prenom-input"
            />
          </div>

          <div class="space-y-1.5">
            <label for="nom" class="text-sm font-medium text-gray-900">Nom</label>
            <input
              id="nom"
              type="text"
              v-model="form.nom"
              required
              placeholder="Dupont"
              class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition-colors"
              data-testid="nom-input"
            />
          </div>
        </div>

        <!-- Username Field -->
        <div class="space-y-1.5">
          <label for="username" class="text-sm font-medium text-gray-900">Nom d'utilisateur</label>
          <div class="relative">
            <span
              class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 pointer-events-none"
            >
              @
            </span>
            <input
              id="username"
              type="text"
              v-model="form.username"
              required
              placeholder="jeandupont"
              class="w-full pl-8 pr-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition-colors"
              data-testid="username-input"
            />
          </div>
          <p class="text-xs text-gray-500">
            Votre identifiant unique sur la plateforme. Maximum 50 caractères.
          </p>
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
    </div>
  </div>
</template>
