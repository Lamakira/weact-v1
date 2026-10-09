<script setup lang="ts">
import { formatXof } from '@/lib/formatCurrency'
import { computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'
import type { SortingState } from '@tanstack/vue-table'
import DataTable from '@/components/data-table/DataTable.vue'
import type { DataTableColumn } from '@/components/data-table/dataTableFeatures'
import type { Booking, BookingFaceUserable, BookingProducerUserable } from '../types'
import BookingStatusBadge from './BookingStatusBadge.vue'
import BookingCard from './BookingCard.vue'

const props = withDefaults(
  defineProps<{
    bookings: Booking[]
    /** Who is looking at the list: drives the counterpart column and the amount shown. */
    role: 'face' | 'producer'
    sorting: SortingState
    page: number
    pageSize: number
    total: number
    lastPage: number
    loading?: boolean
    error?: string | null
    view?: 'table' | 'cards'
  }>(),
  { loading: false, error: null, view: 'table' },
)

const emit = defineEmits<{
  'update:sorting': [sorting: SortingState]
  'page-change': [page: number]
  'page-size-change': [pageSize: number]
  retry: []
}>()

defineSlots<{ empty?: () => unknown }>()

const router = useRouter()

const detailRouteName = computed(() =>
  props.role === 'face' ? 'face-booking-detail' : 'producer-booking-detail',
)

function counterpart(booking: Booking): { name: string; avatar: string | null; initial: string } {
  if (props.role === 'face') {
    const userable = booking.producer?.userable as BookingProducerUserable | undefined
    const name = userable?.display_name || 'Producteur'
    return {
      name,
      avatar: userable?.thumbnail_url || userable?.profile_photo_url || null,
      initial: name.charAt(0).toUpperCase(),
    }
  }
  const userable = booking.face?.userable as BookingFaceUserable | undefined
  const name = userable?.prenom && userable?.nom ? `${userable.prenom} ${userable.nom}` : 'Face'
  return {
    name,
    avatar: userable?.thumbnail_url || userable?.profile_photo_url || null,
    initial: name.charAt(0).toUpperCase(),
  }
}

function formatDate(value: string | null): string {
  if (!value) return ''
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }).format(
    new Date(value),
  )
}

function formatDateRange(booking: Booking): string {
  // UGC dotations have no shoot dates.
  if (!booking.date_debut || !booking.date_fin) return '—'
  const start = formatDate(booking.date_debut)
  const end = formatDate(booking.date_fin)
  return start === end ? start : `${start} — ${end}`
}

function amountOf(booking: Booking): number {
  return props.role === 'face' ? booking.montant_face_recoit : booking.montant_total_producteur
}

const columns = computed<DataTableColumn<Booking>[]>(() => [
  {
    id: 'counterpart',
    accessorFn: (b) => counterpart(b).name,
    header: props.role === 'face' ? 'Producteur' : 'Face',
    enableSorting: false,
  },
  { id: 'type_contenu', accessorFn: (b) => b.type_contenu, header: 'Contenu', enableSorting: false },
  { id: 'date_debut', accessorFn: (b) => b.date_debut, header: 'Tournage', enableSorting: true },
  { id: 'duree', accessorFn: (b) => b.duree_heures, header: 'Durée', enableSorting: false },
  {
    id: 'montant',
    accessorFn: (b) => amountOf(b),
    header: props.role === 'face' ? 'Montant reçu' : 'Montant',
    enableSorting: true,
  },
  { id: 'status', accessorFn: (b) => b.status, header: 'Statut', enableSorting: true },
  { id: 'created_at', accessorFn: (b) => b.created_at, header: 'Créé le', enableSorting: true },
  { id: 'actions', header: 'Actions', enableSorting: false },
])

function goToDetail(booking: Booking): void {
  void router.push({ name: detailRouteName.value, params: { id: booking.id } })
}
</script>

<template>
  <DataTable
    :columns="columns"
    :data="bookings"
    :row-id="(b: Booking) => b.id"
    :sorting="sorting"
    :page="page"
    :page-size="pageSize"
    :total="total"
    :last-page="lastPage"
    :loading="loading"
    :error="error"
    :view="view"
    caption="Liste de vos bookings"
    row-clickable
    @update:sorting="emit('update:sorting', $event)"
    @page-change="emit('page-change', $event)"
    @page-size-change="emit('page-size-change', $event)"
    @retry="emit('retry')"
    @row-click="goToDetail"
  >
    <template #empty><slot name="empty" /></template>

    <template #cell-counterpart="{ row }">
      <div class="flex min-w-0 items-center gap-3">
        <div class="relative h-9 w-9 shrink-0 overflow-hidden rounded-full border border-border bg-muted">
          <img
            v-if="counterpart(row).avatar"
            :src="counterpart(row).avatar!"
            :alt="counterpart(row).name"
            class="h-full w-full object-cover"
          />
          <div
            v-else
            class="flex h-full w-full items-center justify-center bg-primary/10 text-sm font-bold uppercase text-primary"
          >
            {{ counterpart(row).initial }}
          </div>
        </div>
        <span class="max-w-[12rem] truncate font-medium text-foreground">{{ counterpart(row).name }}</span>
      </div>
    </template>

    <template #cell-type_contenu="{ row }">{{ row.type_contenu }}</template>

    <template #cell-date_debut="{ row }">
      <span class="text-muted-foreground">{{ formatDateRange(row) }}</span>
    </template>

    <template #cell-duree="{ row }">
      <span class="text-muted-foreground">{{ row.duree_heures ? `max ${row.duree_heures}h` : '—' }}</span>
    </template>

    <template #cell-montant="{ row }">
      <span class="font-semibold text-foreground">{{ formatXof(amountOf(row)) }}</span>
    </template>

    <template #cell-status="{ row }">
      <BookingStatusBadge :status="row.status" />
    </template>

    <template #cell-created_at="{ row }">
      <span class="text-muted-foreground">{{ formatDate(row.created_at) }}</span>
    </template>

    <template #cell-actions="{ row }">
      <RouterLink
        :to="{ name: detailRouteName, params: { id: row.id } }"
        class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-sm font-medium text-foreground transition-colors hover:border-primary/40 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        :aria-label="`Voir le booking avec ${counterpart(row).name}`"
      >
        Voir
        <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
      </RouterLink>
    </template>

    <template #card="{ row }">
      <BookingCard :booking="row" />
    </template>
  </DataTable>
</template>
