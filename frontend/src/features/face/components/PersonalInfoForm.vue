<script setup lang="ts">
import { reactive, watch } from 'vue'
import type { PersonalInfoInfo } from '../types'
import { FloatingField, FloatingSelect } from '@/components/ui/form'
import { Users, Calendar, Globe, MapPin } from 'lucide-vue-next'

const props = defineProps<{
  personalInfo: PersonalInfoInfo | null
  isSaving: boolean
  error: string | null
}>()

const emit = defineEmits<{
  (e: 'save', data: { sexe: string | null; date_naissance: string | null; nationalite: string | null; pays: string | null }): void
}>()

const sexeOptions = [
  { value: 'homme', label: 'Homme' },
  { value: 'femme', label: 'Femme' },
  { value: 'autre', label: 'Autre' },
]

const form = reactive({
  sexe: '',
  date_naissance: '',
  nationalite: '',
  pays: '',
})

// Watch for personalInfo changes and update form
watch(
  () => props.personalInfo,
  (info) => {
    if (info) {
      form.sexe = info.sexe ?? ''
      form.date_naissance = info.date_naissance ?? ''
      form.nationalite = info.nationalite ?? ''
      form.pays = info.pays ?? ''
    }
  },
  { immediate: true },
)

const handleSubmit = () => {
  emit('save', {
    sexe: form.sexe || null,
    date_naissance: form.date_naissance || null,
    nationalite: form.nationalite || null,
    pays: form.pays || null,
  })
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
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

    <!-- Sexe + Date de naissance -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <FloatingSelect
        id="personal-sexe"
        v-model="form.sexe"
        label="Sexe"
        :icon="Users"
        :options="sexeOptions"
        data-testid="sexe-select"
      />
      <FloatingField
        id="personal-date-naissance"
        v-model="form.date_naissance"
        type="date"
        label="Date de naissance"
        :icon="Calendar"
        data-testid="date-naissance-input"
      />
    </div>

    <!-- Nationalite + Pays -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <FloatingField
        id="personal-nationalite"
        v-model="form.nationalite"
        label="Nationalite"
        :icon="Globe"
        data-testid="nationalite-input"
      />
      <FloatingField
        id="personal-pays"
        v-model="form.pays"
        label="Pays"
        :icon="MapPin"
        data-testid="pays-input"
      />
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
