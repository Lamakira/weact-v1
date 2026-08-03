import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import GoogleSignInButton from '../GoogleSignInButton.vue'

const mockGetGoogleRedirectUrl = vi.fn()

vi.mock('../../services/authApi', () => ({
  authApi: {
    getGoogleRedirectUrl: (...args: unknown[]) => mockGetGoogleRedirectUrl(...args),
  },
  getApiErrorMessage: vi.fn(() => 'La connexion avec Google est indisponible.'),
}))

const mockQuery: { redirect?: string } = {}

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: mockQuery }),
}))

describe('GoogleSignInButton', () => {
  const originalLocation = window.location

  beforeEach(() => {
    vi.clearAllMocks()
    delete mockQuery.redirect
    mockGetGoogleRedirectUrl.mockResolvedValue('https://accounts.google.com/o/oauth2/auth?state=abc')

    // The button navigates the top-level document; jsdom cannot, so replace it.
    Object.defineProperty(window, 'location', {
      configurable: true,
      writable: true,
      value: { ...originalLocation, href: '' },
    })
  })

  const mountButton = (props: Record<string, unknown> = {}) =>
    mount(GoogleSignInButton, { props: { intent: 'login', ...props } })

  it('navigates to the URL the backend hands back', async () => {
    const wrapper = mountButton()

    await wrapper.find('[data-testid="google-sign-in-button"]').trigger('click')
    await flushPromises()

    expect(mockGetGoogleRedirectUrl).toHaveBeenCalledWith('login', null)
    expect(window.location.href).toBe('https://accounts.google.com/o/oauth2/auth?state=abc')
  })

  it('forwards the current ?redirect= so the deep-link survives the round-trip', async () => {
    mockQuery.redirect = '/pricing?plan=pro'
    const wrapper = mountButton({ intent: 'face' })

    await wrapper.find('[data-testid="google-sign-in-button"]').trigger('click')
    await flushPromises()

    expect(mockGetGoogleRedirectUrl).toHaveBeenCalledWith('face', '/pricing?plan=pro')
  })

  it('does nothing while disabled — the CGU gate', async () => {
    const wrapper = mountButton({ disabled: true })

    const button = wrapper.find('[data-testid="google-sign-in-button"]')
    expect(button.attributes('disabled')).toBeDefined()

    await button.trigger('click')
    await flushPromises()

    expect(mockGetGoogleRedirectUrl).not.toHaveBeenCalled()
    expect(window.location.href).toBe('')
  })

  it('surfaces an error instead of navigating when the backend refuses', async () => {
    mockGetGoogleRedirectUrl.mockRejectedValue(new Error('403'))
    const wrapper = mountButton()

    await wrapper.find('[data-testid="google-sign-in-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="google-sign-in-error"]').exists()).toBe(true)
    expect(window.location.href).toBe('')
    // Re-enabled so the user can retry.
    expect(wrapper.find('[data-testid="google-sign-in-button"]').attributes('disabled')).toBeUndefined()
  })

  it('renders the provided label', () => {
    const wrapper = mountButton({ label: "S'inscrire avec Google" })

    expect(wrapper.text()).toContain("S'inscrire avec Google")
  })
})
