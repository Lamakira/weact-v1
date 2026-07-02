import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { ref } from 'vue'
import ProducerLayout from '../ProducerLayout.vue'
import { producerApi } from '@/features/producer/services/producerApi'
import type { SidebarItem } from '@/components/layout'

vi.mock('@/features/producer/services/producerApi', () => ({
  producerApi: { listDeliverablesToReview: vi.fn() },
}))
vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({ logout: vi.fn(), isLoading: ref(false) }),
}))
vi.mock('@/features/producer/composables/useProducerProfilePhoto', () => ({
  useProducerProfilePhoto: () => ({ profile: ref(null), fetchProfile: vi.fn().mockResolvedValue(undefined) }),
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: { email: 'p@x.bj' }, isEmailVerified: true }),
}))

const DashboardLayoutStub = {
  name: 'DashboardLayout',
  props: ['sidebarItems', 'title', 'userEmail', 'userName', 'avatarUrl', 'isLoggingOut', 'profileRoute'],
  template: '<div><slot /></div>',
}

describe('ProducerLayout', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('feeds the in_review count as a badge on the Validation livrables item', async () => {
    vi.mocked(producerApi.listDeliverablesToReview).mockResolvedValue({ data: [{}, {}, {}] as never })
    const wrapper = mount(ProducerLayout, {
      global: { stubs: { DashboardLayout: DashboardLayoutStub, EmailVerificationBanner: true, RouterView: true } },
    })
    await flushPromises()

    const items = wrapper.findComponent({ name: 'DashboardLayout' }).props('sidebarItems') as SidebarItem[]
    expect(items.find((i) => i.to === '/producer/ugc/validation')?.badge).toBe(3)
    expect(items.find((i) => i.to === '/producer/dashboard')?.badge).toBeUndefined()
  })
})
