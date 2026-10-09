import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceLayout from '../FaceLayout.vue'

const routeHolder = vi.hoisted(() => ({ route: { path: '/face/dashboard', meta: {} } }))
vi.mock('vue-router', () => ({
  useRoute: () => routeHolder.route,
}))
vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({ logout: vi.fn(), isLoading: ref(false) }),
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: { email: 'face@test.com' }, isEmailVerified: true }),
}))
vi.mock('@/features/face/composables/useProfilePhoto', () => ({
  useProfilePhoto: () => ({ profile: ref(null), fetchProfile: vi.fn().mockResolvedValue(undefined) }),
}))
vi.mock('@/features/face/composables/usePersonalInfo', () => ({
  usePersonalInfo: () => ({ personalInfo: ref(null), fetchPersonalInfo: vi.fn().mockResolvedValue(undefined) }),
}))
vi.mock('@/components/layout', async () => {
  const { defineComponent } = await import('vue')
  return {
    DashboardLayout: defineComponent({
      name: 'DashboardLayout',
      props: ['title', 'sidebarItems', 'mobileTabs'],
      template: '<div><slot /></div>',
    }),
    KeepAliveRouterView: { template: '<div />' },
  }
})

const stub = { template: '<div />' }

describe('FaceLayout - titre d\'en-tête par page', () => {
  beforeEach(() => {
    routeHolder.route.path = '/face/dashboard'
  })

  it.each([
    ['/face/dashboard', 'Tableau de bord'],
    ['/face/missions', 'Voir les missions'],
    ['/face/ugc-missions', 'Missions UGC'],
    ['/face/candidatures', 'Mes candidatures'],
    ['/face/bookings/3f2a-uuid', 'Mes bookings'],
    ['/face/messages', 'Messages'],
    ['/face/wallet', 'Portefeuille'],
    ['/face/billing', 'Facturation'],
    ['/pricing', 'Tarifs'],
    ['/face/profile', 'Mon profil'],
  ])('affiche le titre français sur %s', async (path, expected) => {
    routeHolder.route.path = path
    const wrapper = mount(FaceLayout, {
      global: {
        stubs: {
          EmailVerificationBanner: stub,
          TarifsMissingBanner: stub,
          WhatsappMissingBanner: stub,
          PendingSubscriptionPaymentBanner: stub,
          UgcSuspensionBanner: stub,
        },
      },
    })
    await flushPromises()
    expect(wrapper.findComponent({ name: 'DashboardLayout' }).props('title')).toBe(expected)
  })
})
