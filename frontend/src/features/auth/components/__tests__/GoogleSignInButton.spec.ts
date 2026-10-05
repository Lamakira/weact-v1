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
const mockRoute = { query: mockQuery, fullPath: '/face/profile?tab=compte' }

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
}))

describe('GoogleSignInButton', () => {
  const originalLocation = window.location

  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
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

    expect(mockGetGoogleRedirectUrl).toHaveBeenCalledWith('login', null, expect.any(String))
    expect(window.location.href).toBe('https://accounts.google.com/o/oauth2/auth?state=abc')
  })

  it('forwards the current ?redirect= so the deep-link survives the round-trip', async () => {
    mockQuery.redirect = '/pricing?plan=pro'
    const wrapper = mountButton({ intent: 'face' })

    await wrapper.find('[data-testid="google-sign-in-button"]').trigger('click')
    await flushPromises()

    expect(mockGetGoogleRedirectUrl).toHaveBeenCalledWith(
      'face',
      '/pricing?plan=pro',
      expect.any(String)
    )
  })

  it('sends a 43-char base64url nonce and stores it for the callback', async () => {
    const wrapper = mountButton()

    await wrapper.find('[data-testid="google-sign-in-button"]').trigger('click')
    await flushPromises()

    const nonce = mockGetGoogleRedirectUrl.mock.calls[0][2] as string
    expect(nonce).toMatch(/^[A-Za-z0-9_-]{43}$/)
    expect(sessionStorage.getItem('weact.oauth_nonce')).toBe(nonce)
  })

  describe('reauth intent', () => {
    it('sends route.fullPath (not ?redirect=) so the user comes back to the asking screen', async () => {
      mockQuery.redirect = '/somewhere/else'
      const wrapper = mountButton({ intent: 'reauth', reauthPurpose: 'delete_account' })

      await wrapper.find('[data-testid="google-sign-in-button"]').trigger('click')
      await flushPromises()

      expect(mockGetGoogleRedirectUrl).toHaveBeenCalledWith(
        'reauth',
        '/face/profile?tab=compte',
        expect.any(String)
      )
    })

    it('stores the purpose before navigating', async () => {
      const wrapper = mountButton({ intent: 'reauth', reauthPurpose: 'set_password' })

      await wrapper.find('[data-testid="google-sign-in-button"]').trigger('click')
      await flushPromises()

      expect(sessionStorage.getItem('weact.auth.google_reauth_purpose')).toBe('set_password')
    })

    it('does not store a purpose for a non-reauth intent', async () => {
      const wrapper = mountButton({ intent: 'login', reauthPurpose: 'set_password' })

      await wrapper.find('[data-testid="google-sign-in-button"]').trigger('click')
      await flushPromises()

      expect(sessionStorage.getItem('weact.auth.google_reauth_purpose')).toBeNull()
    })
  })

  it('resets the loading state when restored from the back/forward cache', async () => {
    const wrapper = mountButton()

    await wrapper.find('[data-testid="google-sign-in-button"]').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Redirection…')

    const event = new Event('pageshow') as PageTransitionEvent
    Object.defineProperty(event, 'persisted', { value: true })
    window.dispatchEvent(event)
    await flushPromises()

    expect(wrapper.text()).not.toContain('Redirection…')
    expect(wrapper.find('[data-testid="google-sign-in-button"]').attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  })

  it('keeps the loading state on a non-persisted pageshow', async () => {
    const wrapper = mountButton()

    await wrapper.find('[data-testid="google-sign-in-button"]').trigger('click')
    await flushPromises()

    const event = new Event('pageshow') as PageTransitionEvent
    Object.defineProperty(event, 'persisted', { value: false })
    window.dispatchEvent(event)
    await flushPromises()

    expect(wrapper.text()).toContain('Redirection…')
    wrapper.unmount()
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
