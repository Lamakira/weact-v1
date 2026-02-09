<script setup lang="ts">
import { reactive, watch, computed, ref } from 'vue'
import type { Experience, ExperienceFormData } from '../types'
import { FloatingField, FloatingTextarea } from '@/components/ui/form'
import { Type, Calendar, AlignLeft } from 'lucide-vue-next'

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

const dateFinValue = computed({
  get: () => form.date_fin ?? '',
  set: (val: string) => { form.date_fin = val || null },
})

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
    <FloatingField
      id="titre"
      v-model="form.titre"
      label="Titre de l'expérience"
      :icon="Type"
      :error="getFieldError('titre') ?? undefined"
      required
      maxlength="150"
      data-testid="titre-input"
    />

    <!-- Date Fields Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <!-- Start Date Field -->
      <FloatingField
        id="date_debut"
        v-model="form.date_debut"
        type="date"
        label="Date de début"
        :icon="Calendar"
        :error="getFieldError('date_debut') ?? undefined"
        required
        :max="today"
        data-testid="date_debut-input"
      />

      <!-- End Date Field -->
      <FloatingField
        id="date_fin"
        v-model="dateFinValue"
        type="date"
        label="Date de fin"
        :icon="Calendar"
        :error="getFieldError('date_fin') ?? undefined"
        :max="today"
        :min="form.date_debut"
        :disabled="form.is_ongoing"
        data-testid="date_fin-input"
      />
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
    <div>
      <FloatingTextarea
        id="description"
        v-model="form.description"
        label="Description"
        :icon="AlignLeft"
        :error="getFieldError('description') ?? undefined"
        :rows="3"
        :maxlength="500"
        data-testid="description-input"
      />
      <div class="flex justify-end mt-1">
        <span class="text-xs text-gray-400">{{ form.description.length }}/500</span>
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
