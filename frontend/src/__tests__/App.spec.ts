import { describe, it, expect, vi, beforeEach } from 'vitest'
import { defineComponent } from 'vue'
import { shallowMount } from '@vue/test-utils'

const mockRoute = {
  path: '/',
  meta: {},
  name: 'home',
}

const mockAuthStore = {
  isAuthenticated: false,
}

const mockNotificationStore = {
  isSubscribed: false,
  subscribe: vi.fn(),
  fetchUnreadCount: vi.fn(),
}

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  RouterView: defineComponent({
    name: 'RouterView',
    setup(_, { slots }) {
      return () =>
        slots.default?.({
          Component: defineComponent({
            name: 'MockRouteComponent',
            template: '<div data-testid="route-component" />',
          }),
        })
    },
  }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => mockAuthStore,
}))

vi.mock('@/stores/notification', () => ({
  useNotificationStore: () => mockNotificationStore,
}))

vi.mock('@/components/layout/AppHeader.vue', () => ({
  default: defineComponent({
    name: 'AppHeader',
    template: '<header data-testid="app-header" />',
  }),
}))

vi.mock('@/components/layout/AppFooter.vue', () => ({
  default: defineComponent({
    name: 'AppFooter',
    template: '<footer data-testid="app-footer" />',
  }),
}))

vi.mock('@/components/cookie/CookieConsentBanner.vue', () => ({
  default: defineComponent({
    name: 'CookieConsentBanner',
    template: '<div data-testid="cookie-banner" />',
  }),
}))

const mockRegisterPushServiceWorker = vi.fn()
vi.mock('@/features/notification/push/webPush', () => ({
  registerPushServiceWorker: () => mockRegisterPushServiceWorker(),
}))

import App from '../App.vue'

describe('App.vue notification bootstrap', () => {
  beforeEach(() => {
    mockRoute.path = '/'
    mockRoute.meta = {}
    mockRoute.name = 'home'
    mockAuthStore.isAuthenticated = false
    mockNotificationStore.isSubscribed = false
    vi.clearAllMocks()
  })

  it('bootstraps notifications on mount for authenticated users without an active subscription', () => {
    mockAuthStore.isAuthenticated = true

    shallowMount(App)

    expect(mockNotificationStore.subscribe).toHaveBeenCalledOnce()
    expect(mockNotificationStore.fetchUnreadCount).toHaveBeenCalledOnce()
  })

  it('does not bootstrap notifications when the user is unauthenticated', () => {
    shallowMount(App)

    expect(mockNotificationStore.subscribe).not.toHaveBeenCalled()
    expect(mockNotificationStore.fetchUnreadCount).not.toHaveBeenCalled()
  })

  it('does not bootstrap notifications when a subscription is already active', () => {
    mockAuthStore.isAuthenticated = true
    mockNotificationStore.isSubscribed = true

    shallowMount(App)

    expect(mockNotificationStore.subscribe).not.toHaveBeenCalled()
    expect(mockNotificationStore.fetchUnreadCount).not.toHaveBeenCalled()
  })

  it('registers the push-only service worker for authenticated users', async () => {
    mockAuthStore.isAuthenticated = true

    shallowMount(App)
    await vi.dynamicImportSettled()

    expect(mockRegisterPushServiceWorker).toHaveBeenCalledOnce()
  })

  it('never registers the service worker for anonymous visitors', async () => {
    shallowMount(App)
    await vi.dynamicImportSettled()

    expect(mockRegisterPushServiceWorker).not.toHaveBeenCalled()
  })

  it('reserves a full viewport of height for <main> on regular public routes (anti-CLS footer)', () => {
    mockRoute.path = '/faces'
    mockRoute.name = 'faces'

    const wrapper = shallowMount(App)

    expect(wrapper.find('main').classes()).toContain('supports-[height:100dvh]:min-h-[100dvh]')
    expect(wrapper.find('main').classes()).toContain('min-h-screen')
  })
})
