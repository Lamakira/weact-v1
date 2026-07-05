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
  __resetForTests?: () => void
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

  function resetForTests(): void {
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

    inFlight = options.load()
      .then((result) => {
        setData(result)
        return result
      })
      .catch((err: unknown) => {
        error.value = options.getErrorMessage(err)
        return data.value
      })
      .finally(() => {
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
    __resetForTests: resetForTests,
  }

  registry.set(options.key, resource as SharedCachedResource<unknown>)

  return resource
}

/**
 * Reset every registered shared cache (data → initialValue, marked stale).
 *
 * MUST be called when the authenticated account changes (logout / 401 purge):
 * every registered resource caches per-account server state behind a TTL —
 * without this, an account logging in right after another on the same app
 * instance would read the previous account's data (profile fields,
 * subscription status, and with it the site-wide payment banner).
 */
export function resetAllSharedCachedResources(): void {
  for (const resource of registry.values()) {
    resource.__resetForTests?.()
  }
}

export function resetSharedCachedResourcesForTests(): void {
  resetAllSharedCachedResources()
}
