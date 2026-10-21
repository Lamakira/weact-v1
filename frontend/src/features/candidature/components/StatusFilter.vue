<script setup lang="ts">
import { computed } from 'vue'
import type { CandidatureFilterStatus } from '../types'
import { CANDIDATURE_ACTIVE_FILTER, CANDIDATURE_TO_PROCESS_FILTER, CandidatureStatus, CandidatureStatusLabel } from '../types'
import { useActionCountsStore } from '@/stores/actionCounts'
import ListFilterBar, { type FilterOption } from '@/components/ListFilterBar.vue'

const props = defineProps<{
  modelValue: CandidatureFilterStatus
  /** Pastille « À traiter » (lot D7) : liste « Mes candidatures » de la Face uniquement. */
  withToProcess?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: CandidatureFilterStatus): void
}>()

const actionCounts = useActionCountsStore()

/** Pastilles visibles (lot F4) ; le nombre « À traiter » vient du store actionCounts. */
const primary = computed<FilterOption<CandidatureFilterStatus>[]>(() => [
  { value: '', label: 'Tous' },
  ...(props.withToProcess
    ? [{ value: CANDIDATURE_TO_PROCESS_FILTER as CandidatureFilterStatus, label: 'À traiter', count: actionCounts.counts.candidatures }]
    : []),
  { value: CANDIDATURE_ACTIVE_FILTER, label: 'En cours' },
  { value: CandidatureStatus.COMPLETED, label: 'Terminées' },
])

/** Statuts rares, rangés dans le menu « Plus » (libellés du point de vue de la Face). */
const more: FilterOption<CandidatureFilterStatus>[] = [
  { value: CandidatureStatus.PENDING, label: CandidatureStatusLabel[CandidatureStatus.PENDING] },
  { value: CandidatureStatus.REJECTED, label: 'Non retenues' },
  { value: CandidatureStatus.CANCELLED, label: 'Annulées' },
  { value: CandidatureStatus.EXPIRED, label: 'Expirées' },
]
</script>

<template>
  <ListFilterBar
    :model-value="modelValue"
    :primary="primary"
    :more="more"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
