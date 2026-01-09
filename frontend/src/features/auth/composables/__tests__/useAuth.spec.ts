import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

// Mock vue-router
const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
}))

// Mock authApi
const mockLogout = vi.fn()
vi.mock('../../services/authApi', () => ({
  authApi: {
    login: vi.fn(),
    registerFace: vi.fn(),
    registerProducer: vi.fn(),
    logout: (...args: unknown[]) => mockLogout(...args),
  },
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Error'),
}))

// Import useAuth after mocks are set up
import { useAuth } from '../useAuth'

describe('useAuth - logout', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mockLogout.mockResolvedValue(undefined)
  })

  it('calls authApi.logout when logout is called', async () => {
    const { logout } = useAuth()

    await logout()

    expect(mockLogout).toHaveBeenCalled()
  })

  it('clears auth store after logout', async () => {
    const authStore = useAuthStore()
    // Set up initial state
    authStore.setToken('test-token')
    authStore.setUser({
      id: 1,
      email: 'test@example.com',
      userable_type: 'Face',
      userable: null,
      email_verified_at: null,
      created_at: '2026-01-01',
      updated_at: '2026-01-01',
    })

    expect(authStore.isAuthenticated).toBe(true)

    const { logout } = useAuth()
    await logout()

    expect(authStore.token).toBeNull()
    expect(authStore.user).toBeNull()
    expect(authStore.isAuthenticated).toBe(false)
  })

  it('redirects to login page after logout', async () => {
    const { logout } = useAuth()

    await logout()

    expect(mockPush).toHaveBeenCalledWith('/login')
  })

  it('clears local state even if API call fails', async () => {
    mockLogout.mockRejectedValue(new Error('Network error'))

    const authStore = useAuthStore()
    authStore.setToken('test-token')
    authStore.setUser({
      id: 1,
      email: 'test@example.com',
      userable_type: 'Face',
      userable: null,
      email_verified_at: null,
      created_at: '2026-01-01',
      updated_at: '2026-01-01',
    })

    const { logout } = useAuth()
    await logout()

    // Should still clear local state
    expect(authStore.token).toBeNull()
    expect(authStore.user).toBeNull()
    expect(mockPush).toHaveBeenCalledWith('/login')
  })
})
