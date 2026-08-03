import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import GoogleCompleteRegistrationPage from '../GoogleCompleteRegistrationPage.vue'
import {
  setPendingGoogleRegistration,
  getPendingGoogleRegistration,
  type PendingGoogleRegistration,
} from '@/features/auth/googlePendingRegistration'

const h = vi.hoisted(() => ({
  replace: vi.fn().mockResolvedValue(undefined),
  completeGoogleRegistration: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ replace: h.replace, push: vi.fn() }),
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

const mockIsLoading = ref(false)

vi.mock('@/features/auth/composables/useAuth', () => ({
  useAuth: () => ({
    completeGoogleRegistration: h.completeGoogleRegistration,
    isLoading: mockIsLoading,
  }),
}))

const pending = (overrides: Partial<PendingGoogleRegistration> = {}): PendingGoogleRegistration => ({
  pending_token: 'pending-abc',
  email: 'jean@gmail.com',
  prenom: 'Jean',
  nom: 'Dupont',
  intent: 'face',
  redirect: null,
  ...overrides,
})

describe('GoogleCompleteRegistrationPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
    mockIsLoading.value = false
    h.completeGoogleRegistration.mockResolvedValue({ success: true })
  })

  const mountPage = () => mount(GoogleCompleteRegistrationPage)

  it('bounces to login when there is no pending registration', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(h.replace).toHaveBeenCalledWith({ name: 'login' })
    expect(wrapper.find('[data-testid="google-complete-form"]').exists()).toBe(false)
  })

  it('prefills the name Google returned and shows the address in use', async () => {
    setPendingGoogleRegistration(pending())

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.text()).toContain('jean@gmail.com')
    expect((wrapper.find('[data-testid="prenom-input"]').element as HTMLInputElement).value).toBe(
      'Jean'
    )
    expect((wrapper.find('[data-testid="nom-input"]').element as HTMLInputElement).value).toBe(
      'Dupont'
    )
  })

  /**
   * Google returns no date of birth: this screen is what keeps the 16+ gate on the
   * Google path.
   */
  it('asks a Face for their date of birth', async () => {
    setPendingGoogleRegistration(pending())

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="date-naissance-input"]').exists()).toBe(true)
  })

  it('locks the role when the user came from a register button', async () => {
    setPendingGoogleRegistration(pending({ intent: 'producer' }))

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="role-selector"]').exists()).toBe(false)
    // Producer branch preselected.
    expect(wrapper.find('[data-testid="type-selector"]').exists()).toBe(true)
  })

  it('offers the role choice only when the user came from /login', async () => {
    setPendingGoogleRegistration(pending({ intent: 'login' }))

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="role-selector"]').exists()).toBe(true)
  })

  it('submits the Face payload', async () => {
    setPendingGoogleRegistration(pending())

    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-testid="date-naissance-input"]').setValue('1995-06-15')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="google-complete-form"]').trigger('submit')
    await flushPromises()

    expect(h.completeGoogleRegistration).toHaveBeenCalledWith({
      pending_token: 'pending-abc',
      role: 'face',
      accept_cgu: true,
      nom: 'Dupont',
      prenom: 'Jean',
      date_naissance: '1995-06-15',
    })
    expect(h.replace).toHaveBeenLastCalledWith({ name: 'face-upsell' })
  })

  it('submits the agency payload', async () => {
    setPendingGoogleRegistration(pending({ intent: 'producer' }))

    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-testid="nom-input"]').setValue('Studio Pro')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="google-complete-form"]').trigger('submit')
    await flushPromises()

    expect(h.completeGoogleRegistration).toHaveBeenCalledWith({
      pending_token: 'pending-abc',
      role: 'producer',
      accept_cgu: true,
      type: 'agency',
      agency_name: 'Studio Pro',
    })
    expect(h.replace).toHaveBeenLastCalledWith({ name: 'producer-dashboard' })
  })

  it('submits the particulier payload as nom_complet', async () => {
    setPendingGoogleRegistration(pending({ intent: 'producer' }))

    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-testid="type-particulier-button"]').trigger('click')
    await wrapper.find('[data-testid="nom-input"]').setValue('Marie Sossou')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="google-complete-form"]').trigger('submit')
    await flushPromises()

    expect(h.completeGoogleRegistration).toHaveBeenCalledWith({
      pending_token: 'pending-abc',
      role: 'producer',
      accept_cgu: true,
      type: 'particulier',
      nom_complet: 'Marie Sossou',
    })
  })

  it('honors a redirect over the default landing page', async () => {
    setPendingGoogleRegistration(pending({ redirect: '/pricing?plan=pro' }))

    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-testid="date-naissance-input"]').setValue('1995-06-15')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="google-complete-form"]').trigger('submit')
    await flushPromises()

    expect(h.replace).toHaveBeenLastCalledWith('/pricing?plan=pro')
  })

  it('clears the pending ticket on success so it cannot be replayed', async () => {
    setPendingGoogleRegistration(pending())

    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-testid="date-naissance-input"]').setValue('1995-06-15')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="google-complete-form"]').trigger('submit')
    await flushPromises()

    expect(getPendingGoogleRegistration()).toBeNull()
  })

  it('keeps the ticket and shows the field errors on failure', async () => {
    setPendingGoogleRegistration(pending())
    h.completeGoogleRegistration.mockResolvedValue({
      success: false,
      errors: { date_naissance: ['Vous devez avoir au moins 16 ans pour vous inscrire.'] },
    })

    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="google-complete-form"]').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Vous devez avoir au moins 16 ans')
    expect(getPendingGoogleRegistration()).not.toBeNull()
  })

  it('maps the per-type server error onto the single name input', async () => {
    setPendingGoogleRegistration(pending({ intent: 'producer' }))
    h.completeGoogleRegistration.mockResolvedValue({
      success: false,
      errors: { agency_name: ["Le nom de l'agence est obligatoire"] },
    })

    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="google-complete-form"]').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain("Le nom de l'agence est obligatoire")
  })

  it('states the 16+ requirement in the Face consent label only', async () => {
    setPendingGoogleRegistration(pending())

    const faceWrapper = mountPage()
    await flushPromises()
    expect(faceWrapper.find('[data-testid="accept-cgu-field"]').text()).toContain('16 ans ou plus')

    sessionStorage.clear()
    setPendingGoogleRegistration(pending({ intent: 'producer' }))

    const producerWrapper = mountPage()
    await flushPromises()
    expect(producerWrapper.find('[data-testid="accept-cgu-field"]').text()).not.toContain(
      '16 ans ou plus'
    )
  })
})
