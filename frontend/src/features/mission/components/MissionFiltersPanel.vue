<script setup lang="ts">
import { computed } from 'vue'
import { MissionTypeLabel, type MissionTypeType } from '../types'

// Props for v-model binding
const lieu = defineModel<string>('lieu', { default: '' })
const budgetMin = defineModel<number | undefined>('budgetMin', { default: undefined })
const budgetMax = defineModel<number | undefined>('budgetMax', { default: undefined })
const dateTournage = defineModel<string>('dateTournage', { default: '' })
const typeMission = defineModel<MissionTypeType | ''>('typeMission', { default: '' })

// Emits
const emit = defineEmits<{
  apply: []
  reset: []
}>()

// Props
defineProps<{
  isLoading?: boolean
}>()

// Mission type options
const missionTypeOptions = computed(() =>
  Object.entries(MissionTypeLabel).map(([value, label]) => ({
    value: value as MissionTypeType,
    label,
  })),
)

// Handle budget input (convert empty to undefined, enforce min 0)
function handleBudgetMinInput(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  if (!value) {
    budgetMin.value = undefined
  } else {
    const parsed = parseInt(value, 10)
    budgetMin.value = isNaN(parsed) ? undefined : Math.max(0, parsed)
  }
}

function handleBudgetMaxInput(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  if (!value) {
    budgetMax.value = undefined
  } else {
    const parsed = parseInt(value, 10)
    budgetMax.value = isNaN(parsed) ? undefined : Math.max(0, parsed)
  }
}

function onApply(): void {
  emit('apply')
}

function onReset(): void {
  emit('reset')
}
</script>

<template>
  <div class="bg-white rounded-lg shadow p-4 sm:p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filtres</h3>

    <div class="space-y-4">
      <!-- Ville filter -->
      <div>
        <label for="filter-lieu" class="block text-sm font-medium text-gray-700 mb-1">
          Ville
        </label>
        <input
          id="filter-lieu"
          v-model="lieu"
          type="text"
          placeholder="Ex: Cotonou"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
          :disabled="isLoading"
          data-testid="filter-lieu"
        />
      </div>

      <!-- Budget range filter -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"> Budget (XOF) </label>
        <div class="flex items-center gap-2">
          <input
            id="filter-budget-min"
            :value="budgetMin ?? ''"
            type="number"
            min="0"
            placeholder="Min"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
            :disabled="isLoading"
            data-testid="filter-budget-min"
            @input="handleBudgetMinInput"
          />
          <span class="text-gray-500">-</span>
          <input
            id="filter-budget-max"
            :value="budgetMax ?? ''"
            type="number"
            min="0"
            placeholder="Max"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
            :disabled="isLoading"
            data-testid="filter-budget-max"
            @input="handleBudgetMaxInput"
          />
        </div>
      </div>

      <!-- Date filter -->
      <div>
        <label for="filter-date" class="block text-sm font-medium text-gray-700 mb-1">
          Date de tournage (à partir du)
        </label>
        <input
          id="filter-date"
          v-model="dateTournage"
          type="date"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
          :disabled="isLoading"
          data-testid="filter-date"
        />
      </div>

      <!-- Type mission filter -->
      <div>
        <label for="filter-type" class="block text-sm font-medium text-gray-700 mb-1">
          Type de mission
        </label>
        <select
          id="filter-type"
          v-model="typeMission"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors bg-white"
          :disabled="isLoading"
          data-testid="filter-type"
        >
          <option value="">Tous les types</option>
          <option v-for="option in missionTypeOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- Action buttons -->
    <div class="flex flex-col sm:flex-row gap-2 mt-6">
      <button
        type="button"
        class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors disabled:opacity-50"
        :disabled="isLoading"
        data-testid="filter-reset"
        @click="onReset"
      >
        Réinitialiser
      </button>
      <button
        type="button"
        class="flex-1 px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors disabled:opacity-50"
        :disabled="isLoading"
        data-testid="filter-apply"
        @click="onApply"
      >
        Appliquer
      </button>
    </div>
  </div>
</template>

<style scoped>
.bg-primary {
  background-color: #198496;
}
.bg-primary\/90 {
  background-color: rgba(25, 132, 150, 0.9);
}
.text-primary {
  color: #198496;
}
.focus\:ring-primary:focus {
  --tw-ring-color: #198496;
}
.focus\:border-primary:focus {
  border-color: #198496;
}
</style>
