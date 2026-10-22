<script setup lang="ts">
import { computed } from 'vue'
import type { MissionFilterStatus } from '../types'
import { MISSION_ACTIVE_FILTER, MISSION_TO_PROCESS_FILTER, MissionStatus } from '../types'
import { useActionCountsStore } from '@/stores/actionCounts'
import ListFilterBar, { type FilterOption } from '@/components/ListFilterBar.vue'

defineProps<{
  modelValue: MissionFilterStatus
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: MissionFilterStatus): void
}>()

const actionCounts = useActionCountsStore()

/** Pastilles visibles (lot F4) ; le nombre « À traiter » vient du store actionCounts. */
const primary = computed<FilterOption<MissionFilterStatus>[]>(() => [
  { value: '', label: 'Tous' },
  { value: MISSION_TO_PROCESS_FILTER, label: 'À traiter', count: actionCounts.counts.missions },
  { value: MISSION_ACTIVE_FILTER, label: 'En cours' },
  { value: MissionStatus.COMPLETED, label: 'Terminées' },
])

/** Statuts précis, rangés dans le menu « Plus » (brouillons exclus comme avant). */
const more: FilterOption<MissionFilterStatus>[] = [
  { value: MissionStatus.PUBLISHED, label: 'Publiées' },
  { value: MissionStatus.CLOSED, label: 'Recrutement clos' },
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
