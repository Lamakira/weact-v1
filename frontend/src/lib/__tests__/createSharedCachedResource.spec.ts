import { describe, it, expect, vi } from 'vitest'
import {
  createSharedCachedResource,
  resetAllSharedCachedResources,
} from '../createSharedCachedResource'

/**
 * The epoch fence: reset() must neutralize the continuations of a load that
 * was in flight when the reset happened (account-switch purge at logout/401).
 * Without it, the stale .then(setData) repopulates the cache with the
 * previous account's data AFTER the purge — the exact cross-account leak the
 * reset exists to prevent — and the stale .catch/.finally clobber the fetch
 * the next account just started.
 */

type Deferred<T> = { promise: Promise<T>; resolve: (v: T) => void; reject: (e: unknown) => void }
function deferred<T>(): Deferred<T> {
  let resolve!: (v: T) => void
  let reject!: (e: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

let keySeq = 0
function makeResource(load: () => Promise<string>) {
  // The registry is module-global: a unique key per test isolates them.
  keySeq += 1
  return createSharedCachedResource<string | null>({
    key: `epoch-test-${keySeq}`,
    initialValue: null,
    ttlMs: 60_000,
    load,
    getErrorMessage: () => 'boom',
  })
}

describe('createSharedCachedResource — epoch fence on reset()', () => {
  it('a load resolving AFTER reset() does not repopulate the cache', async () => {
    const d = deferred<string>()
    const resource = makeResource(() => d.promise)

    const pending = resource.fetch()
    resource.reset()

    d.resolve('account-A-data')
    await pending

    expect(resource.data.value).toBeNull()
    expect(resource.hasFreshData()).toBe(false)
    expect(resource.isLoading.value).toBe(false)
  })

  it('a load rejecting AFTER reset() does not write a stale error', async () => {
    const d = deferred<string>()
    const resource = makeResource(() => d.promise)

    const pending = resource.fetch()
    resource.reset()

    d.reject(new Error('401 from the revoked session'))
    await pending

    expect(resource.error.value).toBeNull()
    expect(resource.data.value).toBeNull()
  })

  it('the stale .finally does not clobber the NEXT fetch (no duplicate load, no premature isLoading flip)', async () => {
    const first = deferred<string>()
    const second = deferred<string>()
    const load = vi
      .fn<() => Promise<string>>()
      .mockReturnValueOnce(first.promise)
      .mockReturnValueOnce(second.promise)
    const resource = makeResource(load)

    const pendingFirst = resource.fetch() // account A, in flight
    resource.reset() // logout purge
    const pendingSecond = resource.fetch() // account B starts its own load
    expect(load).toHaveBeenCalledTimes(2)

    // A's slow response lands while B's is still pending.
    first.resolve('account-A-data')
    await pendingFirst

    expect(resource.isLoading.value).toBe(true)
    // A third consumer must join B's in-flight load, not start a duplicate GET.
    void resource.fetch()
    expect(load).toHaveBeenCalledTimes(2)

    second.resolve('account-B-data')
    await pendingSecond
    expect(resource.data.value).toBe('account-B-data')
    expect(resource.isLoading.value).toBe(false)
  })

  it('an out-of-order stale response cannot overwrite the next account\'s already-fetched data', async () => {
    const first = deferred<string>()
    const second = deferred<string>()
    const load = vi
      .fn<() => Promise<string>>()
      .mockReturnValueOnce(first.promise)
      .mockReturnValueOnce(second.promise)
    const resource = makeResource(load)

    const pendingFirst = resource.fetch() // account A, slow
    resource.reset()
    const pendingSecond = resource.fetch() // account B, fast

    second.resolve('account-B-data')
    await pendingSecond
    expect(resource.data.value).toBe('account-B-data')

    first.resolve('account-A-data')
    await pendingFirst
    expect(resource.data.value).toBe('account-B-data')
  })

  it('resetAllSharedCachedResources() resets every registered resource', async () => {
    const a = makeResource(() => Promise.resolve('a'))
    const b = makeResource(() => Promise.resolve('b'))
    await a.fetch()
    await b.fetch()
    expect(a.data.value).toBe('a')
    expect(b.data.value).toBe('b')

    resetAllSharedCachedResources()

    expect(a.data.value).toBeNull()
    expect(b.data.value).toBeNull()
    expect(a.hasFreshData()).toBe(false)
    expect(b.hasFreshData()).toBe(false)
  })
})
