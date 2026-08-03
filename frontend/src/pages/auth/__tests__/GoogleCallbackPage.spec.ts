import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import GoogleCallbackPage from '../GoogleCallbackPage.vue'
import { getPendingGoogleRegistration } from '@/features/auth/googlePendingRegistration'

const h = vi.hoisted(() => ({
  replace: vi.fn().mockResolvedValue(undefined),
  exchangeGoogleCode: vi.fn(),
  query: {} as Record<string, string>,
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: h.query }),
  useRouter: () => ({ replace: h.replace, push: vi.fn() }),
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({ exchangeGoogleCode: h.exchangeGoogleCode }),
}))

describe('GoogleCallbackPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
    h.query = {}
    h.replace.mockResolvedValue(undefined)
  })

  const mountPage = () => mount(GoogleCallbackPage)

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
    expect(h.exchangeGoogleCode).toHaveBeenCalledWith('one-shot-code')
    // The strip happens before the exchange resolves anything else.
    expect(h.replace.mock.invocationCallOrder[0]).toBeLessThan(
      h.exchangeGoogleCode.mock.invocationCallOrder[0]
    )
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

  it('drops a protocol-relative redirect', async () => {
    h.query = { code: 'one-shot-code' }
    h.exchangeGoogleCode.mockResolvedValue({
      success: true,
      result: { needs_completion: false, redirect: '//evil.com', user: { userable_type: 'Face' } },
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

  it('renders the backend error bounced back on the callback', async () => {
    h.query = { error: 'GOOGLE_EMAIL_UNVERIFIED' }

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="google-callback-error"]').text()).toContain(
      "n'est pas vérifiée"
    )
    expect(h.exchangeGoogleCode).not.toHaveBeenCalled()
  })

  it('maps a deactivated account to its own message', async () => {
    h.query = { error: 'ACCOUNT_DEACTIVATED' }

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="google-callback-error"]').text()).toContain('désactivé')
  })

  it('falls back to a generic message on an unknown error code', async () => {
    h.query = { error: 'SOMETHING_NEW' }

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="google-callback-error"]').exists()).toBe(true)
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
