<script setup lang="ts">
import { reactive, watch, computed, ref } from 'vue'
import type { Experience, ExperienceFormData } from '../types'

const props = withDefaults(
  defineProps<{
    experience?: Experience | null
    isSaving: boolean
    error: string | null
    validationErrors: Record<string, string[]>
    showCancel?: boolean
  }>(),
  {
    showCancel: false,
  },
)

const emit = defineEmits<{
  (e: 'submit', data: ExperienceFormData): void
  (e: 'cancel'): void
}>()

// Get today's date in YYYY-MM-DD format for max date
const today = new Date().toISOString().split('T')[0]

// Store saved date_fin for restoration when unchecking "En cours"
const savedDateFin = ref<string | null>(null)

// Client-side validation error for date_fin
const dateFinClientError = ref<string | null>(null)

const form = reactive({
  titre: '',
  description: '',
  date_debut: '',
  date_fin: '' as string | null,
  is_ongoing: false,
})

// Watch for experience changes and update form (for edit mode)
watch(
  () => props.experience,
  (exp) => {
    if (exp) {
      form.titre = exp.titre
      form.description = exp.description ?? ''
      form.date_debut = exp.date_debut
      form.date_fin = exp.date_fin
      form.is_ongoing = exp.is_ongoing
      savedDateFin.value = exp.date_fin
    } else {
      // Reset form for add mode
      form.titre = ''
      form.description = ''
      form.date_debut = ''
      form.date_fin = ''
      form.is_ongoing = false
      savedDateFin.value = null
    }
    dateFinClientError.value = null
  },
  { immediate: true },
)

// When "En cours" is toggled, save/restore date_fin
watch(
  () => form.is_ongoing,
  (isOngoing, wasOngoing) => {
    if (isOngoing) {
      // Save current date_fin before clearing
      if (form.date_fin) {
        savedDateFin.value = form.date_fin
      }
      form.date_fin = null
      dateFinClientError.value = null
    } else if (!isOngoing && wasOngoing && savedDateFin.value) {
      // Restore saved date_fin when unchecking
      form.date_fin = savedDateFin.value
    }
  },
)

const isEditMode = computed(() => !!props.experience)

const handleSubmit = () => {
  // Clear previous client-side error
  dateFinClientError.value = null

  // Client-side validation: ensure date_fin >= date_debut
  if (!form.is_ongoing && form.date_fin && form.date_debut) {
    if (form.date_fin < form.date_debut) {
      dateFinClientError.value = 'La date de fin doit être après la date de début'
      return
    }
  }

  emit('submit', {
    titre: form.titre,
    description: form.description || null,
    date_debut: form.date_debut,
    date_fin: form.is_ongoing ? null : form.date_fin || null,
  })
}

const handleCancel = () => {
  emit('cancel')
}

