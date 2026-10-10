import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

const mockGet = vi.fn()
const mockPost = vi.fn()
const mockDelete = vi.fn()
vi.mock('@/services/apiClient', () => ({
  default: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
    delete: (...args: unknown[]) => mockDelete(...args),
  },
}))

import {
  fetchVapidPublicKey,
  getPushSupport,
  registerPushServiceWorker,
  resetVapidPublicKeyCache,
  subscribeThisDevice,
  unsubscribeThisDevice,
  urlBase64ToUint8Array,
} from '../webPush'

const KEY = 'BGa-Woq2wPJvvXNVVgHvv432WHrboAtHW2ZMxc_IyBli0CrEOzM6IGWt3RvHFypZjRevEsqNpHTZZ0hCUovGAE8'

interface Env {
  userAgent?: string
  standalone?: boolean
  serviceWorker?: boolean
  pushManager?: boolean
  notification?: boolean
  permission?: NotificationPermission
  requestPermission?: NotificationPermission
}

const subscription = {
  endpoint: 'https://push.example/device',
  toJSON: () => ({ endpoint: 'https://push.example/device', keys: { p256dh: 'p', auth: 'a' } }),
  unsubscribe: vi.fn().mockResolvedValue(true),
}
const pushManager = {
  getSubscription: vi.fn(),
  subscribe: vi.fn(),
}
const registration = { pushManager }
const swContainer = {
  register: vi.fn(),
  getRegistration: vi.fn(),
  ready: Promise.resolve(registration),
}
const requestPermission = vi.fn()

const originals = {
  ua: Object.getOwnPropertyDescriptor(Navigator.prototype, 'userAgent'),
}

function setEnv(env: Env = {}): void {
  const {
    userAgent = 'Mozilla/5.0 (Linux; Android 14) Chrome/130',
    standalone = false,
    serviceWorker = true,
    pushManager: hasPush = true,
    notification = true,
    permission = 'default',
    requestPermission: asked = 'granted',
  } = env

  Object.defineProperty(navigator, 'userAgent', { value: userAgent, configurable: true })
  Object.defineProperty(navigator, 'standalone', { value: standalone, configurable: true })
  if (serviceWorker) {
    Object.defineProperty(navigator, 'serviceWorker', { value: swContainer, configurable: true })
  } else {
    delete (navigator as unknown as Record<string, unknown>).serviceWorker
  }

  const win = window as unknown as Record<string, unknown>
  if (hasPush) {
    win.PushManager = class {
      static supportedContentEncodings = ['aes128gcm']
    }
  } else {
    delete win.PushManager
  }

  requestPermission.mockResolvedValue(asked)
  if (notification) {
    win.Notification = Object.assign(function () {}, { permission, requestPermission })
  } else {
    delete win.Notification
  }
}

