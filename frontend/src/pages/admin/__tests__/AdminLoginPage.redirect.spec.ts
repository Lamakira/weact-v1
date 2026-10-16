import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'

const mockPush = vi.fn()
let mockQuery: Record<string, unknown> = {}
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush, replace: vi.fn() }),
  useRoute: () => ({ query: mockQuery }),
  RouterLink: { template: '<a><slot /></a>' },
}))

const mockLogin = vi.fn()
vi.mock('@/features/admin/composables/useAdminAuth', () => ({
  useAdminAuth: () => ({
    login: mockLogin,
    verifyTwoFactor: vi.fn(),
    isLoading: ref(false),
  }),
}))

import AdminLoginPage from '../AdminLoginPage.vue'
import { useAdminAuthStore } from '@/stores/adminAuth'

async function loginWithRedirect(redirect: unknown): Promise<void> {
  mockQuery = redirect === undefined ? {} : { redirect }
  mockLogin.mockImplementation(async () => {
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
  await wrapper.find('[data-testid="email-input"]').setValue('admin@weact.bj')
  await wrapper.find('[data-testid="password-input"]').setValue('Password1')
  await wrapper.find('[data-testid="admin-login-form"]').trigger('submit')
  await vi.waitFor(() => expect(mockPush).toHaveBeenCalled())
  await flushPromises()
}

describe('AdminLoginPage - redirect après connexion', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('suit un redirect interne à /admin', async () => {
    await loginWithRedirect('/admin/missions?page=2')
    expect(mockPush).toHaveBeenCalledWith('/admin/missions?page=2')
  })

  it.each([
    ['URL absolue', 'https://evil.example/admin'],
    ['URL protocole-relatif', '//evil.example'],
    ['backslash', '/\\evil.example'],
    ['chemin hors admin', '/dashboard'],
    ['préfixe trompeur', '/administrator'],
    ['tableau (redirect dupliqué)', ['/admin/a', '/admin/b']],
  ])('retombe sur le tableau de bord admin pour : %s', async (_label, value) => {
    await loginWithRedirect(value)
    expect(mockPush).toHaveBeenCalledWith({ name: 'admin-dashboard' })
  })

  it('utilise le tableau de bord admin sans redirect', async () => {
    await loginWithRedirect(undefined)
    expect(mockPush).toHaveBeenCalledWith({ name: 'admin-dashboard' })
  })
})
