<script setup lang="ts">
/**
 * RKpiMatrix — matrice lignes x colonnes de chiffres, avec sous-ligne optionnelle
 * (delta / contexte) et colonne de mini-barres. Sur mobile (< md), elle devient une
 * liste pour la ligne active, choisie via un contrôle segmenté.
 */
import { computed, ref } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'
import RStatusDot from './RStatusDot.vue'
import RMiniBars from './RMiniBars.vue'
import RSegmented from './RSegmented.vue'
import type { StatusTone } from './statusTone'

export interface RKpiColumn {
  key: string
  label: string
  tone?: StatusTone
}

export interface RKpiCell {
  value: number | string
  /** Sous-ligne : delta ou contexte (« +2 sem. », « expire 22 h ») */
  sub?: string
  subTone?: 'muted' | 'positive' | 'urgent'
}

export interface RKpiRow {
  key: string
  label: string
  to?: RouteLocationRaw
  linkLabel?: string
  cells: Record<string, RKpiCell | undefined>
  /** Valeurs des mini-barres (le dernier = mois en cours) */
  trend?: number[]
  trendLabel?: string
}

interface Props {
  columns: RKpiColumn[]
  rows: RKpiRow[]
  trendHeading?: string
  segmentedLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  trendHeading: 'Tendance 6 mois',
  segmentedLabel: 'Type',
})

const firstKey = computed(() => props.rows[0]?.key ?? '')
const selectedKey = ref<string | null>(null)
const activeKey = computed({
  get: () => selectedKey.value ?? firstKey.value,
  set: (value: string) => {
    selectedKey.value = value
  },
})
const activeRow = computed(() => props.rows.find((row) => row.key === activeKey.value))
const hasTrend = computed(() => props.rows.some((row) => row.trend?.length))

const subClass: Record<NonNullable<RKpiCell['subTone']>, string> = {
  muted: 'text-ink-3',
  positive: 'text-positive',
  urgent: 'text-urgent',
}

function subToneClass(cell: RKpiCell | undefined): string {
  return subClass[cell?.subTone ?? 'muted']
}
</script>

<template>
  <div data-testid="r-kpi-matrix">
    <!-- Desktop / tablette : tableau -->
    <table class="hidden w-full text-left md:table" data-testid="r-kpi-table">
      <thead>
        <tr class="border-b border-line text-[12px] text-ink-3">
          <th scope="col" class="px-5 py-2.5 font-medium"><span class="sr-only">Type</span></th>
          <th v-for="column in columns" :key="column.key" scope="col" class="px-3 py-2.5 font-medium">
            <RStatusDot v-if="column.tone" :tone="column.tone" :label="column.label" />
            <template v-else>{{ column.label }}</template>
          </th>
          <th v-if="hasTrend" scope="col" class="px-5 py-2.5 text-right font-medium">
            {{ trendHeading }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-line">
        <tr v-for="row in rows" :key="row.key" :data-row="row.key">
          <th scope="row" class="px-5 py-4 text-left font-normal">
            <p class="text-dash font-semibold text-ink">{{ row.label }}</p>
            <RouterLink v-if="row.to" :to="row.to" class="text-[12px] font-semibold text-weact-700 hover:underline">
              {{ row.linkLabel ?? 'Ouvrir' }}
            </RouterLink>
          </th>
          <td v-for="column in columns" :key="column.key" class="px-3 py-4" :data-cell="column.key">
            <p class="text-[26px] font-semibold leading-none tracking-[-0.02em] text-ink">
              {{ row.cells[column.key]?.value ?? '—' }}
            </p>
            <p
              v-if="row.cells[column.key]?.sub"
              class="mt-1 text-[11.5px]"
              :class="subToneClass(row.cells[column.key])"
            >
              {{ row.cells[column.key]?.sub }}
            </p>
          </td>
          <td v-if="hasTrend" class="px-5 py-4">
            <div v-if="row.trend?.length" class="flex justify-end">
              <RMiniBars :values="row.trend" :label="row.trendLabel ?? `${row.label} par mois`" />
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Mobile : liste de la ligne active -->
    <div class="md:hidden" data-testid="r-kpi-list">
      <div v-if="rows.length > 1" class="px-3 pt-3">
        <RSegmented
          v-model="activeKey"
          :group-label="segmentedLabel"
          :options="rows.map((row) => ({ value: row.key, label: row.label }))"
        />
      </div>
      <ul v-if="activeRow" class="mt-2 divide-y divide-line">
        <li
          v-for="column in columns"
          :key="column.key"
          class="flex h-12 items-center gap-2.5 px-4"
          :data-cell="column.key"
        >
          <RStatusDot v-if="column.tone" :tone="column.tone" :label="column.label" class="flex-1 text-dash text-ink" />
          <span v-else class="flex-1 text-dash text-ink">{{ column.label }}</span>
          <span
            v-if="activeRow.cells[column.key]?.sub"
            class="text-[11.5px]"
            :class="subToneClass(activeRow.cells[column.key])"
          >
            {{ activeRow.cells[column.key]?.sub }}
          </span>
          <span class="w-8 text-right text-[18px] font-semibold tracking-[-0.02em] text-ink">
            {{ activeRow.cells[column.key]?.value ?? '—' }}
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>
