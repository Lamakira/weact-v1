<script setup lang="ts">
import { reactive, watch, onMounted } from 'vue'
import { useBasicInfo } from '../composables/useBasicInfo'
import type { BasicInfoFormData } from '../types'
import { FloatingField } from '@/components/ui/form'
import { useToast } from '@/composables/useToast'
import { User, AtSign } from 'lucide-vue-next'

// `flat` drops the component's own card chrome + internal padding so it can be
// embedded inside a host card (e.g. the profile tabs master/detail panel) without
// nesting a card inside a card. Default keeps the standalone card (unchanged).
withDefaults(defineProps<{ flat?: boolean }>(), { flat: false })

const { basicInfo, isLoading, isSaving, error, fetchBasicInfo, updateBasicInfo, clearError } =
  useBasicInfo()

const form = reactive({
  nom: '',
  prenom: '',
  username: '',
})

const toast = useToast()

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

  const data: BasicInfoFormData = {
    nom: form.nom,
    prenom: form.prenom,
    username: form.username,
  }

  const result = await updateBasicInfo(data)

  if (result.success) {
    toast.success(result.message || 'Informations mises à jour avec succès')
  }
}
</script>

<template>
  <div :class="flat ? '' : 'bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden'">
    <!-- Header -->
    <div :class="flat ? 'mb-6' : 'px-6 py-4 border-b border-gray-100'">
      <h2 class="text-lg font-semibold text-gray-900">Informations personnelles</h2>
      <p class="text-sm text-gray-500 mt-1">Modifiez votre nom et nom d'utilisateur</p>
    </div>

    <!-- Content -->
    <div :class="flat ? '' : 'p-6'">
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
          <FloatingField
            id="prenom"
            v-model="form.prenom"
            label="Prénom"
            :icon="User"
            required
            data-testid="prenom-input"
          />
          <FloatingField
            id="nom"
            v-model="form.nom"
            label="Nom"
            :icon="User"
            required
            data-testid="nom-input"
          />
        </div>

        <!-- Username Field -->
        <FloatingField
          id="username"
          v-model="form.username"
          label="Nom d'utilisateur"
          :icon="AtSign"
          required
          data-testid="username-input"
        />
        <p class="text-xs text-gray-500 -mt-2">
          Votre identifiant unique sur la plateforme. Maximum 50 caractères.
        </p>

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
