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

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: { email: 'face@test.com' }, isEmailVerified: true }),
}))

const profileResolvers: Array<() => void> = []
const mockFetchProfile = vi.fn(
  () => new Promise<void>((resolve) => { profileResolvers.push(resolve) }),
)
const mockFetchPersonalInfo = vi.fn().mockResolvedValue(undefined)

vi.mock('@/features/face/composables/useProfilePhoto', () => ({
  useProfilePhoto: () => ({ profile: ref(null), fetchProfile: mockFetchProfile }),
}))
vi.mock('@/features/face/composables/usePersonalInfo', () => ({
  usePersonalInfo: () => ({ personalInfo: ref(null), fetchPersonalInfo: mockFetchPersonalInfo }),
}))

vi.mock('@/components/layout', () => ({
  DashboardLayout: { template: '<div><slot /></div>' },
  KeepAliveRouterView: { template: '<div />' },
}))

const stub = { template: '<div />' }

describe('FaceLayout - chargements au montage', () => {
  it('lance fetchPersonalInfo sans attendre la fin de fetchProfile', async () => {
    mount(FaceLayout, {
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

    expect(mockFetchProfile).toHaveBeenCalledOnce()
    // fetchProfile est encore en attente : l'appel personalInfo doit déjà être parti
    expect(mockFetchPersonalInfo).toHaveBeenCalledOnce()
    profileResolvers.forEach((resolve) => resolve())
  })
})
