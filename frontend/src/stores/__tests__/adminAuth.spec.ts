import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock adminApiClient storage helpers
vi.mock('@/features/admin/services/adminApiClient', () => ({
  getAdminAuthToken: vi.fn(() => null),
  setAdminAuthToken: vi.fn(),
  removeAdminAuthToken: vi.fn(),
  getStoredAdmin: vi.fn(() => null),
  setStoredAdmin: vi.fn(),
  removeStoredAdmin: vi.fn(),
}))

// Mock adminAuthApi
vi.mock('@/features/admin/services/adminAuthApi', () => ({
  adminAuthApi: {
    getMe: vi.fn(),
  },
}))

import { useAdminAuthStore } from '../adminAuth'
import {
  setAdminAuthToken,
  removeAdminAuthToken,
  setStoredAdmin,
  removeStoredAdmin,
} from '@/features/admin/services/adminApiClient'
import { adminAuthApi } from '@/features/admin/services/adminAuthApi'

describe('useAdminAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('starts with null admin and token', () => {
      const store = useAdminAuthStore()

      expect(store.admin).toBeNull()
      expect(store.token).toBeNull()
      expect(store.isLoading).toBe(false)
    })

    it('is not authenticated when token or admin is null', () => {
      const store = useAdminAuthStore()

      expect(store.isAuthenticated).toBe(false)
    })
  })

  describe('setAdmin', () => {
    it('stores admin data in state and localStorage', () => {
      const store = useAdminAuthStore()
      const admin = { id: 1, name: 'Admin', email: 'admin@weact.bj' }

      store.setAdmin(admin)

      expect(store.admin).toEqual(admin)
      expect(setStoredAdmin).toHaveBeenCalledWith(admin)
    })
  })

  describe('setToken', () => {
    it('stores token in state and localStorage', () => {
      const store = useAdminAuthStore()

      store.setToken('test-token')

      expect(store.token).toBe('test-token')
      expect(setAdminAuthToken).toHaveBeenCalledWith('test-token')
    })
  })

  describe('isAuthenticated', () => {
    it('returns true when both token and admin are present', () => {
      const store = useAdminAuthStore()

      store.setToken('test-token')
      store.setAdmin({ id: 1, name: 'Admin', email: 'admin@weact.bj' })

      expect(store.isAuthenticated).toBe(true)
    })

    it('returns false when only token is present', () => {
      const store = useAdminAuthStore()

      store.setToken('test-token')

      expect(store.isAuthenticated).toBe(false)
    })

    it('returns false when only admin is present', () => {
      const store = useAdminAuthStore()

      store.setAdmin({ id: 1, name: 'Admin', email: 'admin@weact.bj' })

      expect(store.isAuthenticated).toBe(false)
    })
  })

  describe('computed getters', () => {
    it('adminName returns name when admin is set', () => {
      const store = useAdminAuthStore()

      store.setAdmin({ id: 1, name: 'Jean Admin', email: 'jean@weact.bj' })

      expect(store.adminName).toBe('Jean Admin')
    })

    it('adminName returns empty string when no admin', () => {
      const store = useAdminAuthStore()

      expect(store.adminName).toBe('')
    })

    it('adminEmail returns email when admin is set', () => {
      const store = useAdminAuthStore()

      store.setAdmin({ id: 1, name: 'Admin', email: 'jean@weact.bj' })

      expect(store.adminEmail).toBe('jean@weact.bj')
    })
  })

  describe('clearAuth', () => {
    it('clears admin, token, and localStorage', () => {
      const store = useAdminAuthStore()

      store.setToken('test-token')
      store.setAdmin({ id: 1, name: 'Admin', email: 'admin@weact.bj' })

      store.clearAuth()

      expect(store.admin).toBeNull()
      expect(store.token).toBeNull()
      expect(removeAdminAuthToken).toHaveBeenCalled()
      expect(removeStoredAdmin).toHaveBeenCalled()
    })
  })

  describe('refreshAdmin', () => {
    it('returns false when no token', async () => {
      const store = useAdminAuthStore()

      const result = await store.refreshAdmin()

      expect(result).toBe(false)
    })

    it('updates admin data from API on success', async () => {
      const store = useAdminAuthStore()
      store.setToken('test-token')

      const apiAdmin = { id: 1, name: 'Updated Admin', email: 'updated@weact.bj' }
      vi.mocked(adminAuthApi.getMe).mockResolvedValue({
        data: apiAdmin,
        message: 'OK',
      })

      const result = await store.refreshAdmin()

      expect(result).toBe(true)
      expect(store.admin).toEqual(apiAdmin)
    })

    it('returns false on API error', async () => {
      const store = useAdminAuthStore()
      store.setToken('test-token')

      vi.mocked(adminAuthApi.getMe).mockRejectedValue(new Error('Network error'))

      const result = await store.refreshAdmin()

      expect(result).toBe(false)
    })
  })

  describe('$reset', () => {
    it('clears all state including loading', () => {
      const store = useAdminAuthStore()

      store.setToken('test-token')
      store.setAdmin({ id: 1, name: 'Admin', email: 'admin@weact.bj' })
      store.setLoading(true)

      store.$reset()

      expect(store.admin).toBeNull()
      expect(store.token).toBeNull()
      expect(store.isLoading).toBe(false)
    })
  })
})