describe('webPush', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    resetVapidPublicKeyCache()
    pushManager.getSubscription.mockResolvedValue(null)
    pushManager.subscribe.mockResolvedValue(subscription)
    swContainer.register.mockResolvedValue(registration)
    swContainer.getRegistration.mockResolvedValue(registration)
    mockGet.mockResolvedValue({ data: { data: { public_key: KEY } } })
    mockPost.mockResolvedValue({})
    mockDelete.mockResolvedValue({})
    setEnv()
  })

  afterEach(() => {
    if (originals.ua) Object.defineProperty(Navigator.prototype, 'userAgent', originals.ua)
    delete (navigator as unknown as Record<string, unknown>).userAgent
  })

  describe('getPushSupport', () => {
    it('is supported on Android Chrome', () => {
      expect(getPushSupport()).toBe('supported')
    })

    it('is unsupported without service worker, PushManager or Notification', () => {
      setEnv({ serviceWorker: false })
      expect(getPushSupport()).toBe('unsupported')
      setEnv({ pushManager: false })
      expect(getPushSupport()).toBe('unsupported')
      setEnv({ notification: false })
      expect(getPushSupport()).toBe('unsupported')
    })

    it('asks iPhone users to install the app first when not standalone', () => {
      setEnv({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari', pushManager: false })
      expect(getPushSupport()).toBe('ios-install-required')
    })

    it('is supported on iPhone once added to the home screen', () => {
      setEnv({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari', standalone: true })
      expect(getPushSupport()).toBe('supported')
    })
  })

  it('converts a base64url VAPID key to bytes', () => {
    const bytes = urlBase64ToUint8Array(KEY)
    expect(bytes).toHaveLength(65)
    expect(bytes[0]).toBe(4)
  })

  describe('registerPushServiceWorker', () => {
    it('registers /sw.js at the root scope when supported', async () => {
      await registerPushServiceWorker()
      expect(swContainer.register).toHaveBeenCalledWith('/sw.js', { scope: '/' })
    })

    it('does nothing when push is not supported', async () => {
      setEnv({ serviceWorker: false })
      expect(await registerPushServiceWorker()).toBeNull()
      expect(swContainer.register).not.toHaveBeenCalled()
    })

    it('swallows registration errors', async () => {
      vi.spyOn(console, 'warn').mockImplementation(() => undefined)
      swContainer.register.mockRejectedValue(new Error('boom'))
      await expect(registerPushServiceWorker()).resolves.toBeNull()
    })
  })

  describe('fetchVapidPublicKey', () => {
    it('returns the key once and caches it', async () => {
      expect(await fetchVapidPublicKey()).toBe(KEY)
      await fetchVapidPublicKey()
      expect(mockGet).toHaveBeenCalledTimes(1)
      expect(mockGet).toHaveBeenCalledWith('/push/public-key')
    })

    it('returns null when the server has no VAPID keys', async () => {
      mockGet.mockResolvedValue({ data: { data: { public_key: null } } })
      expect(await fetchVapidPublicKey()).toBeNull()
    })

    it('returns null and retries later on a network error', async () => {
      mockGet.mockRejectedValueOnce(new Error('offline'))
      expect(await fetchVapidPublicKey()).toBeNull()
      expect(await fetchVapidPublicKey()).toBe(KEY)
    })
  })

  describe('subscribeThisDevice', () => {
    it('asks permission, subscribes with the VAPID key and posts the subscription', async () => {
      expect(await subscribeThisDevice()).toBe('enabled')

      expect(requestPermission).toHaveBeenCalledOnce()
      expect(pushManager.subscribe).toHaveBeenCalledWith({
        userVisibleOnly: true,
        applicationServerKey: expect.any(Uint8Array),
      })
      expect(mockPost).toHaveBeenCalledWith('/me/push-subscriptions', {
        endpoint: 'https://push.example/device',
        keys: { p256dh: 'p', auth: 'a' },
        content_encoding: 'aes128gcm',
      })
    })

    it('does not ask again when the permission is already granted', async () => {
      setEnv({ permission: 'granted' })
      await subscribeThisDevice()
      expect(requestPermission).not.toHaveBeenCalled()
      expect(mockPost).toHaveBeenCalled()
    })

    it('reports denied without subscribing when the user refuses', async () => {
      setEnv({ requestPermission: 'denied' })
      expect(await subscribeThisDevice()).toBe('denied')
      expect(pushManager.subscribe).not.toHaveBeenCalled()
      expect(mockPost).not.toHaveBeenCalled()
    })

    it('never prompts when the permission is already denied', async () => {
      setEnv({ permission: 'denied' })
      expect(await subscribeThisDevice()).toBe('denied')
      expect(requestPermission).not.toHaveBeenCalled()
    })

    it('reports dismissed when the prompt is closed without an answer', async () => {
      setEnv({ requestPermission: 'default' })
      expect(await subscribeThisDevice()).toBe('dismissed')
    })

    it('is unavailable (no prompt) when the server has no VAPID key', async () => {
      mockGet.mockResolvedValue({ data: { data: { public_key: null } } })
      expect(await subscribeThisDevice()).toBe('unavailable')
      expect(requestPermission).not.toHaveBeenCalled()
    })

    it('drops the browser subscription when the server refuses it', async () => {
      mockPost.mockRejectedValue(new Error('422'))
      await expect(subscribeThisDevice()).rejects.toThrow('422')
      expect(subscription.unsubscribe).toHaveBeenCalled()
    })
  })

  describe('unsubscribeThisDevice', () => {
    it('deletes the subscription server-side by endpoint, then in the browser', async () => {
      pushManager.getSubscription.mockResolvedValue(subscription)

      await unsubscribeThisDevice()

      expect(mockDelete).toHaveBeenCalledWith('/me/push-subscriptions', {
        data: { endpoint: 'https://push.example/device' },
      })
      expect(subscription.unsubscribe).toHaveBeenCalled()
    })

    it('still unsubscribes the browser when the server call fails, and never throws', async () => {
      vi.spyOn(console, 'warn').mockImplementation(() => undefined)
      pushManager.getSubscription.mockResolvedValue(subscription)
      mockDelete.mockRejectedValue(new Error('401'))

      await expect(unsubscribeThisDevice()).resolves.toBeUndefined()
      expect(subscription.unsubscribe).toHaveBeenCalled()
    })

    it('is a no-op without subscription or service worker support', async () => {
      await unsubscribeThisDevice()
      setEnv({ serviceWorker: false })
      await unsubscribeThisDevice()
      expect(mockDelete).not.toHaveBeenCalled()
    })
  })
})