const getFieldError = (field: string): string | null => {
  // Check client-side error first for date_fin
  if (field === 'date_fin' && dateFinClientError.value) {
    return dateFinClientError.value
  }
  const errors = props.validationErrors[field]
  return errors && errors.length > 0 ? (errors[0] ?? null) : null
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4" novalidate data-testid="experience-form">
    <!-- Global Error State -->
    <div
      v-if="error && !Object.keys(validationErrors).length"
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

    <!-- Title Field -->
    <div class="space-y-1.5">
      <label for="titre" class="text-sm font-medium text-gray-900">
        Titre de l'expérience <span class="text-red-500">*</span>
      </label>
      <input
        id="titre"
        v-model="form.titre"
        type="text"
        maxlength="150"
        required
        class="w-full px-3 py-2 text-sm rounded-lg border shadow-sm transition-colors focus:ring-2 focus:ring-teal-600 focus:border-teal-600"
        :class="getFieldError('titre') ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300'"
        :aria-describedby="getFieldError('titre') ? 'titre-error' : undefined"
        :aria-invalid="!!getFieldError('titre')"
        placeholder="Ex: Publicité Coca-Cola"
        data-testid="titre-input"
      />
      <p
        v-if="getFieldError('titre')"
        id="titre-error"
        class="text-sm text-red-600"
        data-testid="titre-error"
      >
        {{ getFieldError('titre') }}
      </p>
    </div>

    <!-- Date Fields Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <!-- Start Date Field -->
      <div class="space-y-1.5">
        <label for="date_debut" class="text-sm font-medium text-gray-900">
          Date de début <span class="text-red-500">*</span>
        </label>
        <input
          id="date_debut"
          v-model="form.date_debut"
          type="date"
          :max="today"
          required
          class="w-full px-3 py-2 text-sm rounded-lg border shadow-sm transition-colors focus:ring-2 focus:ring-teal-600 focus:border-teal-600"
          :class="getFieldError('date_debut') ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300'"
          :aria-describedby="getFieldError('date_debut') ? 'date_debut-error' : undefined"
          :aria-invalid="!!getFieldError('date_debut')"
          data-testid="date_debut-input"
        />
        <p
          v-if="getFieldError('date_debut')"
          id="date_debut-error"
          class="text-sm text-red-600"
          data-testid="date_debut-error"
        >
          {{ getFieldError('date_debut') }}
        </p>
      </div>

      <!-- End Date Field -->
      <div class="space-y-1.5">
        <label for="date_fin" class="text-sm font-medium text-gray-900">
          Date de fin <span class="text-gray-400">(optionnel)</span>
        </label>
        <input
          id="date_fin"
          v-model="form.date_fin"
          type="date"
          :max="today"
          :min="form.date_debut"
          :disabled="form.is_ongoing"
          class="w-full px-3 py-2 text-sm rounded-lg border shadow-sm transition-colors focus:ring-2 focus:ring-teal-600 focus:border-teal-600 disabled:bg-gray-100 disabled:cursor-not-allowed"
          :class="getFieldError('date_fin') ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300'"
          :aria-describedby="getFieldError('date_fin') ? 'date_fin-error' : undefined"
          :aria-invalid="!!getFieldError('date_fin')"
          data-testid="date_fin-input"
        />
        <p
          v-if="getFieldError('date_fin')"
          id="date_fin-error"
          class="text-sm text-red-600"
          data-testid="date_fin-error"
        >
          {{ getFieldError('date_fin') }}
        </p>
      </div>
    </div>

    <!-- Ongoing Checkbox -->
    <div class="flex items-center gap-2">
      <input
        id="is_ongoing"
        v-model="form.is_ongoing"
        type="checkbox"
        class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-600"
        data-testid="is_ongoing-checkbox"
      />
      <label for="is_ongoing" class="text-sm text-gray-700">
        En cours
      </label>
    </div>

    <!-- Description Field -->
    <div class="space-y-1.5">
      <label for="description" class="text-sm font-medium text-gray-900">
        Description <span class="text-gray-400">(optionnel)</span>
      </label>
      <textarea
        id="description"
        v-model="form.description"
        rows="3"
        maxlength="500"
        class="w-full px-3 py-2 text-sm rounded-lg border shadow-sm transition-colors focus:ring-2 focus:ring-teal-600 focus:border-teal-600 resize-none"
        :class="getFieldError('description') ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300'"
        :aria-describedby="getFieldError('description') ? 'description-error' : undefined"
        :aria-invalid="!!getFieldError('description')"
        placeholder="Décrivez brièvement cette expérience..."
        data-testid="description-input"
      ></textarea>
      <div class="flex justify-between items-center">
        <p
          v-if="getFieldError('description')"
          id="description-error"
          class="text-sm text-red-600"
          data-testid="description-error"
        >
          {{ getFieldError('description') }}
        </p>
        <span class="text-xs text-gray-400 ml-auto">{{ form.description.length }}/500</span>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="pt-2 flex justify-end gap-3">
      <button
        v-if="isEditMode || showCancel"
        type="button"
        @click="handleCancel"
        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors"
        data-testid="cancel-button"
      >
        Annuler
      </button>
      <button
        type="submit"
        :disabled="isSaving"
        class="inline-flex items-center justify-center px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm"
        data-testid="submit-button"
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
        {{ isSaving ? 'Enregistrement...' : isEditMode ? 'Mettre à jour' : 'Ajouter' }}
      </button>
    </div>
  </form>
</template>
