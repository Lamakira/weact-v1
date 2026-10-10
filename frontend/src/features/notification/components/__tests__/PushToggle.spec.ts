import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const mockSupport = vi.fn()
const mockFetchKey = vi.fn()
const mockExisting = vi.fn()
const mockSubscribe = vi.fn()
const mockUnsubscribe = vi.fn()
vi.mock('../../push/webPush', () => ({
  getPushSupport: () => mockSupport(),
  fetchVapidPublicKey: () => mockFetchKey(),
  getExistingSubscription: () => mockExisting(),
  subscribeThisDevice: () => mockSubscribe(),
  unsubscribeThisDevice: () => mockUnsubscribe(),
}))

const toast = { success: vi.fn(), error: vi.fn(), info: vi.fn() }
vi.mock('@/composables/useToast', () => ({ useToast: () => toast }))

import PushToggle from '../PushToggle.vue'
import { resetWebPushState } from '../../push/useWebPush'

function setPermission(permission: NotificationPermission): void {
  ;(window as unknown as Record<string, unknown>).Notification = { permission }
}

async function mountToggle() {
  const wrapper = mount(PushToggle)
  await flushPromises()
  return wrapper
}

describe('PushToggle', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    resetWebPushState()
    mockSupport.mockReturnValue('supported')
    mockFetchKey.mockResolvedValue('public-key')
    mockExisting.mockResolvedValue(null)
    mockSubscribe.mockResolvedValue('enabled')
    mockUnsubscribe.mockResolvedValue(undefined)
    setPermission('default')
  })

  it('renders nothing when the browser does not support push', async () => {
    mockSupport.mockReturnValue('unsupported')

    const wrapper = await mountToggle()

    expect(wrapper.find('[data-testid="push-toggle"]').exists()).toBe(false)
  })

  it('renders nothing when the server has no VAPID keys', async () => {
    mockFetchKey.mockResolvedValue(null)

    const wrapper = await mountToggle()

    expect(wrapper.find('[data-testid="push-toggle"]').exists()).toBe(false)
  })

  it('explains the home-screen requirement on iPhone and disables the switch', async () => {
    mockSupport.mockReturnValue('ios-install-required')

    const wrapper = await mountToggle()

    expect(wrapper.get('[data-testid="push-toggle-ios-hint"]').text()).toContain(
      "ajoutez d'abord WeAct à l'écran d'accueil (Partager → Sur l'écran d'accueil)",
    )
    expect(wrapper.get('[data-testid="push-toggle-switch"]').attributes('disabled')).toBeDefined()
  })

  it('is off by default and does not prompt on mount', async () => {
    const wrapper = await mountToggle()

    expect(wrapper.get('[data-testid="push-toggle-switch"]').attributes('aria-checked')).toBe('false')
    expect(mockSubscribe).not.toHaveBeenCalled()
  })

  it('subscribes on click only, then shows as enabled', async () => {
    mockSubscribe.mockImplementation(async () => {
      setPermission('granted')
      mockExisting.mockResolvedValue({ endpoint: 'https://push.example/x' })
      return 'enabled'
    })
    const wrapper = await mountToggle()

    await wrapper.get('[data-testid="push-toggle-switch"]').trigger('click')
    await flushPromises()

    expect(mockSubscribe).toHaveBeenCalledOnce()
    expect(wrapper.get('[data-testid="push-toggle-switch"]').attributes('aria-checked')).toBe('true')
    expect(toast.success).toHaveBeenCalled()
  })

  it('shows enabled when permission is granted and a subscription exists', async () => {
    setPermission('granted')
    mockExisting.mockResolvedValue({ endpoint: 'https://push.example/x' })

    const wrapper = await mountToggle()

    expect(wrapper.get('[data-testid="push-toggle-switch"]').attributes('aria-checked')).toBe('true')
  })

  it('unsubscribes the device when switched off', async () => {
    setPermission('granted')
    mockExisting.mockResolvedValue({ endpoint: 'https://push.example/x' })
    const wrapper = await mountToggle()
    mockExisting.mockResolvedValue(null)

    await wrapper.get('[data-testid="push-toggle-switch"]').trigger('click')
    await flushPromises()

    expect(mockUnsubscribe).toHaveBeenCalledOnce()
    expect(wrapper.get('[data-testid="push-toggle-switch"]').attributes('aria-checked')).toBe('false')
  })

  it('explains how to re-enable notifications when the permission is denied', async () => {
    setPermission('denied')

    const wrapper = await mountToggle()

    expect(wrapper.get('[data-testid="push-toggle-denied-hint"]').text()).toContain('réglages du site')
    expect(wrapper.get('[data-testid="push-toggle-switch"]').attributes('disabled')).toBeDefined()
    expect(mockSubscribe).not.toHaveBeenCalled()
  })

  it('toasts an error when enabling fails', async () => {
    mockSubscribe.mockRejectedValue(new Error('network'))
    vi.spyOn(console, 'warn').mockImplementation(() => undefined)
    const wrapper = await mountToggle()

    await wrapper.get('[data-testid="push-toggle-switch"]').trigger('click')
    await flushPromises()

    expect(toast.error).toHaveBeenCalled()
  })
})
