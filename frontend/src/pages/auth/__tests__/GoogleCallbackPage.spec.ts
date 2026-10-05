import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import GoogleCallbackPage from '../GoogleCallbackPage.vue'
import { getPendingGoogleRegistration } from '@/features/auth/googlePendingRegistration'
import { setPendingReauthPurpose, takeGoogleReauthTicket } from '@/features/auth/googleReauth'

const h = vi.hoisted(() => ({
  replace: vi.fn().mockResolvedValue(undefined),
  exchangeGoogleCode: vi.fn(),
  query: {} as Record<string, string>,
  auth: { isAuthenticated: false, isFace: false, isProducer: false },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: h.query }),
  useRouter: () => ({ replace: h.replace, push: vi.fn() }),
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({ exchangeGoogleCode: h.exchangeGoogleCode }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => h.auth,
}))

const NONCE = 'n'.repeat(43)

describe('GoogleCallbackPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
    sessionStorage.setItem('weact.oauth_nonce', NONCE)
    h.query = {}
    h.auth.isAuthenticated = false
    h.auth.isFace = false
    h.auth.isProducer = false
    h.replace.mockResolvedValue(undefined)
  })

  const mountPage = () =>
    mount(GoogleCallbackPage, { global: { stubs: { RouterLink: { template: '<a><slot /></a>', props: ['to'] } } } })

  /**
   * The code is a one-shot ticket to a 30-day bearer: it must leave the URL before
   * anything else, so it never lands in the history entry.
   */
  it('strips the code from the URL before exchanging it', async () => {
    h.query = { code: 'one-shot-code' }
    h.exchangeGoogleCode.mockResolvedValue({
      success: true,
      result: { needs_completion: false, redirect: null, user: { userable_type: 'Face' } },
    })

    mountPage()
    await flushPromises()

    expect(h.replace).toHaveBeenNthCalledWith(1, { name: 'google-callback' })
    expect(h.exchangeGoogleCode).toHaveBeenCalledWith('one-shot-code', NONCE)
    // The strip happens before the exchange resolves anything else.
    expect(h.replace.mock.invocationCallOrder[0]).toBeLessThan(
      h.exchangeGoogleCode.mock.invocationCallOrder[0]
    )
  })

  describe('browser binding (nonce)', () => {
    it('sends the stored nonce with the code and removes it', async () => {
      h.query = { code: 'one-shot-code' }
      h.exchangeGoogleCode.mockResolvedValue({
        success: true,
        result: { needs_completion: false, redirect: null, user: { userable_type: 'Face' } },
      })

      mountPage()
      await flushPromises()

      expect(h.exchangeGoogleCode).toHaveBeenCalledWith('one-shot-code', NONCE)
      expect(sessionStorage.getItem('weact.oauth_nonce')).toBeNull()
    })

    it('shows the expired-link error without calling the API when the nonce is missing', async () => {
      sessionStorage.clear()
      h.query = { code: 'one-shot-code' }

      const wrapper = mountPage()
      await flushPromises()

      expect(h.exchangeGoogleCode).not.toHaveBeenCalled()
      expect(wrapper.find('[data-testid="google-callback-error"]').text()).toContain(
        'Lien de connexion expiré ou invalide. Reprenez la connexion avec Google.'
      )
    })
  })

  it('sends an authenticated Face to their dashboard', async () => {
    h.query = { code: 'one-shot-code' }
    h.exchangeGoogleCode.mockResolvedValue({
      success: true,
      result: { needs_completion: false, redirect: null, user: { userable_type: 'Face' } },
    })

    mountPage()
    await flushPromises()

    expect(h.replace).toHaveBeenLastCalledWith({ name: 'face-dashboard' })
  })

  it('sends an authenticated Producer to their dashboard', async () => {
    h.query = { code: 'one-shot-code' }
    h.exchangeGoogleCode.mockResolvedValue({
      success: true,
      result: { needs_completion: false, redirect: null, user: { userable_type: 'Producer' } },
    })

    mountPage()
    await flushPromises()

    expect(h.replace).toHaveBeenLastCalledWith({ name: 'producer-dashboard' })
  })

  it('honors a valid redirect over the dashboard', async () => {
    h.query = { code: 'one-shot-code' }
    h.exchangeGoogleCode.mockResolvedValue({
      success: true,
      result: {
        needs_completion: false,
        redirect: '/pricing?plan=pro',
        user: { userable_type: 'Face' },
      },
    })

    mountPage()
    await flushPromises()

    expect(h.replace).toHaveBeenLastCalledWith('/pricing?plan=pro')
  })

  it.each(['//evil.com', '/\\evil.com'])('drops the unsafe redirect %s', async (redirect) => {
    h.query = { code: 'one-shot-code' }
    h.exchangeGoogleCode.mockResolvedValue({
      success: true,
      result: { needs_completion: false, redirect, user: { userable_type: 'Face' } },
    })

    mountPage()
    await flushPromises()

    expect(h.replace).toHaveBeenLastCalledWith({ name: 'face-dashboard' })
  })

  it('hands a brand-new account to the finalisation screen via sessionStorage', async () => {
    h.query = { code: 'one-shot-code' }
    h.exchangeGoogleCode.mockResolvedValue({
      success: true,
      result: {
        needs_completion: true,
        pending_token: 'pending-abc',
        email: 'jean@gmail.com',
        prenom: 'Jean',
        nom: 'Dupont',
        intent: 'face',
        redirect: null,
      },
    })

    mountPage()
    await flushPromises()

    expect(getPendingGoogleRegistration()).toMatchObject({
      pending_token: 'pending-abc',
      email: 'jean@gmail.com',
      prenom: 'Jean',
      nom: 'Dupont',
      intent: 'face',
    })
    expect(h.replace).toHaveBeenLastCalledWith({ name: 'google-complete-registration' })
  })

  describe('reauth result', () => {
    beforeEach(() => {
      h.query = { code: 'one-shot-code' }
      h.auth.isAuthenticated = true
    })

    it('stores the ticket under the pending purpose and returns to the screen that asked', async () => {
      setPendingReauthPurpose('set_password')
      h.exchangeGoogleCode.mockResolvedValue({
        success: true,
        result: { reauth_token: 'reauth-abc', redirect: '/face/profile' },
      })

      mountPage()
      await flushPromises()

      // A ticket of another purpose is not handed out...
      expect(takeGoogleReauthTicket('delete_account')).toBeNull()
      // ...the matching one is.
      expect(takeGoogleReauthTicket('set_password')).toBe('reauth-abc')
      expect(h.replace).toHaveBeenLastCalledWith('/face/profile')
    })

    it('does not store the ticket at all when no purpose was pending', async () => {
      h.exchangeGoogleCode.mockResolvedValue({
        success: true,
        result: { reauth_token: 'reauth-abc', redirect: '/face/profile' },
      })

      mountPage()
      await flushPromises()

      expect(takeGoogleReauthTicket('delete_account')).toBeNull()
      expect(takeGoogleReauthTicket('set_password')).toBeNull()
      expect(sessionStorage.getItem('weact.auth.google_reauth')).toBeNull()
    })

    it('falls back to the Face dashboard when no redirect came back', async () => {
      h.auth.isFace = true
      setPendingReauthPurpose('delete_account')
      h.exchangeGoogleCode.mockResolvedValue({
        success: true,
        result: { reauth_token: 'reauth-abc', redirect: null },
      })

      mountPage()
      await flushPromises()

      expect(getPendingGoogleRegistration()).toBeNull()
      expect(h.replace).toHaveBeenLastCalledWith({ name: 'face-dashboard' })
    })

    it('falls back to the Producer dashboard for a Producer', async () => {
      h.auth.isProducer = true
      setPendingReauthPurpose('delete_account')
      h.exchangeGoogleCode.mockResolvedValue({
        success: true,
        result: { reauth_token: 'reauth-abc', redirect: null },
      })

      mountPage()
      await flushPromises()

      expect(h.replace).toHaveBeenLastCalledWith({ name: 'producer-dashboard' })
    })
  })

  describe('bounced error codes', () => {
    it.each([
      ['GOOGLE_EMAIL_UNVERIFIED', "Votre adresse Google n'est pas vérifiée."],
      ['ACCOUNT_DEACTIVATED', 'Ce compte a été désactivé.'],
      ['registration_disabled', 'Les inscriptions sont temporairement suspendues.'],
      ['OAUTH_STATE_INVALID', 'Lien de connexion expiré ou invalide.'],
      ['GOOGLE_HANDSHAKE_FAILED', 'La connexion avec Google a échoué. Veuillez réessayer.'],
      ['GOOGLE_OAUTH_DISABLED', 'La connexion avec Google est indisponible.'],
      [
        'GOOGLE_ACCOUNT_NOT_LINKED',
        "Ce compte Google n'est associé à aucun compte WEACT. Connectez-vous d'abord.",
      ],
      [
        'GOOGLE_ACCOUNT_CONFLICT',
        'Cette adresse est déjà associée à un autre compte Google.',
      ],
    ])('maps %s to its message', async (code, expected) => {
      h.query = { error: code }

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="google-callback-error"]').text()).toContain(expected)
      expect(h.exchangeGoogleCode).not.toHaveBeenCalled()
    })

    it('falls back to a generic message on an unknown error code', async () => {
      h.query = { error: 'SOMETHING_NEW' }

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="google-callback-error"]').text()).toContain(
        'La connexion avec Google a échoué.'
      )
    })

    it.each(['constructor', 'toString', '__proto__', 'hasOwnProperty'])(
      'does not resolve the prototype key %s to a function',
      async (code) => {
        h.query = { error: code }

        const wrapper = mountPage()
        await flushPromises()

        const text = wrapper.find('[data-testid="google-callback-error"]').text()
        expect(text).toContain('La connexion avec Google a échoué.')
        expect(text).not.toContain('function')
      }
    )

    it('offers the way back to the login when nobody is authenticated', async () => {
      h.query = { error: 'GOOGLE_ACCOUNT_NOT_LINKED' }

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="google-callback-back-to-login"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="google-callback-back-to-profile"]').exists()).toBe(false)
    })

    it('tells an authenticated user the Google account is not theirs, with a way back to the profile', async () => {
      h.auth.isAuthenticated = true
      h.auth.isFace = true
      h.query = { error: 'GOOGLE_ACCOUNT_NOT_LINKED' }

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="google-callback-error"]').text()).toContain(
        "Ce compte Google n'est pas celui associé à votre compte WEACT."
      )
      expect(wrapper.find('[data-testid="google-callback-back-to-profile"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="google-callback-back-to-login"]').exists()).toBe(false)
    })
  })

  it('errors when the URL carries neither a code nor an error', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="google-callback-error"]').exists()).toBe(true)
    expect(h.exchangeGoogleCode).not.toHaveBeenCalled()
  })

  it('surfaces a failed exchange instead of navigating', async () => {
    h.query = { code: 'one-shot-code' }
    h.exchangeGoogleCode.mockResolvedValue({
      success: false,
      message: 'Lien de connexion expiré.',
    })

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="google-callback-error"]').text()).toContain(
      'Lien de connexion expiré.'
    )
    // Only the URL strip — no navigation away.
    expect(h.replace).toHaveBeenCalledTimes(1)
  })
})
