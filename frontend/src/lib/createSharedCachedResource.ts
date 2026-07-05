import { ref, type Ref } from 'vue'

interface CreateSharedCachedResourceOptions<T> {
  key: string
  initialValue: T
  ttlMs: number
  load: () => Promise<T>
  getErrorMessage: (error: unknown) => string
}

export interface SharedCachedResource<T> {
  data: Ref<T>
  isLoading: Ref<boolean>
  error: Ref<string | null>
  fetch: (options?: { force?: boolean }) => Promise<T>
  setData: (value: T) => void
  mutate: (updater: (current: T) => T) => void
  invalidate: () => void
  clearError: () => void
  hasFreshData: () => boolean
  /** Full reset (data → initialValue, marked stale, in-flight load fenced off). */
  reset: () => void
}

const registry = new Map<string, SharedCachedResource<unknown>>()

/**
 * Create a singleton async resource shared by every consumer of the same key.
 * It keeps data in memory across route changes, avoids duplicate in-flight GETs,
 * and refreshes only when the cached value becomes stale or is explicitly invalidated.
 */
export function createSharedCachedResource<T>(
  options: CreateSharedCachedResourceOptions<T>,
): SharedCachedResource<T> {
  const existing = registry.get(options.key) as SharedCachedResource<T> | undefined

  if (existing) {
    return existing
  }

  const data = ref(options.initialValue) as Ref<T>
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  let lastFetchedAt = 0
  let inFlight: Promise<T> | null = null
  // Bumped by reset(): a load started before the bump must not write anything
  // back (data, error, isLoading, inFlight) — its continuations belong to the
  // previous account/session and would otherwise repopulate the cache AFTER
  // the purge, or clobber the fetch the next account just started.
  let epoch = 0

  function hasFreshData(): boolean {
    return lastFetchedAt > 0 && (Date.now() - lastFetchedAt) < options.ttlMs
  }

  function clearError(): void {
    error.value = null
  }

  function setData(value: T): void {
    data.value = value
    lastFetchedAt = Date.now()
    error.value = null
  }

  function mutate(updater: (current: T) => T): void {
    setData(updater(data.value))
  }

  function invalidate(): void {
    lastFetchedAt = 0
  }

  function reset(): void {
    epoch += 1
    data.value = options.initialValue
    isLoading.value = false
    error.value = null
    lastFetchedAt = 0
    inFlight = null
  }

  async function fetch(fetchOptions: { force?: boolean } = {}): Promise<T> {
    const { force = false } = fetchOptions

    if (!force && hasFreshData()) {
      return data.value
    }

    if (inFlight) {
      return inFlight
    }

    isLoading.value = true
    error.value = null

    const fetchEpoch = epoch
    inFlight = options.load()
      .then((result) => {
        if (fetchEpoch !== epoch) return result
        setData(result)
        return result
      })
      .catch((err: unknown) => {
        if (fetchEpoch !== epoch) return data.value
        error.value = options.getErrorMessage(err)
        return data.value
      })
      .finally(() => {
        if (fetchEpoch !== epoch) return
        isLoading.value = false
        inFlight = null
      })

    return inFlight
  }

  const resource: SharedCachedResource<T> = {
    data,
    isLoading,
    error,
    fetch,
    setData,
    mutate,
    invalidate,
    clearError,
    hasFreshData,
    reset,
  }

  registry.set(options.key, resource as SharedCachedResource<unknown>)

  return resource
}

/**
 * Reset every registered shared cache (data → initialValue, marked stale,
 * in-flight loads fenced off via the epoch counter).
 *
 * MUST be called when the authenticated account changes (logout / 401 purge /
 * in-place identity switch): every registered resource caches per-account
 * server state behind a TTL — without this, an account logging in right after
 * another on the same app instance would read the previous account's data
 * (profile fields, subscription status, and with it the site-wide payment
 * banner).
 */
export function resetAllSharedCachedResources(): void {
  for (const resource of registry.values()) {
    resource.reset()
  }
}
