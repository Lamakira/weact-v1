import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createMemoryHistory } from 'vue-router'
import VerifyEmailPage from '../VerifyEmailPage.vue'

// Mock the authApi
vi.mock('@/features/auth/services/authApi', () => ({
  authApi: {
    verifyEmail: vi.fn(),
    resendVerificationEmail: vi.fn(),
  },
}))

// Mock useToast
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
  info: vi.fn(),
}

vi.mock('@/composables/useToast', () => ({
  useToast: () => mockToast,
}))

import { authApi } from '@/features/auth/services/authApi'

describe('VerifyEmailPage', () => {
  let router: ReturnType<typeof createRouter>

  beforeEach(() => {
    vi.clearAllMocks()

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/verify-email/:id/:hash',
          name: 'verify-email',
          component: VerifyEmailPage,
        },
        { path: '/login', name: 'login', component: { template: '<div>Login</div>' } },
        { path: '/face/dashboard', name: 'face-dashboard', component: { template: '<div>Face Dashboard</div>' } },
        { path: '/producer/dashboard', name: 'producer-dashboard', component: { template: '<div>Producer Dashboard</div>' } },
      ],
    })
  })

  async function mountComponent(routeParams = { id: '1', hash: 'abc123' }, query = { expires: '123456', signature: 'sig123' }) {
    await router.push({
      name: 'verify-email',
      params: routeParams,
      query,
    })
    await router.isReady()

    return mount(VerifyEmailPage, {
      global: {
        plugins: [
          createTestingPinia({
            initialState: {
              auth: {
                user: {
                  id: 1,
                  email: 'test@example.com',
                  email_verified: false,
                  userable_type: 'Face',
                },
                token: 'test-token',
              },
            },
          }),
          router,
        ],
      },
    })
  }

  describe('Loading state', () => {
    it('shows loading state initially', async () => {
      // Create a promise that doesn't resolve immediately
      let resolvePromise: (value: { verified: boolean }) => void
      const promise = new Promise<{ verified: boolean }>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(authApi.verifyEmail).mockReturnValueOnce(promise)

      const wrapper = await mountComponent()

      expect(wrapper.find('[data-testid="verify-loading"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Vérification en cours')

      // Resolve promise to clean up
      resolvePromise!({ verified: true })
      await flushPromises()
    })
  })

  describe('Success state', () => {
    it('shows success state when verification succeeds', async () => {
      vi.mocked(authApi.verifyEmail).mockResolvedValueOnce({ verified: true })

      const wrapper = await mountComponent()
      await flushPromises()

      expect(wrapper.find('[data-testid="verify-success"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Email vérifié')
    })

    it('shows dashboard button on success', async () => {
      vi.mocked(authApi.verifyEmail).mockResolvedValueOnce({ verified: true })

      const wrapper = await mountComponent()
      await flushPromises()

      expect(wrapper.find('[data-testid="go-to-dashboard-button"]').exists()).toBe(true)
    })
  })

  describe('Already verified state', () => {
    it('shows already verified state when email was already verified', async () => {
      vi.mocked(authApi.verifyEmail).mockResolvedValueOnce({ verified: true, already_verified: true })

      const wrapper = await mountComponent()
      await flushPromises()

      expect(wrapper.find('[data-testid="verify-already"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Email déjà vérifié')
    })
  })

  describe('Error state', () => {
    it('shows error state when verification link is expired', async () => {
      vi.mocked(authApi.verifyEmail).mockRejectedValueOnce({
        response: { data: { error: { code: 'VERIFICATION_LINK_EXPIRED' } } },
      })

      const wrapper = await mountComponent()
      await flushPromises()

      expect(wrapper.find('[data-testid="verify-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain('expiré')
    })

    it('shows error state when verification link is invalid', async () => {
      vi.mocked(authApi.verifyEmail).mockRejectedValueOnce({
        response: { data: { error: { code: 'INVALID_VERIFICATION_LINK' } } },
      })

      const wrapper = await mountComponent()
      await flushPromises()

      expect(wrapper.find('[data-testid="verify-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain('invalide')
    })

    it('shows error state when URL params are missing', async () => {
      await router.push({
        name: 'verify-email',
        params: { id: '1', hash: 'abc' },
        query: {}, // Missing expires and signature
      })
      await router.isReady()

      const wrapper = mount(VerifyEmailPage, {
        global: {
          plugins: [createTestingPinia(), router],
        },
      })
      await flushPromises()

      expect(wrapper.find('[data-testid="verify-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain('invalide ou incomplet')
    })

    it('shows resend button when authenticated', async () => {
      vi.mocked(authApi.verifyEmail).mockRejectedValueOnce({
        response: { data: { error: { code: 'VERIFICATION_LINK_EXPIRED' } } },
      })

      const wrapper = await mountComponent()
      await flushPromises()

      expect(wrapper.find('[data-testid="resend-button"]').exists()).toBe(true)
    })
  })

  describe('Resend functionality', () => {
    it('calls resend API when resend button is clicked', async () => {
      vi.mocked(authApi.verifyEmail).mockRejectedValueOnce({
        response: { data: { error: { code: 'VERIFICATION_LINK_EXPIRED' } } },
      })
      vi.mocked(authApi.resendVerificationEmail).mockResolvedValueOnce({ sent: true })

      const wrapper = await mountComponent()
      await flushPromises()

      await wrapper.find('[data-testid="resend-button"]').trigger('click')
      await flushPromises()

      expect(authApi.resendVerificationEmail).toHaveBeenCalled()
      expect(mockToast.success).toHaveBeenCalledWith(expect.stringContaining('envoyé'))
    })
  })

  describe('Navigation', () => {
    it('navigates to Face dashboard when user is a Face', async () => {
      vi.mocked(authApi.verifyEmail).mockResolvedValueOnce({ verified: true })

      const wrapper = await mountComponent()
      await flushPromises()

      await wrapper.find('[data-testid="go-to-dashboard-button"]').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.name).toBe('face-dashboard')
    })

    it('navigates to Producer dashboard when user is a Producer', async () => {
      vi.mocked(authApi.verifyEmail).mockResolvedValueOnce({ verified: true })

      await router.push({
        name: 'verify-email',
        params: { id: '1', hash: 'abc123' },
        query: { expires: '123456', signature: 'sig123' },
      })
      await router.isReady()

      const wrapper = mount(VerifyEmailPage, {
        global: {
          plugins: [
            createTestingPinia({
              initialState: {
                auth: {
                  user: {
                    id: 1,
                    email: 'producer@example.com',
                    email_verified: false,
                    userable_type: 'Producer',
                  },
                  token: 'test-token',
                },
              },
            }),
            router,
          ],
        },
      })
      await flushPromises()

      await wrapper.find('[data-testid="go-to-dashboard-button"]').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.name).toBe('producer-dashboard')
    })
  })
})
