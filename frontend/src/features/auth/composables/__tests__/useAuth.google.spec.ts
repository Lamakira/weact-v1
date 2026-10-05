import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import type { User } from '../../types'

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
}))

// Only the API module is mocked: the composable and both stores are the real ones.
const h = vi.hoisted(() => ({
  login: vi.fn(),
  exchangeGoogleCode: vi.fn(),
  completeGoogleRegistration: vi.fn(),
}))

vi.mock('../../services/authApi', () => ({
  authApi: {
    login: (...args: unknown[]) => h.login(...args),
    exchangeGoogleCode: (...args: unknown[]) => h.exchangeGoogleCode(...args),
    completeGoogleRegistration: (...args: unknown[]) => h.completeGoogleRegistration(...args),
  },
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Error'),
  getApiErrorCode: vi.fn(() => null),
}))

import { useAuth } from '../useAuth'

const makeUser = (id: number): User => ({
  id,
  email: `user${id}@example.com`,
  userable_type: 'Face',
  userable: null,
  email_verified_at: null,
  created_at: '2026-01-01',
  updated_at: '2026-01-01',
})

function storageSnapshot(): Record<string, string | null> {
  const snapshot: Record<string, string | null> = {}
  for (let i = 0; i < localStorage.length; i += 1) {
    const key = localStorage.key(i)
    if (key !== null) snapshot[key] = localStorage.getItem(key)
  }

  return snapshot
}

