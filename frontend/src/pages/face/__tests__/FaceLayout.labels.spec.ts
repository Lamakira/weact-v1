import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceLayout from '../FaceLayout.vue'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/face/dashboard', meta: {} }),
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

vi.mock('@/features/face/composables/useProfilePhoto', () => ({
  useProfilePhoto: () => ({ profile: ref(null), fetchProfile: vi.fn().mockResolvedValue(undefined) }),
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

describe('FaceLayout - libellés en français', () => {
  it('titre l\'en-tête « Tableau de bord » et libelle l\'entrée de la barre latérale en français', async () => {
    const wrapper = mount(FaceLayout, {
      global: {
        stubs: {
          AccountAlertBar: stub,
        },
      },
    })
    await flushPromises()

    const layout = wrapper.findComponent({ name: 'DashboardLayout' })
    expect(layout.props('title')).toBe('Tableau de bord')

    const labels = (layout.props('sidebarItems') as Array<{ label: string; to: string }>).map((i) => i.label)
    expect(labels).toContain('Tableau de bord')
    expect(labels).not.toContain('Dashboard')
  })
})
