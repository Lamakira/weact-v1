import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import EmailVerificationBanner from '../EmailVerificationBanner.vue'

// Mock authApi
vi.mock('@/features/auth/services/authApi', () => ({
  authApi: {
    resendVerificationEmail: vi.fn(),
  },
}))

// Mock useToast
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
}

vi.mock('@/composables/useToast', () => ({
  useToast: () => mockToast,
}))

import { authApi } from '@/features/auth/services/authApi'

describe('EmailVerificationBanner', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  function mountBanner() {
    return mount(EmailVerificationBanner, {
      global: {
        plugins: [createTestingPinia()],
      },
    })
  }

  describe('Rendering', () => {
    it('renders the banner with testid', () => {
      const wrapper = mountBanner()
      expect(wrapper.find('[data-testid="email-verification-banner"]').exists()).toBe(true)
    })

    it('displays title', () => {
      const wrapper = mountBanner()
      expect(wrapper.find('[data-testid="banner-title"]').text()).toBe('Vérifiez votre adresse email')
    })

    it('displays message', () => {
      const wrapper = mountBanner()
      expect(wrapper.find('[data-testid="banner-message"]').exists()).toBe(true)
    })

    it('displays resend button', () => {
      const wrapper = mountBanner()
      expect(wrapper.find('[data-testid="resend-button"]').exists()).toBe(true)
    })

    it('does not have dismiss button (banner cannot be dismissed)', () => {
      const wrapper = mountBanner()
      expect(wrapper.find('[data-testid="dismiss-button"]').exists()).toBe(false)
    })
  })

  describe('Resend functionality', () => {
    it('calls API when resend button is clicked', async () => {
      vi.mocked(authApi.resendVerificationEmail).mockResolvedValue({ sent: true })
      const wrapper = mountBanner()

      await wrapper.find('[data-testid="resend-button"]').trigger('click')
      await flushPromises()

      expect(authApi.resendVerificationEmail).toHaveBeenCalled()
    })

    it('shows success toast when email is sent', async () => {
      vi.mocked(authApi.resendVerificationEmail).mockResolvedValue({ sent: true })
      const wrapper = mountBanner()

      await wrapper.find('[data-testid="resend-button"]').trigger('click')
      await flushPromises()

      expect(mockToast.success).toHaveBeenCalledWith('Un email de vérification a été envoyé.')
    })

    it('shows success toast when already verified', async () => {
      vi.mocked(authApi.resendVerificationEmail).mockResolvedValue({ verified: true })
      const wrapper = mountBanner()

      await wrapper.find('[data-testid="resend-button"]').trigger('click')
      await flushPromises()

      expect(mockToast.success).toHaveBeenCalledWith('Votre email est déjà vérifié.')
    })

    it('starts cooldown after successful resend', async () => {
      vi.mocked(authApi.resendVerificationEmail).mockResolvedValue({ sent: true })
      const wrapper = mountBanner()

      await wrapper.find('[data-testid="resend-button"]').trigger('click')
      await flushPromises()

      // Button should show countdown
      expect(wrapper.find('[data-testid="resend-button"]').text()).toContain('60')
    })

    it('decrements cooldown timer', async () => {
      vi.mocked(authApi.resendVerificationEmail).mockResolvedValue({ sent: true })
      const wrapper = mountBanner()

      await wrapper.find('[data-testid="resend-button"]').trigger('click')
      await flushPromises()

      // Advance timer by 1 second
      vi.advanceTimersByTime(1000)
      await flushPromises()

      expect(wrapper.find('[data-testid="resend-button"]').text()).toContain('59')
    })

    it('disables button during cooldown', async () => {
      vi.mocked(authApi.resendVerificationEmail).mockResolvedValue({ sent: true })
      const wrapper = mountBanner()

      await wrapper.find('[data-testid="resend-button"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-testid="resend-button"]').attributes('disabled')).toBeDefined()
    })

    it('handles throttle error (429)', async () => {
      vi.mocked(authApi.resendVerificationEmail).mockRejectedValue({
        response: { status: 429 },
      })
      const wrapper = mountBanner()

      await wrapper.find('[data-testid="resend-button"]').trigger('click')
      await flushPromises()

      expect(mockToast.warning).toHaveBeenCalledWith('Veuillez patienter avant de renvoyer un email.')
    })

    it('handles generic error', async () => {
      vi.mocked(authApi.resendVerificationEmail).mockRejectedValue(new Error('Network error'))
      const wrapper = mountBanner()

      await wrapper.find('[data-testid="resend-button"]').trigger('click')
      await flushPromises()

      expect(mockToast.error).toHaveBeenCalledWith("Impossible d'envoyer l'email. Veuillez réessayer.")
    })
  })

})
