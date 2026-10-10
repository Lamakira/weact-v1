import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const mockSupport = vi.fn()
const mockFetchKey = vi.fn()
const mockExisting = vi.fn()
const mockSubscribe = vi.fn()
vi.mock('../../push/webPush', () => ({
  getPushSupport: () => mockSupport(),
  fetchVapidPublicKey: () => mockFetchKey(),
  getExistingSubscription: () => mockExisting(),
  subscribeThisDevice: () => mockSubscribe(),
  unsubscribeThisDevice: vi.fn(),
  syncSubscriptionToServer: vi.fn().mockResolvedValue(true),
}))

vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ user: { id: 42 }, isEmailVerified: true }) }))
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() }),
}))

import PushSoftPrompt from '../PushSoftPrompt.vue'
import { resetWebPushState } from '../../push/useWebPush'

const KEY = 'weact.push.softPrompt.42'

function setPermission(permission: NotificationPermission): void {
  ;(window as unknown as Record<string, unknown>).Notification = { permission }
}

async function mountPrompt(audience: 'face' | 'producer' = 'face') {
  const wrapper = mount(PushSoftPrompt, { props: { audience } })
  await flushPromises()
  return wrapper
}

describe('PushSoftPrompt', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    resetWebPushState()
    mockSupport.mockReturnValue('supported')
    mockFetchKey.mockResolvedValue('public-key')
    mockExisting.mockResolvedValue(null)
    mockSubscribe.mockResolvedValue('enabled')
    setPermission('default')
  })

  it('shows the Face wording and never asks the permission by itself', async () => {
    const wrapper = await mountPrompt('face')

    expect(wrapper.get('[data-testid="push-soft-prompt"]').text()).toContain(
      'Recevez une alerte quand un Producteur vous propose un booking',
    )
    expect(mockSubscribe).not.toHaveBeenCalled()
  })

  it('shows the Producer wording', async () => {
    const wrapper = await mountPrompt('producer')

    expect(wrapper.get('[data-testid="push-soft-prompt"]').text()).toContain(
      'Recevez une alerte quand une Face répond',
    )
  })

  it('"Plus tard" dismisses it and remembers it for the user', async () => {
    const wrapper = await mountPrompt()

    await wrapper.get('[data-testid="push-soft-prompt-later"]').trigger('click')

    expect(wrapper.find('[data-testid="push-soft-prompt"]').exists()).toBe(false)
    expect(localStorage.getItem(KEY)).toBe('1')
    expect(mockSubscribe).not.toHaveBeenCalled()

    const remounted = await mountPrompt()
    expect(remounted.find('[data-testid="push-soft-prompt"]').exists()).toBe(false)
  })

  it('"Activer" subscribes (the only prompt trigger) and is remembered', async () => {
    mockSubscribe.mockImplementation(async () => {
      setPermission('granted')
      mockExisting.mockResolvedValue({ endpoint: 'https://push.example/x' })
      return 'enabled'
    })
    const wrapper = await mountPrompt()

    await wrapper.get('[data-testid="push-soft-prompt-enable"]').trigger('click')
    await flushPromises()

    expect(mockSubscribe).toHaveBeenCalledOnce()
    expect(wrapper.find('[data-testid="push-soft-prompt"]').exists()).toBe(false)
    expect(localStorage.getItem(KEY)).toBe('1')
  })

  it('stays hidden when already subscribed, denied, unsupported or without server keys', async () => {
    setPermission('granted')
    mockExisting.mockResolvedValue({ endpoint: 'https://push.example/x' })
    expect((await mountPrompt()).find('[data-testid="push-soft-prompt"]').exists()).toBe(false)

    resetWebPushState()
    setPermission('denied')
    expect((await mountPrompt()).find('[data-testid="push-soft-prompt"]').exists()).toBe(false)

    resetWebPushState()
    setPermission('default')
    mockSupport.mockReturnValue('unsupported')
    expect((await mountPrompt()).find('[data-testid="push-soft-prompt"]').exists()).toBe(false)

    resetWebPushState()
    mockSupport.mockReturnValue('supported')
    mockFetchKey.mockResolvedValue(null)
    expect((await mountPrompt()).find('[data-testid="push-soft-prompt"]').exists()).toBe(false)
  })

  it('still works when localStorage is unavailable', async () => {
    const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied')
    })
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('denied')
    })

    const wrapper = await mountPrompt()
    expect(wrapper.find('[data-testid="push-soft-prompt"]').exists()).toBe(true)

    await wrapper.get('[data-testid="push-soft-prompt-later"]').trigger('click')
    expect(wrapper.find('[data-testid="push-soft-prompt"]').exists()).toBe(false)

    getItem.mockRestore()
    setItem.mockRestore()
  })
})
