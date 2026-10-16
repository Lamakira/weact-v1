import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { defineComponent } from 'vue'
import DataPrivacySection from '../DataPrivacySection.vue'

const h = vi.hoisted(() => ({
  clearAuth: vi.fn(),
  del: vi.fn(),
  toastSuccess: vi.fn(),
  toastWarning: vi.fn(),
  toastInfo: vi.fn(),
  allowLeave: true,
}))

vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn(), delete: (...args: unknown[]) => h.del(...args) },
  getCsrfCookie: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    hasPassword: true,
    user: { id: 5 },
    clearAuth: h.clearAuth,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: h.toastSuccess,
    error: vi.fn(),
    warning: h.toastWarning,
    info: h.toastInfo,
  }),
}))

const Stub = defineComponent({ template: '<div />' })

function makeRouter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: Stub },
      { path: '/profile', name: 'profile', component: Stub },
    ],
  })
  // A guard that aborts the navigation away from the profile page (stands for
  // the unsaved-changes leave guard of ProfileEditPage).
  router.beforeEach((to, from) => {
    if (from.name === 'profile' && to.name === 'home' && !h.allowLeave) return false
    return true
  })
  return router
}

const stubs = {
  GoogleSignInButton: true,
  // Render the teleported modal inline so it can be queried.
  Teleport: { template: '<div><slot /></div>' },
}

const DIALOG = '[data-testid="confirm-delete-button"]'

describe('DataPrivacySection — après suppression du compte', () => {
  const assign = vi.fn()
  const originalLocation = window.location

  beforeEach(() => {
    vi.clearAllMocks()
    h.allowLeave = true
    h.del.mockResolvedValue({ data: { message: 'Compte supprimé' } })
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { ...originalLocation, assign },
    })
  })

  afterEach(() => {
    Object.defineProperty(window, 'location', { configurable: true, value: originalLocation })
  })

  async function mountOnProfile() {
    const router = makeRouter()
    await router.push('/profile')
    await router.isReady()
    const wrapper = mount(DataPrivacySection, { global: { plugins: [router], stubs } })
    await flushPromises()
    await wrapper.find('[data-testid="delete-account-button"]').trigger('click')
    await wrapper.find('#delete-password').setValue('secret')
    return { router, wrapper }
  }

  it('closes the dialog, clears auth and lands on home after a successful deletion', async () => {
    const { router, wrapper } = await mountOnProfile()

    await wrapper.find(DIALOG).trigger('click')
    await flushPromises()

    expect(h.clearAuth).toHaveBeenCalledTimes(1)
    expect(wrapper.find(DIALOG).exists()).toBe(false)
    expect(router.currentRoute.value.name).toBe('home')
    expect(assign).not.toHaveBeenCalled()
    expect(h.toastSuccess).toHaveBeenCalledWith('Compte supprimé')
  })

  it('falls back to a full page load on home when the navigation is aborted', async () => {
    h.allowLeave = false
    const { router, wrapper } = await mountOnProfile()

    await wrapper.find(DIALOG).trigger('click')
    await flushPromises()

    expect(h.clearAuth).toHaveBeenCalledTimes(1)
    expect(wrapper.find(DIALOG).exists()).toBe(false)
    expect(router.currentRoute.value.name).toBe('profile')
    expect(assign).toHaveBeenCalledWith('/')
  })

  it('treats a 401 as an already-deleted or expired session: logout, home, info toast', async () => {
    h.del.mockRejectedValue({ isAxiosError: true, response: { status: 401, data: { message: 'Non authentifié.' } } })
    const { router, wrapper } = await mountOnProfile()

    await wrapper.find(DIALOG).trigger('click')
    await flushPromises()

    expect(h.clearAuth).toHaveBeenCalledTimes(1)
    expect(wrapper.find(DIALOG).exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Non authentifié.')
    expect(router.currentRoute.value.name).toBe('home')
    expect(h.toastInfo).toHaveBeenCalledWith(
      'Votre session a expiré. Reconnectez-vous si votre compte existe encore.'
    )
    expect(h.toastSuccess).not.toHaveBeenCalled()
  })

  it('ignores a second confirmation while a deletion is already in flight', async () => {
    let resolveDelete: (v: unknown) => void = () => {}
    h.del.mockReturnValue(new Promise((resolve) => (resolveDelete = resolve)))
    const { wrapper } = await mountOnProfile()

    await wrapper.find('#delete-password').trigger('keyup.enter')
    await wrapper.find('#delete-password').trigger('keyup.enter')
    await flushPromises()

    expect(h.del).toHaveBeenCalledTimes(1)
    resolveDelete({ data: {} })
    await flushPromises()
  })
})
