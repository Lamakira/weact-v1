import {
  rowPaginationFeature,
  rowSortingFeature,
  tableFeatures,
  type ColumnDef,
  type RowData,
} from '@tanstack/vue-table'

/**
 * Only the features DataTable needs, so the unused ones stay out of the bundle.
 * Sorting and pagination are driven by the server (manual mode): no sorted /
 * paginated row model is registered.
 */
export const dataTableFeatures = tableFeatures({ rowSortingFeature, rowPaginationFeature })

/** Per-column presentation options, read from the column `meta`. */
export interface DataTableColumnMeta {
  /** Pin the column to the right edge (kept visible while the table scrolls horizontally). */
  sticky?: 'right'
  /** Extra classes applied to the header and body cells (e.g. responsive visibility). */
  class?: string
}

export type DataTableColumn<T extends RowData> = ColumnDef<typeof dataTableFeatures, T> & {
  meta?: DataTableColumnMeta
}
