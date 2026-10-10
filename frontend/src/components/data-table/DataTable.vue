<script setup lang="ts" generic="T extends RowData">
import { computed } from 'vue'
import {
  FlexRender,
  useTable,
  type PaginationState,
  type RowData,
  type SortingState,
  type Updater,
} from '@tanstack/vue-table'
import { AlertCircle, ArrowDown, ArrowUp, ArrowUpDown, RefreshCw } from 'lucide-vue-next'
import { Pagination } from '@/components/ui/pagination'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { dataTableFeatures, type DataTableColumn, type DataTableColumnMeta } from './dataTableFeatures'
import { PER_PAGE_OPTIONS } from './listQuery'

/**
 * Generic server-driven table (TanStack in manualSorting + manualPagination mode).
 *
 * The parent owns the data and the sort/page state (typically synced to the URL)
 * and reacts to the emitted events. Cells are rendered through `cell-<columnId>`
 * slots; the `card` slot renders the same rows as a card list (mobile / cards view).
 */
const props = withDefaults(
  defineProps<{
    columns: DataTableColumn<T>[]
    data: T[]
    rowId: (row: T) => string
    sorting: SortingState
    /** 1-based current page. */
    page: number
    pageSize: number
    total: number
    lastPage: number
    loading?: boolean
    error?: string | null
    view?: 'table' | 'cards'
    /** Accessible table caption (visually hidden). */
    caption?: string
    /** Rows react to a click (`row-click`); a predicate restricts it to some rows. */
    rowClickable?: boolean | ((row: T) => boolean)
    pageSizeOptions?: readonly number[]
    /** Label of the retry button in the error state. */
    errorTitle?: string
    errorHint?: string
  }>(),
  {
    loading: false,
    error: null,
    view: 'table',
    caption: '',
    rowClickable: false,
    pageSizeOptions: () => PER_PAGE_OPTIONS,
    errorTitle: 'Oups ! Une erreur est survenue',
    errorHint: '',
  },
)

const emit = defineEmits<{
  'update:sorting': [sorting: SortingState]
  'page-change': [page: number]
  'page-size-change': [pageSize: number]
  retry: []
  'row-click': [row: T]
}>()

const sortingState = computed(() => props.sorting)
const paginationState = computed<PaginationState>(() => ({
  pageIndex: Math.max(0, props.page - 1),
  pageSize: props.pageSize,
}))

function resolve<S>(updater: Updater<S>, current: S): S {
  return typeof updater === 'function' ? (updater as (old: S) => S)(current) : updater
}

const table = useTable({
  features: dataTableFeatures,
  columns: computed(() => props.columns),
  data: computed(() => props.data),
  getRowId: (row: T) => props.rowId(row),
  manualSorting: true,
  manualPagination: true,
  enableMultiSort: false,
  sortDescFirst: false,
  pageCount: computed(() => props.lastPage),
  state: {
    get sorting() {
      return sortingState.value
    },
    get pagination() {
      return paginationState.value
    },
  },
  onSortingChange: (updater: Updater<SortingState>) => {
    emit('update:sorting', resolve(updater, props.sorting))
  },
  onPaginationChange: (updater: Updater<PaginationState>) => {
    const next = resolve(updater, paginationState.value)
    if (next.pageSize !== props.pageSize) emit('page-size-change', next.pageSize)
    else if (next.pageIndex !== paginationState.value.pageIndex) emit('page-change', next.pageIndex + 1)
  },
})

const rows = computed(() => table.getRowModel().rows)
const showSkeleton = computed(() => props.loading && props.data.length === 0)
const showError = computed(() => !!props.error && !props.loading)
const isEmpty = computed(() => !props.loading && !props.error && props.data.length === 0)
const hasRows = computed(() => !showSkeleton.value && !showError.value && !isEmpty.value)

function ariaSort(sorted: false | 'asc' | 'desc'): 'none' | 'ascending' | 'descending' {
  if (sorted === 'asc') return 'ascending'
  if (sorted === 'desc') return 'descending'
  return 'none'
}

function metaOf(column: { columnDef: unknown }): DataTableColumnMeta {
  return ((column.columnDef as { meta?: DataTableColumnMeta }).meta ?? {}) as DataTableColumnMeta
}

const STICKY_HEAD = 'sticky right-0 z-10 bg-[color-mix(in_oklab,var(--color-muted)_40%,var(--color-card))] shadow-[-1px_0_0_0_var(--color-border)]'
const STICKY_CELL = 'sticky right-0 z-10 bg-card shadow-[-1px_0_0_0_var(--color-border)]'

function columnClass(column: { columnDef: unknown }, part: 'head' | 'cell'): (string | undefined)[] {
  const meta = metaOf(column)
  return [meta.sticky === 'right' ? (part === 'head' ? STICKY_HEAD : STICKY_CELL) : undefined, meta.class]
}

function onPageChange(page: number): void {
  table.setPageIndex(page - 1)
}

function onPageSizeChange(event: Event): void {
  const size = Number((event.target as HTMLSelectElement).value)
  table.setPageSize(size)
}