describe('useAuth - Google session adoption', () => {
  let calls: string[]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
    sessionStorage.clear()
    calls = []

    const notificationStore = useNotificationStore()
    vi.spyOn(notificationStore, 'subscribe').mockImplementation(() => {
      calls.push('subscribe')
    })
    vi.spyOn(notificationStore, 'unsubscribe').mockImplementation(() => {
      calls.push('unsubscribe')
    })
    vi.spyOn(notificationStore, 'fetchUnreadCount').mockImplementation(async () => {
      calls.push('fetchUnreadCount')
    })
  })

  async function passwordLoginSnapshot(): Promise<Record<string, string | null>> {
    h.login.mockResolvedValue({ data: { token: 'tok-1', user: makeUser(1) } })
    await useAuth().login({ email: 'user1@example.com', password: 'x' } as never)
    const snapshot = storageSnapshot()

    // Fresh state for the Google run.
    localStorage.clear()
    setActivePinia(createPinia())
    const notificationStore = useNotificationStore()
    vi.spyOn(notificationStore, 'subscribe').mockImplementation(() => {
      calls.push('subscribe')
    })
    vi.spyOn(notificationStore, 'unsubscribe').mockImplementation(() => {
      calls.push('unsubscribe')
    })
    vi.spyOn(notificationStore, 'fetchUnreadCount').mockImplementation(async () => {
      calls.push('fetchUnreadCount')
    })
    calls.length = 0

    return snapshot
  }

  describe('exchangeGoogleCode', () => {
    it('stores token and user exactly like a password login on an authenticated result', async () => {
      const expected = await passwordLoginSnapshot()
      h.exchangeGoogleCode.mockResolvedValue({
        needs_completion: false,
        redirect: null,
        token: 'tok-1',
        user: makeUser(1),
      })

      const outcome = await useAuth().exchangeGoogleCode('code', 'nonce')

      expect(outcome.success).toBe(true)
      expect(storageSnapshot()).toEqual(expected)
      expect(Object.keys(expected).length).toBeGreaterThanOrEqual(2)
      const authStore = useAuthStore()
      expect(authStore.token).toBe('tok-1')
      expect(authStore.user?.id).toBe(1)
      expect(calls).toEqual(['subscribe', 'fetchUnreadCount'])
    })

    it('stores no token on needs_completion', async () => {
      h.exchangeGoogleCode.mockResolvedValue({
        needs_completion: true,
        redirect: null,
        pending_token: 'pending-abc',
        email: 'jean@gmail.com',
      })

      await useAuth().exchangeGoogleCode('code', 'nonce')

      expect(useAuthStore().token).toBeNull()
      expect(useAuthStore().user).toBeNull()
      expect(storageSnapshot()).toEqual({})
      expect(calls).toEqual([])
    })

    it('stores no token on a reauth result', async () => {
      h.exchangeGoogleCode.mockResolvedValue({ redirect: '/face/profile', reauth_token: 'reauth-abc' })

      await useAuth().exchangeGoogleCode('code', 'nonce')

      expect(useAuthStore().token).toBeNull()
      expect(useAuthStore().user).toBeNull()
      expect(storageSnapshot()).toEqual({})
      expect(calls).toEqual([])
    })
  })

  describe('completeGoogleRegistration', () => {
    it('stores token and user exactly like a password login', async () => {
      const expected = await passwordLoginSnapshot()
      h.completeGoogleRegistration.mockResolvedValue({
        data: { token: 'tok-1', user: makeUser(1) },
      })

      const result = await useAuth().completeGoogleRegistration({} as never)

      expect(result.success).toBe(true)
      expect(storageSnapshot()).toEqual(expected)
      expect(useAuthStore().token).toBe('tok-1')
      expect(calls).toEqual(['subscribe', 'fetchUnreadCount'])
    })

    it('stores no token when the completion fails', async () => {
      h.completeGoogleRegistration.mockRejectedValue(new Error('422'))

      const result = await useAuth().completeGoogleRegistration({} as never)

      expect(result.success).toBe(false)
      expect(useAuthStore().token).toBeNull()
      expect(storageSnapshot()).toEqual({})
    })
  })

  describe('in-place account switch', () => {
    it('unsubscribes the previous account before subscribing the adopted one', async () => {
      const authStore = useAuthStore()
      authStore.setToken('tok-old')
      authStore.setUser(makeUser(1))
      h.exchangeGoogleCode.mockResolvedValue({
        needs_completion: false,
        redirect: null,
        token: 'tok-new',
        user: makeUser(2),
      })

      await useAuth().exchangeGoogleCode('code', 'nonce')

      expect(calls).toEqual(['unsubscribe', 'subscribe', 'fetchUnreadCount'])
      expect(authStore.user?.id).toBe(2)
    })

    it('unsubscribes while the previous user is still the current one (channel name)', async () => {
      const authStore = useAuthStore()
      authStore.setToken('tok-old')
      authStore.setUser(makeUser(1))
      const notificationStore = useNotificationStore()
      let idAtUnsubscribe: number | undefined
      vi.spyOn(notificationStore, 'unsubscribe').mockImplementation(() => {
        idAtUnsubscribe = authStore.user?.id
      })
      h.completeGoogleRegistration.mockResolvedValue({
        data: { token: 'tok-new', user: makeUser(2) },
      })

      await useAuth().completeGoogleRegistration({} as never)

      expect(idAtUnsubscribe).toBe(1)
    })

    it('does not unsubscribe when the same account is adopted again', async () => {
      const authStore = useAuthStore()
      authStore.setToken('tok-old')
      authStore.setUser(makeUser(1))
      h.exchangeGoogleCode.mockResolvedValue({
        needs_completion: false,
        redirect: null,
        token: 'tok-new',
        user: makeUser(1),
      })

      await useAuth().exchangeGoogleCode('code', 'nonce')

      expect(calls).toEqual(['subscribe', 'fetchUnreadCount'])
    })

    it('does not unsubscribe on a first login', async () => {
      h.exchangeGoogleCode.mockResolvedValue({
        needs_completion: false,
        redirect: null,
        token: 'tok-1',
        user: makeUser(1),
      })

      await useAuth().exchangeGoogleCode('code', 'nonce')

      expect(calls).not.toContain('unsubscribe')
    })
  })
})
