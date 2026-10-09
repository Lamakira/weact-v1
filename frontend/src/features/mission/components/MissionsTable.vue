<script setup lang="ts">
import { computed } from 'vue'
import { Calendar, Users, Wallet } from 'lucide-vue-next'
import type { SortingState } from '@tanstack/vue-table'
import DataTable from '@/components/data-table/DataTable.vue'
import type { DataTableColumn } from '@/components/data-table/dataTableFeatures'
import type { Mission, MissionStatusType } from '../types'
import { getMissionActionState } from '../composables/missionActionState'
import MissionRowActions from './MissionRowActions.vue'

const props = withDefaults(
  defineProps<{
    missions: Mission[]
    emailVerified?: boolean
    sorting: SortingState
    page: number
    pageSize: number
    total: number
    lastPage: number
    loading?: boolean
    error?: string | null
    /** table from md, cards below (decided by the page). */
    view?: 'table' | 'cards'
  }>(),
  { emailVerified: true, loading: false, error: null, view: 'table' },
)

const emit = defineEmits<{
  'update:sorting': [sorting: SortingState]
  'page-change': [page: number]
  'page-size-change': [pageSize: number]
  retry: []
  edit: [id: string]
  delete: [id: string]
  close: [id: string]
  reopen: [id: string]
  complete: [id: string]
  viewCandidatures: [id: string]
  viewAttendance: [id: string]
  payCommission: [id: string]
}>()

defineSlots<{ empty?: () => unknown }>()

const statusClasses: Record<MissionStatusType, string> = {
  draft: 'bg-gray-100 text-gray-800 border-gray-200',
  published: 'bg-green-100 text-green-800 border-green-200',
  pending_payment: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  closed: 'bg-orange-100 text-orange-800 border-orange-200',
  pending_attendance_validation: 'bg-amber-100 text-amber-800 border-amber-200',
  completed: 'bg-blue-100 text-blue-800 border-blue-200',
}

function statusClass(status: MissionStatusType): string {
  return statusClasses[status] ?? statusClasses.draft
}

function isUgc(mission: Mission): boolean {
  return getMissionActionState(mission, true).isUgc
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }).format(
    new Date(value),
  )
}

function formatCurrency(amount: number): string {
  return (
    new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', currencyDisplay: 'code' })
      .format(amount)
      .replace('XOF', '')
      .trim() + ' XOF'
  )
}

const columns = computed<DataTableColumn<Mission>[]>(() => [
  { id: 'titre', accessorFn: (m) => m.titre, header: 'Mission', enableSorting: false },
  { id: 'status', accessorFn: (m) => m.status, header: 'Statut', enableSorting: true },
  {
    id: 'date_limite_candidature',
    accessorFn: (m) => m.date_limite_candidature,
    header: 'Limite candidature',
    enableSorting: true,
  },
  { id: 'date_tournage', accessorFn: (m) => m.date_tournage, header: 'Tournage', enableSorting: true },
  {
    id: 'candidatures_count',
    accessorFn: (m) => m.candidatures_count ?? 0,
    header: 'Candidatures',
    enableSorting: true,
  },
  { id: 'faces', accessorFn: (m) => m.nombre_faces_voulu, header: 'Faces voulues', enableSorting: false },
  { id: 'budget', accessorFn: (m) => m.budget, header: 'Budget', enableSorting: false },
  { id: 'created_at', accessorFn: (m) => m.created_at, header: 'Créée le', enableSorting: true },
  { id: 'actions', header: 'Actions', enableSorting: false },
])

// Same behaviour as the former card: a click on an editable mission opens its edit form.
function onRowClick(mission: Mission): void {
  if (getMissionActionState(mission, props.emailVerified).canEdit) emit('edit', mission.id)
}
</script>

