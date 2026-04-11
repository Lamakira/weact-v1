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
})
