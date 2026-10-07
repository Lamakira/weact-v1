import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'

const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush, replace: vi.fn() }),
  useRoute: () => ({ query: {} }),
  RouterLink: { template: '<a><slot /></a>' },
}))

const mockLogin = vi.fn()
const mockVerifyTwoFactor = vi.fn()
vi.mock('@/features/admin/composables/useAdminAuth', () => ({
  useAdminAuth: () => ({
    login: mockLogin,
    verifyTwoFactor: mockVerifyTwoFactor,
    isLoading: ref(false),
  }),
}))

import AdminLoginPage from '../AdminLoginPage.vue'
import { useAdminAuthStore } from '@/stores/adminAuth'

async function submitCredentials(wrapper: ReturnType<typeof mount>): Promise<void> {
  await wrapper.find('[data-testid="email-input"]').setValue('admin@weact.bj')
  await wrapper.find('[data-testid="password-input"]').setValue('Password1')
  await wrapper.find('[data-testid="admin-login-form"]').trigger('submit')
  // vee-validate validates asynchronously before calling the submit handler
  await vi.waitFor(() => expect(mockLogin).toHaveBeenCalled())
  await flushPromises()
}

describe('AdminLoginPage - two-factor flow', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('shows the code step when the password step returns a challenge', async () => {
    mockLogin.mockResolvedValue({ success: false, twoFactorChallenge: 'chal-1' })
    const wrapper = mount(AdminLoginPage)

    await submitCredentials(wrapper)

    expect(wrapper.find('[data-testid="admin-two-factor-form"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="admin-login-form"]').exists()).toBe(false)
    expect(mockPush).not.toHaveBeenCalled()
  })

  it('submits the TOTP code with the challenge and redirects on success', async () => {
    mockLogin.mockResolvedValue({ success: false, twoFactorChallenge: 'chal-1' })
    mockVerifyTwoFactor.mockImplementation(async () => {
      useAdminAuthStore().setAdmin({
        id: 'a1',
        name: 'Admin',
        email: 'admin@weact.bj',
        role: 'admin',
        two_factor_enabled: true,
      })
      return { success: true }
    })
    const wrapper = mount(AdminLoginPage)
    await submitCredentials(wrapper)

    await wrapper.find('[data-testid="two-factor-code-input"]').setValue('123456')
    await wrapper.find('[data-testid="admin-two-factor-form"]').trigger('submit')
    await flushPromises()

    expect(mockVerifyTwoFactor).toHaveBeenCalledWith({ challenge: 'chal-1', code: '123456' })
    expect(mockPush).toHaveBeenCalledWith({ name: 'admin-dashboard' })
  })

  it('switches to a recovery code and sends it as recovery_code', async () => {
    mockLogin.mockResolvedValue({ success: false, twoFactorChallenge: 'chal-1' })
    mockVerifyTwoFactor.mockResolvedValue({ success: false, message: 'Code de vérification incorrect' })
    const wrapper = mount(AdminLoginPage)
    await submitCredentials(wrapper)

    await wrapper.find('[data-testid="toggle-recovery-code"]').trigger('click')
    await wrapper.find('[data-testid="two-factor-code-input"]').setValue('aaaaa-bbbbb')
    await wrapper.find('[data-testid="admin-two-factor-form"]').trigger('submit')
    await flushPromises()

    expect(mockVerifyTwoFactor).toHaveBeenCalledWith({
      challenge: 'chal-1',
      recovery_code: 'aaaaa-bbbbb',
    })
    expect(wrapper.find('[data-testid="api-error"]').text()).toContain('Code de vérification incorrect')
  })

  it('returns to the credentials step when the challenge has expired', async () => {
    mockLogin.mockResolvedValue({ success: false, twoFactorChallenge: 'chal-1' })
    mockVerifyTwoFactor.mockResolvedValue({
      success: false,
      message: 'La session de connexion a expiré. Veuillez vous reconnecter.',
      errorCode: 'TWO_FACTOR_CHALLENGE_INVALID',
    })
    const wrapper = mount(AdminLoginPage)
    await submitCredentials(wrapper)

    await wrapper.find('[data-testid="two-factor-code-input"]').setValue('123456')
    await wrapper.find('[data-testid="admin-two-factor-form"]').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[data-testid="admin-login-form"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="api-error"]').text()).toContain('expiré')
  })

  it('redirects an admin without 2FA to the enrolment page after a plain login', async () => {
    mockLogin.mockImplementation(async () => {
      useAdminAuthStore().setAdmin({
        id: 'a1',
        name: 'Admin',
        email: 'admin@weact.bj',
        role: 'admin',
        two_factor_enabled: false,
      })
      return { success: true }
    })
    const wrapper = mount(AdminLoginPage)

    await submitCredentials(wrapper)

    expect(mockPush).toHaveBeenCalledWith({ name: 'admin-two-factor-setup' })
  })
})
