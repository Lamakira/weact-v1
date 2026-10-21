import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceLayout from '../FaceLayout.vue'

const routeMeta: { value: Record<string, unknown> } = { value: {} }

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/face/dashboard', get meta() { return routeMeta.value } }),
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({ logout: vi.fn(), isLoading: ref(false) }),
}))

vi.mock('@/stores/messagesUnread', () => ({
  useMessagesUnreadStore: () => ({ count: 0, start: vi.fn(), stop: vi.fn() }),
}))
vi.mock('@/stores/actionCounts', () => ({
  useActionCountsStore: () => ({
    counts: { bookings: 0, candidatures: 0, missions: 0, livrables: 0, messages: 0 },
    start: vi.fn(),
    stop: vi.fn(),
    refresh: vi.fn(),
  }),
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: { email: 'face@test.com' }, isEmailVerified: true }),
}))

const mockFetchProfile = vi.fn().mockResolvedValue(undefined)
const mockFetchPersonalInfo = vi.fn().mockResolvedValue(undefined)

vi.mock('@/features/face/composables/useProfilePhoto', () => ({
  useProfilePhoto: () => ({ profile: ref(null), fetchProfile: mockFetchProfile }),
}))
vi.mock('@/features/face/composables/usePersonalInfo', () => ({
  usePersonalInfo: () => ({ personalInfo: ref(null), fetchPersonalInfo: mockFetchPersonalInfo }),
}))

// Le stub rend le slot `alerts` (la barre) puis le contenu, comme le vrai DashboardLayout.
vi.mock('@/components/layout', () => ({
  DashboardLayout: { template: '<div><slot name="alerts" /><slot /></div>' },
  KeepAliveRouterView: { template: '<div />' },
}))

const AccountAlertBarStub = {
  props: ['audience', 'hideSubscription'],
  template: '<div data-testid="account-alert-bar-stub" />',
}

function mountLayout() {
  return mount(FaceLayout, { global: { stubs: { AccountAlertBar: AccountAlertBarStub } } })
}

describe('FaceLayout - chargements au montage et barre d\'alertes', () => {
  it('ne charge plus les infos personnelles : le rappel WhatsApp est porté par la to-do', async () => {
    mountLayout()
    await flushPromises()

    expect(mockFetchProfile).toHaveBeenCalledOnce()
    expect(mockFetchPersonalInfo).not.toHaveBeenCalled()
  })

  it('monte une seule barre d\'alertes (audience face) et aucun bandeau empilé', async () => {
    routeMeta.value = {}
    const wrapper = mountLayout()
    await flushPromises()

    const bars = wrapper.findAllComponents(AccountAlertBarStub)
    expect(bars).toHaveLength(1)
    expect(bars[0]!.props('audience')).toBe('face')
    expect(bars[0]!.props('hideSubscription')).toBe(false)
    for (const testid of [
      'email-verification-banner',
      'tarifs-missing-banner',
      'whatsapp-missing-banner',
      'pending-payment-banner',
      'ugc-suspension-banner',
    ]) {
      expect(wrapper.find(`[data-testid="${testid}"]`).exists()).toBe(false)
    }
  })

  it('masque l\'alerte d\'abonnement sur une route qui porte sa propre surface d\'abonnement', async () => {
    routeMeta.value = { ownSubscriptionSurface: true }
    const wrapper = mountLayout()
    await flushPromises()

    expect(wrapper.findComponent(AccountAlertBarStub).props('hideSubscription')).toBe(true)
    routeMeta.value = {}
  })
})