function isRowClickable(row: T): boolean {
  return typeof props.rowClickable === 'function' ? props.rowClickable(row) : props.rowClickable
}

function onRowClick(event: MouseEvent, row: T): void {
  if (!isRowClickable(row)) return
  // Inner controls (links, buttons) handle their own click.
  if ((event.target as HTMLElement).closest('a, button, input, select, textarea, label')) return
  emit('row-click', row)
}
</script>

<template>
  <div class="space-y-4" :aria-busy="loading ? 'true' : undefined">
    <!-- Error -->
    <div
      v-if="showError"
      class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/20 bg-destructive/5 py-16 text-center"
      role="alert"
    >
      <div class="mb-4 rounded-full bg-destructive/10 p-4 text-destructive">
        <AlertCircle class="h-10 w-10" />
      </div>
      <h3 class="text-xl font-bold text-foreground">{{ errorTitle }}</h3>
      <p v-if="errorHint" class="mt-2 max-w-xs text-muted-foreground">{{ errorHint }}</p>
      <p class="mt-2 text-sm text-destructive">{{ error }}</p>
      <button
        type="button"
        data-testid="data-table-retry"
        class="mt-6 inline-flex items-center gap-2 rounded-lg border border-border bg-card px-6 py-2 text-sm font-medium transition-colors hover:bg-muted"
        @click="emit('retry')"
      >
        <RefreshCw class="h-4 w-4" />
        Réessayer
      </button>
    </div>

    <!-- Empty -->
    <slot v-else-if="isEmpty" name="empty" />

    <!-- Loading skeleton (first load, nothing to show yet) -->
    <div
      v-else-if="showSkeleton"
      data-testid="data-table-skeleton"
      class="space-y-3"
      role="status"
      aria-label="Chargement"
    >
      <Skeleton v-for="i in 5" :key="i" :class="view === 'table' ? 'h-12 w-full' : 'h-28 w-full rounded-xl'" />
    </div>

    <!-- Table (md and up) -->
    <div
      v-else-if="view === 'table'"
      class="overflow-hidden rounded-xl border border-border bg-card transition-opacity"
      :class="{ 'opacity-60': loading }"
    >
      <Table>
        <caption v-if="caption" class="sr-only">{{ caption }}</caption>
        <TableHeader class="bg-muted/40">
          <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id" class="hover:bg-transparent">
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              scope="col"
              :aria-sort="header.column.getCanSort() ? ariaSort(header.column.getIsSorted()) : undefined"
              :class="columnClass(header.column, 'head')"
              class="px-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
            >
              <button
                v-if="header.column.getCanSort() && !header.isPlaceholder"
                type="button"
                class="-mx-2 inline-flex items-center gap-1.5 rounded-md px-2 py-1 uppercase tracking-wide transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                :class="{ 'text-foreground': header.column.getIsSorted() }"
                @click="header.column.getToggleSortingHandler()?.($event)"
              >
                <FlexRender :header="header" />
                <ArrowUp v-if="header.column.getIsSorted() === 'asc'" class="h-3.5 w-3.5" aria-hidden="true" />
                <ArrowDown v-else-if="header.column.getIsSorted() === 'desc'" class="h-3.5 w-3.5" aria-hidden="true" />
                <ArrowUpDown v-else class="h-3.5 w-3.5 opacity-50" aria-hidden="true" />
              </button>
              <FlexRender v-else-if="!header.isPlaceholder" :header="header" />
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="row in rows"
            :key="row.id"
            :class="{ 'cursor-pointer': isRowClickable(row.original) }"
            @click="onRowClick($event, row.original)"
          >
            <TableCell
              v-for="cell in row.getAllCells()"
              :key="cell.id"
              :class="columnClass(cell.column, 'cell')"
              class="px-3 py-3"
            >
              <slot :name="`cell-${cell.column.id}`" :row="row.original">
                {{ cell.getValue() }}
              </slot>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Cards (mobile / cards view) -->
    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" :class="{ 'opacity-60': loading }">
      <li v-for="row in rows" :key="row.id" class="flex [&>*]:w-full">
        <slot name="card" :row="row.original" />
      </li>
    </ul>

    <!-- Footer: count, page size, pagination -->
    <div v-if="hasRows" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
      <div class="flex items-center gap-3 text-sm text-muted-foreground">
        <span>{{ total }} résultat{{ total > 1 ? 's' : '' }}</span>
        <label class="inline-flex items-center gap-2">
          <span>Par page</span>
          <select
            data-testid="data-table-page-size"
            class="rounded-md border border-border bg-card px-2 py-1 text-sm text-foreground"
            :value="pageSize"
            @change="onPageSizeChange"
          >
            <option v-for="size in pageSizeOptions" :key="size" :value="size">{{ size }}</option>
          </select>
        </label>
      </div>
      <Pagination
        v-if="lastPage > 1"
        :current-page="page"
        :total-pages="lastPage"
        @page-change="onPageChange"
      />
    </div>
  </div>
</template>
