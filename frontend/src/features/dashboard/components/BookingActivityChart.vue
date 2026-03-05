<script setup lang="ts">
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
import type { BookingMonthlyStats, MonthlyCount } from '../types'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, Filler)

const props = defineProps<{
  bookingsByMonth: BookingMonthlyStats[]
  bookingsCompletedByMonth: MonthlyCount[]
  isLoading: boolean
  error: string | null
}>()

const emit = defineEmits<{ retry: [] }>()

const monthNames = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.']

const formatMonthLabel = (monthStr: string): string => {
  const parts = monthStr.split('-')
  const year = parts[0] ?? ''
  const month = parts[1] ?? '01'
  return `${monthNames[parseInt(month, 10) - 1]} ${year.slice(-2)}`
}

const baseConfig = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false,
      position: 'bottom' as const,
      labels: { usePointStyle: true, padding: 20, font: { size: 12, weight: 500 as const } },
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
    x: { grid: { display: false }, ticks: { color: '#6b7280', font: { size: 11 } } },
    y: {
      border: { dash: [4, 4] },
      grid: { color: '#f3f4f6' },
      ticks: { color: '#6b7280', font: { size: 11 }, stepSize: 1 },
      beginAtZero: true,
    },
  },
}

const barData = computed<ChartData<'bar'>>(() => ({
  labels: props.bookingsByMonth.map((d) => formatMonthLabel(d.month)),
  datasets: [
    { label: 'En attente', data: props.bookingsByMonth.map((d) => d.pending),     backgroundColor: '#f59e0b', borderRadius: 4 },
    { label: 'Acceptés',   data: props.bookingsByMonth.map((d) => d.accepted),    backgroundColor: '#22c55e', borderRadius: 4 },
    { label: 'En cours',   data: props.bookingsByMonth.map((d) => d.in_progress), backgroundColor: '#3b82f6', borderRadius: 4 },
    { label: 'Terminés',   data: props.bookingsByMonth.map((d) => d.completed),   backgroundColor: '#198496', borderRadius: 4 },
  ],
}))

const barOptions: ChartOptions<'bar'> = {
  ...baseConfig,
  plugins: { ...baseConfig.plugins, legend: { ...baseConfig.plugins.legend, display: true } },
  scales: {
    x: { ...baseConfig.scales.x, stacked: true },
    y: { ...baseConfig.scales.y, stacked: true },
  },
}

const lineOptions: ChartOptions<'line'> = { ...baseConfig }

const lineData = computed<ChartData<'line'>>(() => ({
  labels: props.bookingsCompletedByMonth.map((d) => formatMonthLabel(d.month)),
  datasets: [
    {
      label: 'Bookings terminés',
      data: props.bookingsCompletedByMonth.map((d) => d.count),
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

const hasData = computed(
  () => props.bookingsByMonth.length > 0 || props.bookingsCompletedByMonth.length > 0,
)
</script>

<template>
  <section aria-labelledby="booking-charts-title" data-testid="booking-activity-chart">
    <h2 id="booking-charts-title" class="text-lg font-semibold text-gray-900 mb-4">Mon évolution (Bookings)</h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full">
      <!-- Chart 1: Bookings par mois -->
      <div
        class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm min-h-[400px] flex flex-col"
        data-testid="bookings-bar-chart-card"
      >
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-gray-900 font-semibold text-base">Bookings par mois</h3>
        </div>

        <div class="flex-1 relative">
          <template v-if="isLoading">
            <Skeleton class="h-full w-full rounded-xl" />
          </template>
          <template v-else-if="error">
            <div class="h-full flex flex-col items-center justify-center text-center p-6">
              <div class="bg-red-50 p-3 rounded-full mb-3">
                <AlertCircle class="w-6 h-6 text-red-500" />
              </div>
              <p class="text-sm text-gray-600 mb-4">{{ error }}</p>
              <button
                @click="emit('retry')"
                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
              >
                <RotateCcw class="w-4 h-4" /> Réessayer
              </button>
            </div>
          </template>
          <template v-else-if="!hasData">
            <div class="h-full flex flex-col items-center justify-center text-center p-8">
              <div class="bg-gray-50 p-4 rounded-full mb-4">
                <BarChart3 class="w-8 h-8 text-gray-300" />
              </div>
              <p class="text-sm text-gray-500 max-w-[240px] leading-relaxed">
                Pas encore de bookings. Vos données apparaîtront ici au fil du temps.
              </p>
            </div>
          </template>
          <template v-else>
            <Bar :data="barData" :options="barOptions" />
          </template>
        </div>
      </div>

      <!-- Chart 2: Bookings terminés -->
      <div
        class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm min-h-[400px] flex flex-col"
        data-testid="bookings-line-chart-card"
      >
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-gray-900 font-semibold text-base">Bookings terminés</h3>
          <span
            v-if="!isLoading && !error && hasData"
            class="text-xs font-medium text-weact bg-weact-50 px-2 py-1 rounded-md"
          >
            Performance
          </span>
        </div>

        <div class="flex-1 relative">
          <template v-if="isLoading">
            <Skeleton class="h-full w-full rounded-xl" />
          </template>
          <template v-else-if="error">
            <div class="h-full flex flex-col items-center justify-center text-center p-6">
              <div class="bg-red-50 p-3 rounded-full mb-3">
                <AlertCircle class="w-6 h-6 text-red-500" />
              </div>
              <p class="text-sm text-gray-600 mb-4">{{ error }}</p>
              <button @click="emit('retry')" class="text-sm font-medium text-weact hover:underline">
                Réessayer
              </button>
            </div>
          </template>
          <template v-else-if="!hasData">
            <div class="h-full flex flex-col items-center justify-center text-center p-8">
              <div class="bg-gray-50 p-4 rounded-full mb-4">
                <BarChart3 class="w-8 h-8 text-gray-300" />
              </div>
              <p class="text-sm text-gray-500 max-w-[240px] leading-relaxed">
                Vos bookings terminés s'afficheront ici au fil du temps.
              </p>
            </div>
          </template>
          <template v-else>
            <Line :data="lineData" :options="lineOptions" />
          </template>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.text-weact { color: var(--color-weact); }
.bg-weact-50 { background-color: var(--color-weact-50); }
</style>
