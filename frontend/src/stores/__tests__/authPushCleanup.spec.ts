import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn() },
  getAuthToken: vi.fn(() => null),
  setAuthToken: vi.fn(),
  removeAuthToken: vi.fn(),
}))

const mockUnsubscribeBrowserOnly = vi.fn()
vi.mock('@/features/notification/push/webPush', () => ({
  unsubscribeBrowserOnly: () => mockUnsubscribeBrowserOnly(),
}))

import { useAuthStore } from '../auth'

describe('auth store - web push cleanup', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mockUnsubscribeBrowserOnly.mockResolvedValue(undefined)
  })

  it('clearAuth (401, account deletion…) cuts the browser push subscription', async () => {
    useAuthStore().clearAuth()
    await vi.dynamicImportSettled()

    expect(mockUnsubscribeBrowserOnly).toHaveBeenCalledOnce()
  })

  it('clearAuth never throws nor blocks when the unsubscribe fails', async () => {
    mockUnsubscribeBrowserOnly.mockRejectedValue(new Error('boom'))
    const store = useAuthStore()

    expect(() => store.clearAuth()).not.toThrow()
    await vi.dynamicImportSettled()

    expect(store.token).toBeNull()
  })
})
