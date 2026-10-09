import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { SortingState } from '@tanstack/vue-table'
import { buildListQuery, parseListQuery, type ListQueryState } from '@/components/data-table/listQuery'
import { BOOKING_SORT_KEYS, BookingFilterLabel } from '../types'
import type { BookingFilterStatus, BookingSortKey } from '../types'
import { useBookingsList } from './useBookingsList'

const FILTER_STATUSES = Object.keys(BookingFilterLabel).filter((key) => key !== '')

/**
 * Shared logic of the Face and Producer bookings list pages.
 *
 * The URL (`?status=&sort=&direction=&page=&per_page=`) is the single source of
 * truth: user actions only rewrite the query, and the `route.query` watcher
 * (guarded by the route name) reloads the list. Keep-alive contract: the first
 * load runs in onMounted; every later return to the cached page changes the
 * query identity, so the watcher fires once and refreshes the list.
 */
export function useBookingsListPage(routeName: string) {
  const route = useRoute()
  const router = useRouter()
  const list = useBookingsList()
  // False until the first fetch settles: avoids flashing the empty state on mount.
  const hasLoaded = ref(false)

  const urlState = computed(() =>
    parseListQuery(route.query, { sorts: BOOKING_SORT_KEYS, statuses: FILTER_STATUSES }),
  )

  const sorting = computed<SortingState>(() =>
    urlState.value.sort ? [{ id: urlState.value.sort, desc: urlState.value.direction === 'desc' }] : [],
  )

  async function load(): Promise<void> {
    const state = urlState.value
    list.applyListState({
      status: state.status as BookingFilterStatus,
      sort: state.sort as BookingSortKey | null,
      direction: state.direction,
      perPage: state.perPage,
    })
    await list.fetchBookings(state.page)
    hasLoaded.value = true
  }

  async function navigate(patch: Partial<ListQueryState>): Promise<void> {
    await router.replace({ query: buildListQuery(route.query, { ...urlState.value, ...patch }) })
  }

  /** Any filter / sort / page size change goes back to page 1. */
  const setStatus = (status: BookingFilterStatus) => navigate({ status, page: 1 })
  const setPerPage = (perPage: number) => navigate({ perPage, page: 1 })
  const setPage = (page: number) => navigate({ page })
  const setSorting = (next: SortingState) =>
    navigate({
      sort: next[0]?.id ?? null,
      direction: next[0]?.desc ? 'desc' : 'asc',
      page: 1,
    })

  onMounted(() => {
    void load()
  })

  watch(
    () => route.query,
    () => {
      // Cached by keep-alive: this watcher keeps firing while the page is
      // off-screen. Act only when actually on this route.
      if (route.name !== routeName) return
      void load()
    },
  )

  return {
    ...list,
    statusFilter: computed(() => urlState.value.status as BookingFilterStatus),
    hasLoaded,
    sorting,
    pageSize: computed(() => urlState.value.perPage),
    load,
    setStatus,
    setPerPage,
    setPage,
    setSorting,
  }
}
