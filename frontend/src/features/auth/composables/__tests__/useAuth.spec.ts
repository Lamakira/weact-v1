import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useUgcValidationCountStore } from '@/stores/ugcValidationCount'
import { useMessagesUnreadStore } from '@/stores/messagesUnread'
import { captureAnalyticsEvent } from '@/lib/analytics'
import { authApi } from '../../services/authApi'

vi.mock('@/lib/analytics', () => ({ captureAnalyticsEvent: vi.fn() }))

// Le store de notifications appelle l'API (compteur non lu, canal temps réel) après une
// inscription : simulé ici pour qu'aucune vraie requête ne parte.
vi.mock('@/stores/notification', () => ({
  useNotificationStore: () => ({
    subscribe: vi.fn(),
    unsubscribe: vi.fn(),
    fetchUnreadCount: vi.fn(),
  }),
}))

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

// Mock web push device unsubscribe (shared phone: stop pushes of the previous account)
const mockUnsubscribeDevice = vi.fn()
vi.mock('@/features/notification/push/webPush', () => ({
  unsubscribeThisDevice: (...args: unknown[]) => mockUnsubscribeDevice(...args),
}))

// Import useAuth after mocks are set up
import { useAuth } from '../useAuth'

describe('useAuth - logout', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mockLogout.mockResolvedValue(undefined)
    mockUnsubscribeDevice.mockResolvedValue(undefined)
  })

  it('unsubscribes the device from web push BEFORE revoking the token', async () => {
    const order: string[] = []
    mockUnsubscribeDevice.mockImplementation(async () => {
      order.push('push')
    })
    mockLogout.mockImplementation(async () => {
      order.push('logout')
    })

    const { logout } = useAuth()
    await logout()

    expect(order).toEqual(['push', 'logout'])
  })

  it('still logs out when the push unsubscribe throws', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => undefined)
    mockUnsubscribeDevice.mockRejectedValue(new Error('push down'))

    const { logout } = useAuth()
    await logout()

    expect(mockLogout).toHaveBeenCalled()
    expect(mockPush).toHaveBeenCalledWith('/login')
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

  it('resets the UGC validation count on logout (no cross-account leak)', async () => {
    // Simule un Producteur A avec des livrables en attente déjà chargés.
    const ugcValidationCountStore = useUgcValidationCountStore()
    ugcValidationCountStore.setCount(5)
    expect(ugcValidationCountStore.count).toBe(5)

    const { logout } = useAuth()
    await logout()

    // Le compteur ne doit pas survivre au logout : le compte suivant repart de 0.
    expect(ugcValidationCountStore.count).toBe(0)
  })

  it('resets the Messages unread badge on logout (no cross-account leak)', async () => {
    const messagesUnreadStore = useMessagesUnreadStore()
    messagesUnreadStore.setCount(7)
    expect(messagesUnreadStore.count).toBe(7)

    const { logout } = useAuth()
    await logout()

    expect(messagesUnreadStore.count).toBe(0)
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

describe('useAuth - analytics on email signup', () => {
  const user = (type: 'Face' | 'Producer') => ({
    id: 1,
    email: 'test@example.com',
    userable_type: type,
    userable: null,
    email_verified_at: null,
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
  })

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('registerFace success sends inscription_reussie for a face', async () => {
    vi.mocked(authApi.registerFace).mockResolvedValue({
      data: { token: 't', user: user('Face') },
    } as never)

    const result = await useAuth().registerFace({} as never)

    expect(result.success).toBe(true)
    expect(captureAnalyticsEvent).toHaveBeenCalledTimes(1)
    expect(captureAnalyticsEvent).toHaveBeenCalledWith('inscription_reussie', {
      compte: 'face',
      methode: 'email',
    })
  })

  it('registerProducer success sends inscription_reussie for a producteur', async () => {
    vi.mocked(authApi.registerProducer).mockResolvedValue({
      data: { token: 't', user: user('Producer') },
    } as never)

    const result = await useAuth().registerProducer({} as never)

    expect(result.success).toBe(true)
    expect(captureAnalyticsEvent).toHaveBeenCalledTimes(1)
    expect(captureAnalyticsEvent).toHaveBeenCalledWith('inscription_reussie', {
      compte: 'producteur',
      methode: 'email',
    })
  })

  it('sends nothing when the registration fails', async () => {
    vi.mocked(authApi.registerFace).mockRejectedValue(new Error('422'))
    vi.mocked(authApi.registerProducer).mockRejectedValue(new Error('422'))

    const face = await useAuth().registerFace({} as never)
    const producer = await useAuth().registerProducer({} as never)

    expect(face.success).toBe(false)
    expect(producer.success).toBe(false)
    expect(captureAnalyticsEvent).not.toHaveBeenCalled()
  })
})
})
