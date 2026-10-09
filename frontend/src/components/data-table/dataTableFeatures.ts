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

export type DataTableColumn<T extends RowData> = ColumnDef<typeof dataTableFeatures, T>
