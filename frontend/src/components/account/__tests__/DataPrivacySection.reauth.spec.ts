import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import DataPrivacySection from '../DataPrivacySection.vue'
import { setGoogleReauthToken, hasGoogleReauthToken } from '@/features/auth/googleReauth'

const h = vi.hoisted(() => ({
  hasPassword: true,
  clearAuth: vi.fn(),
  push: vi.fn(),
  del: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: h.push }),
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

vi.mock('@/services/apiClient', () => ({
  default: {
    get: vi.fn(),
    delete: (...args: unknown[]) => h.del(...args),
  },
  getCsrfCookie: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('@/services/errorFormatter', () => ({
  formatApiError: vi.fn(() => 'Confirmation expirée.'),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    get hasPassword() {
      return h.hasPassword
    },
    clearAuth: h.clearAuth,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn() }),
}))

const stubs = {
  GoogleSignInButton: {
    props: ['intent', 'disabled', 'label'],
    template: '<button data-testid="google-sign-in-button" :data-intent="intent" />',
  },
  Teleport: true,
}

describe('DataPrivacySection — Google re-authentication before deletion', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
    h.hasPassword = true
    h.del.mockResolvedValue({ data: { message: 'Compte supprimé' } })
  })

  const mountSection = () => mount(DataPrivacySection, { global: { stubs } })

  it('offers a Google confirmation instead of a password field for an OAuth-only account', async () => {
    h.hasPassword = false
    const wrapper = mountSection()
    await flushPromises()

    await wrapper.find('[data-testid="delete-account-button"]').trigger('click')

    expect(wrapper.find('[data-testid="delete-requires-reauth"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="google-sign-in-button"]').attributes('data-intent')).toBe(
      'reauth'
    )
    expect(wrapper.find('#delete-password').exists()).toBe(false)
  })

  it('keeps the password field for an account that has one', async () => {
    const wrapper = mountSection()
    await flushPromises()

    await wrapper.find('[data-testid="delete-account-button"]').trigger('click')

    expect(wrapper.find('[data-testid="delete-requires-reauth"]').exists()).toBe(false)
    expect(wrapper.find('#delete-password').exists()).toBe(true)
  })

  it('reopens the dialog with the ticket picked up on return from Google', async () => {
    h.hasPassword = false
    setGoogleReauthToken('reauth-abc')

    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.find('[data-testid="delete-reauth-confirmed"]').exists()).toBe(true)
    // Taken on mount: one confirmation, one use.
    expect(hasGoogleReauthToken()).toBe(false)
  })

  it('sends the ticket rather than a password', async () => {
    h.hasPassword = false
    setGoogleReauthToken('reauth-abc')

    const wrapper = mountSection()
    await flushPromises()

    await wrapper.find('[data-testid="confirm-delete-button"]').trigger('click')
    await flushPromises()

    expect(h.del).toHaveBeenCalledWith('/user/account', { data: { reauth_token: 'reauth-abc' } })
    expect(h.clearAuth).toHaveBeenCalled()
  })

  it('drops a spent ticket on failure so the user re-confirms', async () => {
    h.hasPassword = false
    setGoogleReauthToken('reauth-abc')
    h.del.mockRejectedValue(new Error('422'))

    const wrapper = mountSection()
    await flushPromises()

    await wrapper.find('[data-testid="confirm-delete-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="delete-reauth-confirmed"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="delete-requires-reauth"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Confirmation expirée.')
  })
})
