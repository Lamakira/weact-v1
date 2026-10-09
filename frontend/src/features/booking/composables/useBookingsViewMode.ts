import { ref, watch, type Ref } from 'vue'

export type BookingsViewMode = 'table' | 'cards'

const STORAGE_PREFIX = 'weact:bookings-view:'

function readStored(key: string): BookingsViewMode {
  try {
    const stored = localStorage.getItem(key)
    return stored === 'cards' ? 'cards' : 'table'
  } catch {
    // Storage blocked (private mode, quota): fall back to the default.
    return 'table'
  }
}

/**
 * Cards / table preference of the bookings list (md and up), persisted per user.
 * Default: table.
 */
export function useBookingsViewMode(userId: number | string | null | undefined): Ref<BookingsViewMode> {
  const key = `${STORAGE_PREFIX}${userId ?? 'anonymous'}`
  const mode = ref<BookingsViewMode>(readStored(key))

  watch(mode, (value) => {
    try {
      localStorage.setItem(key, value)
    } catch {
      // Not persisted: the choice still applies for this session.
    }
  })

  return mode
}
