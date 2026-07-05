import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'
import type { User } from '@/features/auth/types'

vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn() },
  getAuthToken: vi.fn(() => null),
  setAuthToken: vi.fn(),
  removeAuthToken: vi.fn(),
}))

function makeUser(id: number): User {
  return {
    id,
    email: `face-${id}@example.test`,
    userable_type: 'Face',
    userable_id: id,
    email_verified: true,
    email_verified_at: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
  }
}

// The registry is module-global: unique keys isolate each test's resource.
let keySeq = 0
function makeCache() {
  keySeq += 1
  return createSharedCachedResource<string | null>({
    key: `auth-spec-${keySeq}`,
    initialValue: null,
    ttlMs: 60_000,
    load: () => Promise.resolve('fresh'),
    getErrorMessage: () => 'err',
  })
}

/**
 * Account isolation of the shared caches: every registered resource holds
 * per-account server state behind a TTL. The auth store is the choke point —
 * clearAuth() (logout / 401 purge) AND setUser() on an identity switch must
 * both purge, or the next account reads the previous account's data (profile
 * fields, subscription status, site-wide payment banner).
 */
describe('auth store — per-account shared-cache hygiene', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('clearAuth() purges every shared cached resource (logout / 401)', () => {
    const store = useAuthStore()
    const cache = makeCache()
    cache.setData('account-A-data')
    expect(cache.hasFreshData()).toBe(true)

    store.clearAuth()

    expect(cache.data.value).toBeNull()
    expect(cache.hasFreshData()).toBe(false)
  })

  it('setUser() with a DIFFERENT user id purges the caches (in-place identity switch)', () => {
    const store = useAuthStore()
    store.setUser(makeUser(1))

    const cache = makeCache()
    cache.setData('account-A-data')

    store.setUser(makeUser(2))

    expect(cache.data.value).toBeNull()
    expect(cache.hasFreshData()).toBe(false)
  })

  it('setUser() with the SAME user id keeps the caches (profile refresh)', () => {
    const store = useAuthStore()
    store.setUser(makeUser(1))

    const cache = makeCache()
    cache.setData('account-A-data')

    store.setUser({ ...makeUser(1), email: 'updated@example.test' })

    expect(cache.data.value).toBe('account-A-data')
    expect(cache.hasFreshData()).toBe(true)
  })

  it('setUser() from a logged-out state (no previous user) keeps the caches', () => {
    const store = useAuthStore()
    const cache = makeCache()
    cache.setData('public-or-fresh-login-data')

    store.setUser(makeUser(1))

    expect(cache.data.value).toBe('public-or-fresh-login-data')
  })
})
