<script setup lang="ts">
/**
 * ActivityChart Component
 * Displays Face activity evolution charts (FR53):
 * - Candidatures per month grouped by status (stacked bar chart)
 * - Missions completed per month (line chart with area fill)
 */
import { computed } from 'vue'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
  type ChartOptions,
  type ChartData,
} from 'chart.js'
import { Bar, Line } from 'vue-chartjs'
import { Skeleton } from '@/components/ui/skeleton'
import { AlertCircle, RotateCcw, BarChart3 } from 'lucide-vue-next'
import type { MonthlyStats, MonthlyCount } from '../types'

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
)

const props = defineProps<{
  candidaturesByMonth: MonthlyStats[]
  missionsCompletedByMonth: MonthlyCount[]
  isLoading: boolean
  error: string | null
}>()

const emit = defineEmits<{
  retry: []
}>()

// French month names for chart labels
const monthNames = [
  'janv.',
  'févr.',
  'mars',
  'avr.',
  'mai',
  'juin',
  'juil.',
  'août',
  'sept.',
  'oct.',
  'nov.',
  'déc.',
]

/**
 * Format month string "YYYY-MM" to French label "janv. 26"
 */
const formatMonthLabel = (monthStr: string): string => {
  const parts = monthStr.split('-')
  const year = parts[0] ?? ''
  const month = parts[1] ?? '01'
  const monthIdx = parseInt(month, 10) - 1
  return `${monthNames[monthIdx]} ${year.slice(-2)}`
}

// Base chart configuration (shared between bar and line charts)
const baseConfig = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false,
      position: 'bottom' as const,
      labels: {
        usePointStyle: true,
        padding: 20,
        font: { size: 12, weight: 500 as const },
      },
    },
    tooltip: {
      backgroundColor: '#1f2937',
      padding: 12,
      cornerRadius: 8,
      titleFont: { size: 13, weight: 'bold' as const },
      bodyFont: { size: 12 },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: '#6b7280', font: { size: 11 } },
    },
    y: {
      border: { dash: [4, 4] },
      grid: { color: '#f3f4f6' },
      ticks: { color: '#6b7280', font: { size: 11 }, stepSize: 1 },
      beginAtZero: true,
    },
  },
}

// Bar chart data (candidatures by month)
const barData = computed<ChartData<'bar'>>(() => ({
  labels: props.candidaturesByMonth.map((d) => formatMonthLabel(d.month)),
  datasets: [
    {
      label: 'En attente',
      data: props.candidaturesByMonth.map((d) => d.pending),
      backgroundColor: '#a855f7',
      borderRadius: 4,
    },
    {
      label: 'Acceptées',
      data: props.candidaturesByMonth.map((d) => d.accepted),
      backgroundColor: '#22c55e',
      borderRadius: 4,
    },
    {
      label: 'En cours',
      data: props.candidaturesByMonth.map((d) => d.in_progress),
      backgroundColor: '#3b82f6',
      borderRadius: 4,
    },
    {
      label: 'Terminées',
      data: props.candidaturesByMonth.map((d) => d.completed),
      backgroundColor: '#ec4899',
      borderRadius: 4,
    },
  ],
}))

// Bar chart options with stacking enabled
const barOptions: ChartOptions<'bar'> = {
  ...baseConfig,
  plugins: {
    ...baseConfig.plugins,
    legend: { ...baseConfig.plugins.legend, display: true },
  },
  scales: {
    x: { ...baseConfig.scales.x, stacked: true },
    y: { ...baseConfig.scales.y, stacked: true },
  },
}

// Line chart options
const lineOptions: ChartOptions<'line'> = {
  ...baseConfig,
}

// Line chart data (missions completed by month)
const lineData = computed<ChartData<'line'>>(() => ({
  labels: props.missionsCompletedByMonth.map((d) => formatMonthLabel(d.month)),
  datasets: [
    {
      label: 'Missions terminées',
      data: props.missionsCompletedByMonth.map((d) => d.count),
      borderColor: '#198496',
      backgroundColor: 'rgba(25, 132, 150, 0.1)',
      fill: true,
      tension: 0.4,
      pointRadius: 4,
      pointBackgroundColor: '#fff',
      pointBorderWidth: 2,
      pointHoverRadius: 6,
    },
  ],
}))

