import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAdminAuthStore } from '@/stores/adminAuth'

// Mock vue-router
const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
}))

// Mock adminAuthApi
const mockLogin = vi.fn()
const mockLogout = vi.fn()
const mockVerifyTwoFactor = vi.fn()
vi.mock('../../services/adminAuthApi', () => ({
  adminAuthApi: {
    login: (...args: unknown[]) => mockLogin(...args),
    logout: (...args: unknown[]) => mockLogout(...args),
    verifyTwoFactor: (...args: unknown[]) => mockVerifyTwoFactor(...args),
    getMe: vi.fn(),
  },
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Error'),
}))

// Import useAdminAuth after mocks are set up
import { useAdminAuth } from '../useAdminAuth'

describe('useAdminAuth - login', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('stores token and admin on successful login', async () => {
    mockLogin.mockResolvedValue({
      data: {
        admin: { id: 1, name: 'Admin', email: 'admin@weact.bj' },
        token: 'test-admin-token',
      },
      message: 'Connexion admin réussie',
      meta: {},
    })

    const { login } = useAdminAuth()
    const result = await login({ email: 'admin@weact.bj', password: 'password' })

    expect(result.success).toBe(true)

    const adminAuthStore = useAdminAuthStore()
    expect(adminAuthStore.token).toBe('test-admin-token')
    expect(adminAuthStore.admin).toEqual({ id: 1, name: 'Admin', email: 'admin@weact.bj' })
    expect(adminAuthStore.isAuthenticated).toBe(true)
  })

  it('returns errors on failed login', async () => {
    const apiError = {
      response: {
        data: {
          error: { message: 'Identifiants invalides', code: 'AUTH_FAILED' },
          errors: { email: ['Email invalide'] },
        },
      },
    }
    mockLogin.mockRejectedValue(apiError)

    const { login } = useAdminAuth()
    const result = await login({ email: 'bad@email.com', password: 'wrong' })

    expect(result.success).toBe(false)
    expect(result.message).toBeDefined()

    const adminAuthStore = useAdminAuthStore()
    expect(adminAuthStore.isAuthenticated).toBe(false)
  })

  it('sets loading state during login', async () => {
    let resolveLogin: (value: unknown) => void
    mockLogin.mockReturnValue(
      new Promise((resolve) => {
        resolveLogin = resolve
      }),
    )

    const adminAuthStore = useAdminAuthStore()
    const { login } = useAdminAuth()

    const loginPromise = login({ email: 'admin@weact.bj', password: 'password' })

    // Loading should be true while in-flight
    expect(adminAuthStore.isLoading).toBe(true)

    resolveLogin!({
      data: {
        admin: { id: 1, name: 'Admin', email: 'admin@weact.bj' },
        token: 'token',
      },
      message: 'OK',
      meta: {},
    })

    await loginPromise

    // Loading should be false after completion
    expect(adminAuthStore.isLoading).toBe(false)
  })
})

describe('useAdminAuth - two-factor login', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('returns the challenge and stores NO token when the backend requires 2FA', async () => {
    mockLogin.mockResolvedValue({
      data: { two_factor_required: true, challenge: 'challenge-123' },
      message: 'Code de vérification requis',
      meta: {},
    })

    const { login } = useAdminAuth()
    const result = await login({ email: 'admin@weact.bj', password: 'password' })

    expect(result.success).toBe(false)
    expect(result.twoFactorChallenge).toBe('challenge-123')

    const adminAuthStore = useAdminAuthStore()
    expect(adminAuthStore.token).toBeNull()
    expect(adminAuthStore.isAuthenticated).toBe(false)
  })

  it('stores token and admin once the second step succeeds', async () => {
    mockVerifyTwoFactor.mockResolvedValue({
      data: {
        admin: { id: 'a1', name: 'Admin', email: 'admin@weact.bj', role: 'admin', two_factor_enabled: true },
        token: 'final-token',
      },
      message: 'Connexion admin réussie',
      meta: {},
    })

    const { verifyTwoFactor } = useAdminAuth()
    const result = await verifyTwoFactor({ challenge: 'challenge-123', code: '123456' })

    expect(mockVerifyTwoFactor).toHaveBeenCalledWith({ challenge: 'challenge-123', code: '123456' })
    expect(result.success).toBe(true)

    const adminAuthStore = useAdminAuthStore()
    expect(adminAuthStore.token).toBe('final-token')
    expect(adminAuthStore.admin?.two_factor_enabled).toBe(true)
  })

  it('reports the error code and stores nothing when the code is wrong', async () => {
    mockVerifyTwoFactor.mockRejectedValue({
      response: {
        data: { error: { message: 'Code de vérification incorrect', code: 'TWO_FACTOR_INVALID_CODE' } },
      },
    })

    const { verifyTwoFactor } = useAdminAuth()
    const result = await verifyTwoFactor({ challenge: 'challenge-123', code: '000000' })

    expect(result.success).toBe(false)
    expect(useAdminAuthStore().token).toBeNull()
  })
})

describe('useAdminAuth - logout', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
    mockLogout.mockResolvedValue(undefined)
  })

  it('calls adminAuthApi.logout when logout is called', async () => {
    const { logout } = useAdminAuth()

    await logout()

    expect(mockLogout).toHaveBeenCalled()
  })

  it('clears admin auth store after logout', async () => {
    const adminAuthStore = useAdminAuthStore()
    adminAuthStore.setToken('test-token')
    adminAuthStore.setAdmin({ id: 1, name: 'Admin', email: 'admin@weact.bj' })

    expect(adminAuthStore.isAuthenticated).toBe(true)

    const { logout } = useAdminAuth()
    await logout()

    expect(adminAuthStore.token).toBeNull()
    expect(adminAuthStore.admin).toBeNull()
    expect(adminAuthStore.isAuthenticated).toBe(false)
  })

  it('redirects to admin-login page after logout', async () => {
    const { logout } = useAdminAuth()

    await logout()

    expect(mockPush).toHaveBeenCalledWith({ name: 'admin-login' })
  })

  it('clears local state even if API call fails', async () => {
    mockLogout.mockRejectedValue(new Error('Network error'))

    const adminAuthStore = useAdminAuthStore()
    adminAuthStore.setToken('test-token')
    adminAuthStore.setAdmin({ id: 1, name: 'Admin', email: 'admin@weact.bj' })

    const { logout } = useAdminAuth()
    await logout()

    // Should still clear local state
    expect(adminAuthStore.token).toBeNull()
    expect(adminAuthStore.admin).toBeNull()
    expect(mockPush).toHaveBeenCalledWith({ name: 'admin-login' })
  })

  it('sets loading state during logout', async () => {
    const adminAuthStore = useAdminAuthStore()
    const { logout } = useAdminAuth()

    // After logout completes, loading should be false
    await logout()

    expect(adminAuthStore.isLoading).toBe(false)
  })
})