<template>
  <DataTable
    :columns="columns"
    :data="missions"
    :row-id="(m: Mission) => m.id"
    :sorting="sorting"
    :page="page"
    :page-size="pageSize"
    :total="total"
    :last-page="lastPage"
    :loading="loading"
    :error="error"
    :view="view"
    caption="Liste de vos missions"
    error-hint="Impossible de charger vos missions pour le moment."
    row-clickable
    @update:sorting="emit('update:sorting', $event)"
    @page-change="emit('page-change', $event)"
    @page-size-change="emit('page-size-change', $event)"
    @retry="emit('retry')"
    @row-click="onRowClick"
  >
    <template #empty><slot name="empty" /></template>

    <template #cell-titre="{ row }">
      <div class="min-w-0 max-w-[18rem]">
        <p class="truncate font-semibold text-foreground">{{ row.titre }}</p>
        <span
          class="mt-1 inline-block rounded-full border px-2 py-0.5 text-[11px] font-medium"
          :class="isUgc(row) ? 'border-primary/20 bg-primary/10 text-primary' : 'border-border bg-muted text-muted-foreground'"
        >
          {{ isUgc(row) ? 'UGC' : row.type_mission_label }}
        </span>
      </div>
    </template>

    <template #cell-status="{ row }">
      <span
        class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold"
        :class="statusClass(row.status)"
      >
        {{ row.status_label }}
      </span>
    </template>

    <template #cell-date_limite_candidature="{ row }">
      <span class="text-muted-foreground">{{ formatDate(row.date_limite_candidature) }}</span>
    </template>

    <template #cell-date_tournage="{ row }">
      <!-- Shoot date is hidden for UGC missions (null shoot fields). -->
      <span class="text-muted-foreground">{{ isUgc(row) ? '—' : formatDate(row.date_tournage) }}</span>
    </template>

    <template #cell-candidatures_count="{ row }">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/50 px-2.5 py-0.5 text-xs font-medium text-muted-foreground transition-colors hover:border-primary/30 hover:bg-primary/10 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        :aria-label="`${row.candidatures_count ?? 0} candidature${(row.candidatures_count ?? 0) > 1 ? 's' : ''} pour ${row.titre}`"
        @click.stop="emit('viewCandidatures', row.id)"
      >
        <Users class="h-3.5 w-3.5 text-primary" aria-hidden="true" />
        {{ row.candidatures_count ?? 0 }}
      </button>
    </template>

    <template #cell-faces="{ row }">
      <span class="text-muted-foreground">{{ row.nombre_faces_voulu }}</span>
    </template>

    <template #cell-budget="{ row }">
      <span class="font-semibold text-foreground">{{ formatCurrency(row.budget) }}</span>
    </template>

    <template #cell-created_at="{ row }">
      <span class="text-muted-foreground">{{ formatDate(row.created_at) }}</span>
    </template>

    <template #cell-actions="{ row }">
      <MissionRowActions
        :mission="row"
        :email-verified="emailVerified"
        @edit="emit('edit', $event)"
        @delete="emit('delete', $event)"
        @close="emit('close', $event)"
        @reopen="emit('reopen', $event)"
        @complete="emit('complete', $event)"
        @view-candidatures="emit('viewCandidatures', $event)"
        @view-attendance="emit('viewAttendance', $event)"
        @pay-commission="emit('payCommission', $event)"
      />
    </template>

    <!-- Compact mobile card, built from the same row data -->
    <template #card="{ row }">
      <article
        class="w-full rounded-xl border border-border bg-card p-4 shadow-sm"
        :data-testid="`mission-card-${row.id}`"
      >
        <div class="flex items-start justify-between gap-3">
          <h3 class="min-w-0 text-base font-bold leading-tight text-foreground">{{ row.titre }}</h3>
          <span
            class="shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-semibold"
            :class="statusClass(row.status)"
          >
            {{ row.status_label }}
          </span>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
          <span class="rounded-full border border-border bg-muted px-2 py-0.5 text-[11px] font-medium">
            {{ isUgc(row) ? 'UGC' : row.type_mission_label }}
          </span>
          <span class="inline-flex items-center gap-1.5">
            <Users class="h-4 w-4 text-primary/70" aria-hidden="true" />
            {{ row.candidatures_count ?? 0 }} candidature{{ (row.candidatures_count ?? 0) > 1 ? 's' : '' }}
          </span>
          <span class="inline-flex items-center gap-1.5">
            <Calendar class="h-4 w-4 text-primary/70" aria-hidden="true" />
            Limite {{ formatDate(row.date_limite_candidature) }}
          </span>
          <span v-if="!isUgc(row)" class="inline-flex items-center gap-1.5">
            <Calendar class="h-4 w-4 text-primary/70" aria-hidden="true" />
            Tournage {{ formatDate(row.date_tournage) }}
          </span>
          <span class="inline-flex items-center gap-1.5 font-semibold text-foreground">
            <Wallet class="h-4 w-4 text-primary/70" aria-hidden="true" />
            {{ formatCurrency(row.budget) }}
          </span>
        </div>

        <MissionRowActions
          class="mt-3 border-t border-border pt-3"
          :mission="row"
          :email-verified="emailVerified"
          show-labels
          @edit="emit('edit', $event)"
          @delete="emit('delete', $event)"
          @close="emit('close', $event)"
          @reopen="emit('reopen', $event)"
          @complete="emit('complete', $event)"
          @view-candidatures="emit('viewCandidatures', $event)"
          @view-attendance="emit('viewAttendance', $event)"
          @pay-commission="emit('payCommission', $event)"
        />
      </article>
    </template>
  </DataTable>
</template>
