import { useRoute } from 'vue-router'
import type { LocationQuery, LocationQueryRaw } from 'vue-router'

/**
 * Keep-alive reload guard shared by the public paginated listings
 * (usePaginatedFaces / usePaginatedMissions / usePaginatedArticles).
 *
 * WHY this exists — these listing pages are cached under <keep-alive>, and
 * under Vue 3.5 a deactivated child's watchers keep running off-screen: they
 * are NOT paused on deactivation, and the pre-flush query watcher even fires
 * BEFORE onDeactivated (see keepAliveQueryGuard.spec). Two query churns must
 * therefore NOT trigger a reload:
 *  - navigating AWAY to a detail page drops the listing's query params →
 *    route.name is no longer the listing → a naive reload would refetch
 *    page 1 and clobber the cached grid;
 *  - returning restores the exact same query → the cache is still valid, so
 *    a refetch is pure waste (skeleton flash + reshuffle of the rotated
 *    public grid).
 * A genuine page/filter change while ON the listing changes the signature and
 * falls through to the reload.
 *
 * Usage: call `markLoaded()` when committing to a fetch for the current query,
 * `markFailed()` when that fetch errors, and bail out of the query watcher
 * when `shouldSkipReload()` returns true.
 */
export function useKeepAliveListingGuard(trackedKeys: readonly string[]) {
  const route = useRoute()

  // Captured at setup — the page IS the active route at its first mount, so
  // this snapshots the listing's own route name without hard-coding the string.
  const listRouteName = route.name

  // Signature of the query the currently-displayed data was fetched for.
  // A plain `let`, not a ref — it is never read reactively.
  let loadedSignature: string | null = null

  /**
   * Normalized signature of a query object, restricted to the tracked keys
   * (built in tracked-key order; empty/non-string values are dropped).
   * Defaults to the current route query.
   */
  function signatureOf(query: LocationQuery | LocationQueryRaw = route.query): string {
    const normalized: Record<string, string> = {}
    for (const key of trackedKeys) {
      const value = query[key]
      if (typeof value === 'string' && value !== '') normalized[key] = value
    }
    return JSON.stringify(normalized)
  }

  /** Record the current route query as the one the displayed data was fetched for. */
  function markLoaded(): void {
    loadedSignature = signatureOf()
  }

  /**
   * A query whose fetch failed is NOT loaded: forget its signature so a
   * keep-alive return retries the fetch instead of restoring the error screen
   * from cache.
   */
  function markFailed(): void {
    loadedSignature = null
  }

  /**
   * True when the query watcher must NOT reload: either the listing is
   * off-screen (route churned away from it), or the current query is exactly
   * the one already loaded (keep-alive return).
   */
  function shouldSkipReload(): boolean {
    if (route.name !== listRouteName) return true
    return signatureOf() === loadedSignature
  }

  return { signatureOf, markLoaded, markFailed, shouldSkipReload }
}
