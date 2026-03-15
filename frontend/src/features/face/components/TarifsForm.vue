<script setup lang="ts">
import { reactive, watch, computed } from 'vue'
import type { TarifsInfo } from '../types'
import { FloatingField } from '@/components/ui/form'
import { Wallet } from 'lucide-vue-next'

const props = defineProps<{
  tarifsInfo: TarifsInfo | null
  isSaving: boolean
  error: string | null
}>()

const emit = defineEmits<{
  (e: 'save', data: { tarif_horaire: number | null; tarif_journalier: number | null }): void
}>()

const form = reactive({
  tarif_journalier: '' as string | number,
})

const formattedTarifJournalier = computed(() => {
  if (form.tarif_journalier === '' || form.tarif_journalier === undefined) return ''
  const num = typeof form.tarif_journalier === 'string' ? parseInt(form.tarif_journalier, 10) : form.tarif_journalier
  if (isNaN(num)) return ''
  return `${new Intl.NumberFormat('fr-FR').format(num)} F CFA`
})

const formattedTarifDemiJournee = computed(() => {
  if (form.tarif_journalier === '' || form.tarif_journalier === undefined) return ''
  const num = typeof form.tarif_journalier === 'string' ? parseInt(form.tarif_journalier, 10) : form.tarif_journalier
  if (isNaN(num) || num === 0) return ''
  return `${new Intl.NumberFormat('fr-FR').format(Math.round(num / 2))} F CFA`
})

watch(
  () => props.tarifsInfo,
  (info) => {
    if (info) {
      form.tarif_journalier = info.tarif_journalier ?? ''
    }
  },
  { immediate: true },
)

const handleSubmit = () => {
  const journalier = form.tarif_journalier === '' ? null : Number(form.tarif_journalier)
  emit('save', {
    tarif_horaire: journalier !== null ? Math.round(journalier / 8) : null,
    tarif_journalier: journalier,
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

    <!-- Tarif Journalier -->
    <div>
      <FloatingField
        id="tarif_journalier"
        v-model="form.tarif_journalier"
        type="number"
        label="Tarif journalier (F CFA)"
        :icon="Wallet"
        min="0"
        max="100000000"
        step="1"
        data-testid="tarif-journalier-input"
      />
      <div v-if="formattedTarifJournalier" class="mt-2 flex flex-wrap gap-4 text-xs text-gray-500" data-testid="tarif-preview">
        <span>Journée (8h) : <strong class="text-gray-700">{{ formattedTarifJournalier }}</strong></span>
        <span>Demi-journée (4h) : <strong class="text-gray-700">{{ formattedTarifDemiJournee }}</strong></span>
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
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
        {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
      </button>
    </div>
  </form>
</template>
