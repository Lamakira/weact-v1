<script setup lang="ts">
import { reactive, watch, computed } from 'vue'
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

const currentYear = new Date().getFullYear()

const form = reactive({
  titre: '',
  description: '',
  annee: currentYear,
})

// Watch for experience changes and update form (for edit mode)
watch(
  () => props.experience,
  (exp) => {
    if (exp) {
      form.titre = exp.titre
      form.description = exp.description ?? ''
      form.annee = exp.annee
    } else {
      // Reset form for add mode
      form.titre = ''
      form.description = ''
      form.annee = currentYear
    }
  },
  { immediate: true },
)

const isEditMode = computed(() => !!props.experience)

const handleSubmit = () => {
  emit('submit', {
    titre: form.titre,
    description: form.description || null,
    annee: form.annee,
  })
}

const handleCancel = () => {
  emit('cancel')
}

const getFieldError = (field: string): string | null => {
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

    <!-- Year Field -->
    <div class="space-y-1.5">
      <label for="annee" class="text-sm font-medium text-gray-900">
        Année <span class="text-red-500">*</span>
      </label>
      <input
        id="annee"
        v-model.number="form.annee"
        type="number"
        min="1950"
        :max="currentYear"
        required
        class="w-full px-3 py-2 text-sm rounded-lg border shadow-sm transition-colors focus:ring-2 focus:ring-teal-600 focus:border-teal-600"
        :class="getFieldError('annee') ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300'"
        :aria-describedby="getFieldError('annee') ? 'annee-error' : undefined"
        :aria-invalid="!!getFieldError('annee')"
        data-testid="annee-input"
      />
      <p
        v-if="getFieldError('annee')"
        id="annee-error"
        class="text-sm text-red-600"
        data-testid="annee-error"
      >
        {{ getFieldError('annee') }}
      </p>
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
