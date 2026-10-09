import type { LocationQuery, LocationQueryRaw } from 'vue-router'

export type SortDirection = 'asc' | 'desc'

export const PER_PAGE_OPTIONS = [10, 15, 25, 50] as const
export const DEFAULT_PER_PAGE = 15

/** Query keys owned by the list state; every other key is left untouched. */
export const LIST_QUERY_KEYS = ['page', 'per_page', 'sort', 'direction', 'status'] as const

export interface ListQueryState {
  page: number
  perPage: number
  sort: string | null
  direction: SortDirection
  status: string
}

export interface ListQueryOptions {
  /** Sort keys accepted by the API for this list. */
  sorts: readonly string[]
  /** Status filter values accepted for this list ('' is always allowed). */
  statuses: readonly string[]
}

function firstString(value: unknown): string {
  const v = Array.isArray(value) ? value[0] : value
  return typeof v === 'string' ? v : ''
}

/**
 * Parse `?page=&per_page=&sort=&direction=&status=` into a safe state:
 * unknown values fall back to defaults instead of reaching the API (422).
 */
export function parseListQuery(query: LocationQuery, options: ListQueryOptions): ListQueryState {
  const page = parseInt(firstString(query.page), 10)
  const perPage = parseInt(firstString(query.per_page), 10)
  const sort = firstString(query.sort)
  const status = firstString(query.status)
  const sortIsValid = options.sorts.includes(sort)

  return {
    page: Number.isFinite(page) && page >= 1 ? page : 1,
    perPage: (PER_PAGE_OPTIONS as readonly number[]).includes(perPage) ? perPage : DEFAULT_PER_PAGE,
    sort: sortIsValid ? sort : null,
    direction: sortIsValid && firstString(query.direction) === 'desc' ? 'desc' : 'asc',
    status: options.statuses.includes(status) ? status : '',
  }
}

/**
 * Next URL query: foreign keys of `current` are kept, list keys are rewritten
 * from `state` (defaults are omitted to keep URLs short).
 */
export function buildListQuery(current: LocationQuery, state: ListQueryState): LocationQueryRaw {
  const next: LocationQueryRaw = {}
  for (const [key, value] of Object.entries(current)) {
    if (!(LIST_QUERY_KEYS as readonly string[]).includes(key)) next[key] = value
  }

  if (state.status) next.status = state.status
  if (state.sort) {
    next.sort = state.sort
    next.direction = state.direction
  }
  if (state.perPage !== DEFAULT_PER_PAGE) next.per_page = String(state.perPage)
  if (state.page > 1) next.page = String(state.page)

  return next
}
