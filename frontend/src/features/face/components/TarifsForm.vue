<script setup lang="ts">
import { reactive, watch, computed } from 'vue'
import type { TarifsInfo } from '../types'

const props = defineProps<{
  tarifsInfo: TarifsInfo | null
  isSaving: boolean
  error: string | null
}>()

const emit = defineEmits<{
  (e: 'save', data: { tarif_horaire: number | null; tarif_journalier: number | null }): void
}>()

const form = reactive({
  tarif_horaire: '' as string | number,
  tarif_journalier: '' as string | number,
})

// Format number with French thousand separators for display
const formatCurrency = (value: number | string | null): string => {
  if (value === null || value === '' || value === undefined) return ''
  const num = typeof value === 'string' ? parseInt(value, 10) : value
  if (isNaN(num)) return ''
  return new Intl.NumberFormat('fr-FR').format(num)
}

// Computed formatted display values
const formattedTarifHoraire = computed(() => {
  const formatted = formatCurrency(form.tarif_horaire)
  return formatted ? `${formatted} XOF` : ''
})

const formattedTarifJournalier = computed(() => {
  const formatted = formatCurrency(form.tarif_journalier)
  return formatted ? `${formatted} XOF` : ''
})

// Watch for tarifsInfo changes and update form
watch(
  () => props.tarifsInfo,
  (info) => {
    if (info) {
      form.tarif_horaire = info.tarif_horaire ?? ''
      form.tarif_journalier = info.tarif_journalier ?? ''
    }
  },
  { immediate: true },
)

const handleSubmit = () => {
  emit('save', {
    tarif_horaire: form.tarif_horaire === '' ? null : Number(form.tarif_horaire),
    tarif_journalier: form.tarif_journalier === '' ? null : Number(form.tarif_journalier),
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

    <!-- Tarifs Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Tarif Horaire -->
      <div class="space-y-1.5">
        <label for="tarif_horaire" class="text-sm font-medium text-gray-900"
          >Tarif horaire (XOF)</label
        >
        <input
          id="tarif_horaire"
          type="number"
          v-model="form.tarif_horaire"
          min="0"
          max="10000000"
          step="1"
          placeholder="Ex: 75000"
          class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition-colors"
          data-testid="tarif-horaire-input"
        />
        <p v-if="formattedTarifHoraire" class="text-xs text-gray-500" data-testid="tarif-horaire-preview">
          {{ formattedTarifHoraire }}/heure
        </p>
      </div>

      <!-- Tarif Journalier -->
      <div class="space-y-1.5">
        <label for="tarif_journalier" class="text-sm font-medium text-gray-900"
          >Tarif journalier (XOF)</label
        >
        <input
          id="tarif_journalier"
          type="number"
          v-model="form.tarif_journalier"
          min="0"
          max="100000000"
          step="1"
          placeholder="Ex: 250000"
          class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition-colors"
          data-testid="tarif-journalier-input"
        />
        <p v-if="formattedTarifJournalier" class="text-xs text-gray-500" data-testid="tarif-journalier-preview">
          {{ formattedTarifJournalier }}/jour
        </p>
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
