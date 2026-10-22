<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { watchDebounced } from '@vueuse/core'
import { Banknote, Briefcase, MapPin, RotateCcw } from 'lucide-vue-next'
import { SimpleSelect } from '@/components/ui/select'
import type { PublicMissionFilters } from '../services/publicMissionsApi'

interface MissionTypeOption {
  value: string
  label: string
}

const props = defineProps<{
  currentFilters: PublicMissionFilters
  missionTypes: MissionTypeOption[]
}>()

const emit = defineEmits<{
  'filter-change': [filters: PublicMissionFilters]
}>()

const typeMission = ref('')
const lieu = ref('')
const budgetMin = ref('')
const budgetMax = ref('')
const isSyncingFromProps = ref(false)

const hasActiveFilters = computed(() =>
  Boolean(typeMission.value || lieu.value.trim() || budgetMin.value || budgetMax.value)
)

function toOptionalNumber(value: string | number): number | undefined {
  const normalized = String(value).trim()

  if (normalized === '') return undefined

  const parsed = Number(normalized)

  if (!Number.isFinite(parsed) || parsed < 0) {
    return undefined
  }

  return parsed
}

function buildFilters(): PublicMissionFilters {
  const filters: PublicMissionFilters = {}
  const normalizedLieu = lieu.value.trim()
  const normalizedBudgetMin = toOptionalNumber(budgetMin.value)
  const normalizedBudgetMax = toOptionalNumber(budgetMax.value)

  if (typeMission.value) filters.type_mission = typeMission.value
  if (normalizedLieu) filters.lieu = normalizedLieu
  if (normalizedBudgetMin != null) filters.budget_min = normalizedBudgetMin
  if (normalizedBudgetMax != null) filters.budget_max = normalizedBudgetMax

  return filters
}

function syncLocalState(filters: PublicMissionFilters): void {
  typeMission.value = filters.type_mission ?? ''
  lieu.value = filters.lieu ?? ''
  budgetMin.value = filters.budget_min != null ? String(filters.budget_min) : ''
  budgetMax.value = filters.budget_max != null ? String(filters.budget_max) : ''
}

function emitFilters(): void {
  if (isSyncingFromProps.value) return

  emit('filter-change', buildFilters())
}

function handleBudgetCommit(): void {
  emitFilters()
}

function resetFilters(): void {
  syncLocalState({})
  emit('filter-change', {})
}

watch(
  () => props.currentFilters,
  async (newFilters) => {
    isSyncingFromProps.value = true
    syncLocalState(newFilters)
    await nextTick()
    isSyncingFromProps.value = false
  },
  { immediate: true, deep: true },
)

watch(typeMission, emitFilters)

watchDebounced(
  lieu,
  () => {
    emitFilters()
  },
  { debounce: 400, maxWait: 400 },
)
</script>

<template>
  <div
    class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
    data-testid="public-mission-filters"
  >
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-4">
      <div class="relative lg:w-56">
        <SimpleSelect
          v-model="typeMission"
          :options="missionTypes"
          placeholder="Type de mission"
          empty-label="Tous les types"
          :icon="Briefcase"
          aria-label="Filtrer par type de mission"
          data-testid="mission-filter-type"
        />
      </div>

      <div class="relative flex-1">
        <MapPin
          aria-hidden="true"
          class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
        />
        <input
          v-model="lieu"
          type="text"
          maxlength="100"
          placeholder="Lieu..."
          class="h-10 w-full rounded-lg border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-700 placeholder:text-gray-400 transition-colors hover:border-[#198496]/30 focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
          aria-label="Filtrer par lieu"
          data-testid="mission-filter-lieu"
        />
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center lg:w-[22rem]">
        <div class="relative">
          <Banknote
            aria-hidden="true"
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
          />
          <input
            v-model="budgetMin"
            type="number"
            min="0"
            inputmode="numeric"
            placeholder="Min F CFA"
            class="h-10 w-full rounded-lg border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-700 placeholder:text-gray-400 transition-colors hover:border-[#198496]/30 focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
            aria-label="Budget minimum"
            data-testid="mission-filter-budget-min"
            @change="handleBudgetCommit"
          />
        </div>

        <span class="hidden text-center text-sm text-gray-300 sm:block">-</span>

        <div class="relative">
          <Banknote
            aria-hidden="true"
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
          />
          <input
            v-model="budgetMax"
            type="number"
            min="0"
            inputmode="numeric"
            placeholder="Max F CFA"
            class="h-10 w-full rounded-lg border border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-700 placeholder:text-gray-400 transition-colors hover:border-[#198496]/30 focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496]"
            aria-label="Budget maximum"
            data-testid="mission-filter-budget-max"
            @change="handleBudgetCommit"
          />
        </div>
      </div>

      <button
        v-if="hasActiveFilters"
        type="button"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 px-4 text-sm font-medium text-gray-600 transition-colors hover:border-[#198496]/30 hover:bg-[#198496]/5 hover:text-[#198496] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
        data-testid="mission-filter-reset"
        @click="resetFilters"
      >
        <RotateCcw class="h-4 w-4" />
        Réinitialiser
      </button>
    </div>
  </div>
</template>

<style scoped>
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

input[type='number'] {
  -moz-appearance: textfield;
}
</style>