// Check if there's any data to display
const hasData = computed(
  () => props.candidaturesByMonth.length > 0 || props.missionsCompletedByMonth.length > 0,
)

function handleRetry() {
  emit('retry')
}
</script>

<template>
  <section aria-labelledby="charts-section-title" data-testid="activity-chart">
    <h2 id="charts-section-title" class="text-lg font-semibold text-gray-900 mb-4">Mon évolution</h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full">
      <!-- Chart 1: Candidatures by Month -->
      <div
        class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm min-h-[400px] flex flex-col"
        data-testid="candidatures-chart"
      >
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-gray-900 font-semibold text-base">Candidatures par mois</h3>
        </div>

        <div class="flex-1 relative">
          <!-- Loading State -->
          <template v-if="isLoading">
            <div class="space-y-4 h-full" data-testid="candidatures-chart-loading">
              <Skeleton class="h-full w-full rounded-xl" />
            </div>
          </template>

          <!-- Error State -->
          <template v-else-if="error">
            <div
              class="h-full flex flex-col items-center justify-center text-center p-6"
              data-testid="candidatures-chart-error"
            >
              <div class="bg-red-50 p-3 rounded-full mb-3">
                <AlertCircle class="w-6 h-6 text-red-500" />
              </div>
              <p class="text-sm text-gray-600 mb-4">{{ error }}</p>
              <button
                @click="handleRetry"
                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                data-testid="candidatures-chart-retry"
              >
                <RotateCcw class="w-4 h-4" /> Réessayer
              </button>
            </div>
          </template>

          <!-- Empty State -->
          <template v-else-if="!hasData">
            <div
              class="h-full flex flex-col items-center justify-center text-center p-8"
              data-testid="candidatures-chart-empty"
            >
              <div class="bg-gray-50 p-4 rounded-full mb-4">
                <BarChart3 class="w-8 h-8 text-gray-300" />
              </div>
              <p class="text-sm text-gray-500 max-w-[240px] leading-relaxed">
                Pas encore de données. Commencez à postuler aux missions pour voir votre évolution
                apparaître ici !
              </p>
            </div>
          </template>

          <!-- Chart -->
          <template v-else>
            <Bar :data="barData" :options="barOptions" data-testid="candidatures-bar-chart" />
          </template>
        </div>
      </div>

      <!-- Chart 2: Missions Completed -->
      <div
        class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm min-h-[400px] flex flex-col"
        data-testid="missions-chart"
      >
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-gray-900 font-semibold text-base">Missions terminées</h3>
          <span
            v-if="!isLoading && !error && hasData"
            class="text-xs font-medium text-weact bg-weact-50 px-2 py-1 rounded-md"
          >
            Performance
          </span>
        </div>

        <div class="flex-1 relative">
          <!-- Loading State -->
          <template v-if="isLoading">
            <Skeleton class="h-full w-full rounded-xl" data-testid="missions-chart-loading" />
          </template>

          <!-- Error State -->
          <template v-else-if="error">
            <div
              class="h-full flex flex-col items-center justify-center text-center p-6"
              data-testid="missions-chart-error"
            >
              <div class="bg-red-50 p-3 rounded-full mb-3">
                <AlertCircle class="w-6 h-6 text-red-500" />
              </div>
              <p class="text-sm text-gray-600 mb-4">{{ error }}</p>
              <button
                @click="handleRetry"
                class="text-sm font-medium text-weact hover:underline"
                data-testid="missions-chart-retry"
              >
                Réessayer
              </button>
            </div>
          </template>

          <!-- Empty State -->
          <template v-else-if="!hasData">
            <div
              class="h-full flex flex-col items-center justify-center text-center p-8"
              data-testid="missions-chart-empty"
            >
              <div class="bg-gray-50 p-4 rounded-full mb-4">
                <BarChart3 class="w-8 h-8 text-gray-300" />
              </div>
              <p class="text-sm text-gray-500 max-w-[240px] leading-relaxed">
                Vos missions accomplies s'afficheront ici au fil du temps.
              </p>
            </div>
          </template>

          <!-- Chart -->
          <template v-else>
            <Line :data="lineData" :options="lineOptions" data-testid="missions-line-chart" />
          </template>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.text-weact {
  color: var(--color-weact);
}
.bg-weact-50 {
  background-color: var(--color-weact-50);
}
</style>
