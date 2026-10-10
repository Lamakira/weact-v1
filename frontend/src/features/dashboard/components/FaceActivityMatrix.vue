<script setup lang="ts">
/**
 * FaceActivityMatrix — matrice Candidatures / Bookings x statut (direction Régie).
 * Remplace les 8 tuiles et les deux graphiques chart.js : chiffres issus des stats
 * existantes, delta = mois en cours vs mois précédent de la série mensuelle,
 * tendance = mini-barres des 6 derniers mois. Pas de bascule de période.
 */
import { computed } from 'vue'
import RPanel from '@/components/regie/RPanel.vue'
import RKpiMatrix, { type RKpiCell, type RKpiColumn, type RKpiRow } from '@/components/regie/RKpiMatrix.vue'
import { Skeleton } from '@/components/ui/skeleton'
import type {
  BookingMonthlyStats,
  BookingStats,
  DashboardStats,
  MonthlyStats,
} from '../types'

interface Props {
  stats: DashboardStats | null
  bookingStats: BookingStats | null
  candidaturesByMonth?: MonthlyStats[]
  bookingsByMonth?: BookingMonthlyStats[]
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  candidaturesByMonth: () => [],
  bookingsByMonth: () => [],
  isLoading: false,
})

type BucketKey = 'pending' | 'accepted' | 'in_progress' | 'completed'

const columns: RKpiColumn[] = [
  { key: 'pending', label: 'En attente', tone: 'pending' },
  { key: 'accepted', label: 'Acceptées', tone: 'success' },
  { key: 'in_progress', label: 'En cours', tone: 'progress' },
  { key: 'completed', label: 'Terminées', tone: 'done' },
]
const BUCKETS = columns.map((c) => c.key as BucketKey)

type MonthPoint = Record<BucketKey, number> & { total: number }

// Une candidature « confirmed » compte comme « en cours » (même regroupement que les stats serveur).
function toCandidaturePoints(series: MonthlyStats[]): MonthPoint[] {
  return series.map((m) => ({
    pending: m.pending,
    accepted: m.accepted,
    in_progress: m.in_progress + m.confirmed,
    completed: m.completed,
    total: m.pending + m.accepted + m.confirmed + m.in_progress + m.completed + m.rejected,
  }))
}

function toBookingPoints(series: BookingMonthlyStats[]): MonthPoint[] {
  return series.map((m) => ({
    pending: m.pending,
    accepted: m.accepted,
    in_progress: m.in_progress,
    completed: m.completed,
    total: m.pending + m.accepted + m.in_progress + m.completed,
  }))
}

const MINUS = '−'

/** Delta mois en cours vs mois précédent (le dernier point de la série = mois en cours). */
function deltaSub(points: MonthPoint[], key: BucketKey): Pick<RKpiCell, 'sub' | 'subTone'> {
  if (points.length < 2) return {}
  const current = points[points.length - 1]![key]
  const previous = points[points.length - 2]![key]
  const delta = current - previous
  if (delta > 0) return { sub: `+${delta} vs mois dernier`, subTone: 'positive' }
  if (delta < 0) return { sub: `${MINUS}${Math.abs(delta)} vs mois dernier`, subTone: 'muted' }
  return current > 0 ? { sub: 'stable vs mois dernier', subTone: 'muted' } : {}
}

function buildRow(
  key: string,
  label: string,
  routeName: string,
  counts: Record<BucketKey, number> | null,
  points: MonthPoint[],
  trendLabel: string,
): RKpiRow {
  const cells: Record<string, RKpiCell> = {}
  for (const bucket of BUCKETS) {
    cells[bucket] = { value: counts?.[bucket] ?? 0, ...deltaSub(points, bucket) }
  }
  return {
    key,
    label,
    to: { name: routeName },
    linkLabel: 'Ouvrir',
    cells,
    trend: points.length ? points.slice(-6).map((p) => p.total) : undefined,
    trendLabel,
  }
}

const rows = computed<RKpiRow[]>(() => [
  buildRow(
    'candidatures',
    'Candidatures',
    'face-candidatures',
    props.stats,
    toCandidaturePoints(props.candidaturesByMonth),
    'Candidatures par mois',
  ),
  buildRow(
    'bookings',
    'Bookings',
    'face-bookings',
    props.bookingStats,
    toBookingPoints(props.bookingsByMonth),
    'Bookings par mois',
  ),
])
</script>

<template>
  <RPanel title="Activité" data-testid="face-activity-matrix">
    <div v-if="isLoading && !stats && !bookingStats" class="space-y-3 p-4 sm:p-5" data-testid="face-activity-skeleton">
      <Skeleton class="h-16 w-full" />
      <Skeleton class="h-16 w-full" />
    </div>
    <RKpiMatrix v-else :columns="columns" :rows="rows" segmented-label="Activité" />
  </RPanel>
</template>
