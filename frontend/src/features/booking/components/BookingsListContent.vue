<script setup lang="ts">
import { computed } from 'vue'
import { CalendarCheck, LayoutGrid, Table2 } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useIsDesktop } from '@/composables/useMediaQuery'
import { useBookingsListPage } from '../composables/useBookingsListPage'
import { useBookingsViewMode } from '../composables/useBookingsViewMode'
import { BookingFilterLabel } from '../types'
import BookingsTable from './BookingsTable.vue'
import BookingStatusFilter from './BookingStatusFilter.vue'

/**
 * Body shared by the Face and Producer bookings pages: status filter, cards /
 * table toggle (md and up) and the server-driven list.
 */
const props = defineProps<{
  role: 'face' | 'producer'
  /** Route name of the hosting page (keep-alive guard of the query watcher). */
  routeName: string
  emptyHint: string
  emptyCtaLabel: string
}>()

const emit = defineEmits<{ 'empty-cta': [] }>()

const authStore = useAuthStore()
const isDesktop = useIsDesktop()
const viewMode = useBookingsViewMode(authStore.user?.id)

const {
  bookings,
  isLoading,
  error,
  currentPage,
  lastPage,
  total,
  isEmpty,
  hasLoaded,
  statusFilter,
  sorting,
  pageSize,
  load,
  setStatus,
  setPage,
  setPerPage,
  setSorting,
} = useBookingsListPage(props.routeName)

// Below md the table is unusable: always cards. From md: the user's choice.
const view = computed<'table' | 'cards'>(() =>
  isDesktop.value && viewMode.value === 'table' ? 'table' : 'cards',
)
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <BookingStatusFilter :model-value="statusFilter" @update:model-value="setStatus" />

      <div
        v-if="isDesktop"
        class="inline-flex rounded-lg border border-border bg-card p-0.5"
        role="group"
        aria-label="Affichage de la liste"
      >
        <button
          type="button"
          data-testid="view-table"
          class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
          :class="viewMode === 'table' ? 'bg-primary text-white' : 'text-muted-foreground hover:text-foreground'"
          :aria-pressed="viewMode === 'table'"
          @click="viewMode = 'table'"
        >
          <Table2 class="h-4 w-4" aria-hidden="true" />
          Tableau
        </button>
        <button
          type="button"
          data-testid="view-cards"
          class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
          :class="viewMode === 'cards' ? 'bg-primary text-white' : 'text-muted-foreground hover:text-foreground'"
          :aria-pressed="viewMode === 'cards'"
          @click="viewMode = 'cards'"
        >
          <LayoutGrid class="h-4 w-4" aria-hidden="true" />
          Cartes
        </button>
      </div>
    </div>

    <BookingsTable
      :bookings="bookings"
      :role="role"
      :sorting="sorting"
      :page="currentPage"
      :page-size="pageSize"
      :total="total"
      :last-page="lastPage"
      :loading="isLoading || !hasLoaded"
      :error="error"
      :view="view"
      @update:sorting="setSorting"
      @page-change="setPage"
      @page-size-change="setPerPage"
      @retry="load"
    >
      <template #empty>
        <div
          v-if="isEmpty"
          class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-muted py-24 text-center"
        >
          <div class="mb-4 rounded-full bg-muted p-4">
            <CalendarCheck class="h-10 w-10 text-muted-foreground" />
          </div>
          <h3 class="text-xl font-bold text-foreground">Pas encore de booking</h3>
          <p class="mt-2 max-w-xs text-muted-foreground">
            <template v-if="statusFilter">
              Aucun booking avec le statut "{{ BookingFilterLabel[statusFilter] }}" trouvé.
            </template>
            <template v-else>
              <span class="whitespace-pre-line">{{ emptyHint }}</span>
            </template>
          </p>
          <button
            v-if="!statusFilter"
            type="button"
            class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90"
            @click="emit('empty-cta')"
          >
            {{ emptyCtaLabel }}
          </button>
        </div>
      </template>
    </BookingsTable>
  </div>
</template>
