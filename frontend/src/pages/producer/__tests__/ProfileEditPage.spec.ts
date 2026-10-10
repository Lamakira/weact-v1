import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { enableAutoUnmount, flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import type { Ref } from 'vue'
import ProfileEditPage from '../ProfileEditPage.vue'

// The composables must hand back genuine refs: the page uses them bare in the
// template and reads them in watchers.
const h = vi.hoisted(() => ({
  query: {} as Record<string, string>,
  routerPush: vi.fn(),
  routerReplace: vi.fn(),
  leaveGuard: null as null | (() => unknown),
  toastSuccess: vi.fn(),
  fetchProfile: vi.fn().mockResolvedValue(undefined),
  fetchBio: vi.fn().mockResolvedValue(undefined),
  fetchBasicInfo: vi.fn().mockResolvedValue(undefined),
  saveBio: vi.fn(),
  updateBasicInfo: vi.fn(),
  uploadPhoto: vi.fn(),
  deletePhoto: vi.fn(),
  refs: {} as {
    profile: Ref<Record<string, unknown> | null>
    isLoading: Ref<boolean>
    bio: Ref<string | null>
    isBioLoading: Ref<boolean>
    bioError: Ref<string | null>
    basicInfo: Ref<Record<string, unknown> | null>
    basicLoading: Ref<boolean>
    basicError: Ref<string | null>
  },
}))

vi.mock('@/features/producer/composables/useProducerProfilePhoto', async () => {
  const { ref } = await import('vue')
  h.refs.profile = ref(null)
  h.refs.isLoading = ref(false)
  return {
    useProducerProfilePhoto: () => ({
      profile: h.refs.profile,
      isLoading: h.refs.isLoading,
      isUploading: ref(false),
      isDeleting: ref(false),
      error: ref(null),
      fetchProfile: h.fetchProfile,
      uploadPhoto: h.uploadPhoto,
      deletePhoto: h.deletePhoto,
    }),
  }
})

vi.mock('@/features/producer/composables/useProducerBio', async () => {
  const { ref } = await import('vue')
  h.refs.bio = ref(null)
  h.refs.isBioLoading = ref(false)
  h.refs.bioError = ref(null)
  return {
    useProducerBio: () => ({
      bio: h.refs.bio,
      isLoading: h.refs.isBioLoading,
      isSaving: ref(false),
      error: h.refs.bioError,
      fetchBio: h.fetchBio,
      saveBio: h.saveBio,
    }),
  }
})

vi.mock('@/features/producer/composables/useProducerBasicInfo', async () => {
  const { ref } = await import('vue')
  h.refs.basicInfo = ref(null)
  h.refs.basicLoading = ref(false)
  h.refs.basicError = ref(null)
  return {
    useProducerBasicInfo: () => ({
      basicInfo: h.refs.basicInfo,
      isLoading: h.refs.basicLoading,
      isSaving: ref(false),
      error: h.refs.basicError,
      fetchBasicInfo: h.fetchBasicInfo,
      updateBasicInfo: h.updateBasicInfo,
      clearError: vi.fn(),
    }),
  }
})

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: h.toastSuccess, error: vi.fn() }),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/producer/profile', query: h.query }),
  useRouter: () => ({ push: h.routerPush, replace: h.routerReplace }),
  onBeforeRouteLeave: (guard: () => unknown) => {
    h.leaveGuard = guard
  },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { id: 7, email: 'prod@example.com', userable: { slug: 'studio-awale' } },
    hasPassword: true,
  }),
}))

const stubs = {
  RouterLink: RouterLinkStub,
  ProducerProfilePhotoUpload: { name: 'ProducerProfilePhotoUpload', template: '<div data-testid="photo-upload-stub" />' },
  AgencyLogoUpload: { template: '<div data-testid="agency-logo-stub" />' },
  EmailChangeForm: { template: '<div data-testid="email-change-stub" />' },
  PasswordChangeForm: { template: '<div data-testid="password-change-stub" />' },
  DataPrivacySection: { template: '<div data-testid="data-privacy-stub" />' },
  ConfirmModal: {
    props: ['isOpen'],
    emits: ['confirm', 'cancel'],
    template:
      '<div v-if="isOpen" data-testid="leave-modal"><button data-testid="leave-confirm" @click="$emit(\'confirm\')" /><button data-testid="leave-cancel" @click="$emit(\'cancel\')" /></div>',
  },
}

const mountPage = () => mount(ProfileEditPage, { global: { stubs } })

const SAVE_BAR = '[data-testid="r-sticky-save-bar"]'

function setAgency(): void {
  h.refs.profile.value = {
    type: 'agency',
    agency_logo_url: 'https://cdn/logo.png',
    profile_photo_url: null,
  }
  h.refs.basicInfo.value = {
    type: 'agency',
    agency_name: 'Studio Awalé',
    whatsapp_number: '+229 01 97 45 22 10',
  }
  h.refs.bio.value = 'Agence de production.'
}

function setParticulier(): void {
  h.refs.profile.value = {
    type: 'particulier',
    agency_logo_url: null,
    profile_photo_url: 'https://cdn/photo.jpg',
  }
  h.refs.basicInfo.value = {
    type: 'particulier',
    first_name: 'Marie',
    last_name: 'Martin',
    whatsapp_number: null,
  }
  h.refs.bio.value = 'Productrice indépendante.'
}

enableAutoUnmount(afterEach)

describe('Producer ProfileEditPage — Régie settings page', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    h.query = {}
    h.leaveGuard = null
    h.refs.isLoading.value = false
    h.refs.isBioLoading.value = false
    h.refs.basicLoading.value = false
    h.refs.bioError.value = null
    h.refs.basicError.value = null
    h.saveBio.mockResolvedValue({ success: true, message: 'Bio mise à jour avec succès' })
    h.updateBasicInfo.mockResolvedValue({ success: true, message: 'Informations mises à jour' })
    setAgency()
  })

  describe('fetching', () => {
    it('fetches profile, bio and basic info on mount', async () => {
      mountPage()
      await flushPromises()

      expect(h.fetchProfile).toHaveBeenCalledOnce()
      expect(h.fetchBio).toHaveBeenCalledOnce()
      expect(h.fetchBasicInfo).toHaveBeenCalledOnce()
    })
  })

  describe('tabs', () => {
    it('renders the four section tabs, Profil public selected by default', async () => {
      const wrapper = mountPage()
      await flushPromises()

      const tabs = wrapper.findAll('[role="tab"]')
      expect(tabs.map((t) => t.text())).toEqual([
        'Profil public',
        'Coordonnées',
        'Sécurité',
        'Données personnelles',
      ])
      expect(tabs[0]!.attributes('aria-selected')).toBe('true')
      expect(wrapper.find('[data-testid="profile-tabs"]').classes()).toContain('overflow-x-auto')
    })

    it('opens the tab given by ?tab=', async () => {
      h.query = { tab: 'coordonnees' }
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="profile-tab-coordonnees"]').attributes('aria-selected')).toBe('true')
      expect(wrapper.find('[data-testid="profile-panel-coordonnees"]').isVisible()).toBe(true)
      expect(wrapper.find('[data-testid="profile-panel-profil"]').attributes('style')).toContain('display: none')
    })

    it('ignores an unknown ?tab= value', async () => {
      h.query = { tab: 'nope' }
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="profile-tab-profil"]').attributes('aria-selected')).toBe('true')
    })

    it('reflects a click in the URL, keeping the other query params', async () => {
      h.query = { other: '1' }
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="profile-tab-securite"]').trigger('click')

      expect(h.routerPush).toHaveBeenCalledWith({ query: { other: '1', tab: 'securite' } })
      expect(wrapper.find('[data-testid="profile-panel-securite"]').isVisible()).toBe(true)
    })

    it('does not mount the security/email/privacy forms until their tab is opened (no password-manager popup)', async () => {
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="password-change-stub"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="email-change-stub"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="data-privacy-stub"]').exists()).toBe(false)

      await wrapper.find('[data-testid="profile-tab-securite"]').trigger('click')
      expect(wrapper.find('[data-testid="password-change-stub"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="email-change-stub"]').exists()).toBe(false)

      await wrapper.find('[data-testid="profile-tab-coordonnees"]').trigger('click')
      expect(wrapper.find('[data-testid="email-change-stub"]').exists()).toBe(true)

      await wrapper.find('[data-testid="profile-tab-donnees"]').trigger('click')
      expect(wrapper.find('[data-testid="data-privacy-stub"]').exists()).toBe(true)
    })

    it('puts the data section in a red-bordered danger zone', async () => {
      h.query = { tab: 'donnees' }
      const wrapper = mountPage()
      await flushPromises()

      const zone = wrapper.find('[data-testid="danger-zone"]')
      expect(zone.exists()).toBe(true)
      expect(zone.classes().join(' ')).toContain('red')
      expect(zone.find('[data-testid="data-privacy-stub"]').exists()).toBe(true)
    })
  })

  describe('Profil public', () => {
    it('shows the agency logo upload and the agency name for an agency', async () => {
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="agency-logo-section"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="photo-upload-stub"]').exists()).toBe(false)
      expect((wrapper.find('[data-testid="agency-name-input"]').element as HTMLInputElement).value).toBe(
        'Studio Awalé'
      )
      expect(wrapper.find('[data-testid="first-name-input"]').exists()).toBe(false)
    })

    it('shows the photo upload and first/last name for an individual', async () => {
      setParticulier()
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="photo-upload-stub"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="agency-logo-section"]').exists()).toBe(false)
      expect((wrapper.find('[data-testid="first-name-input"]').element as HTMLInputElement).value).toBe('Marie')
      expect((wrapper.find('[data-testid="last-name-input"]').element as HTMLInputElement).value).toBe('Martin')
      expect(wrapper.find('[data-testid="agency-name-input"]').exists()).toBe(false)
    })

    it('keeps the photo upload wired to the composable (upload and delete)', async () => {
      setParticulier()
      h.uploadPhoto.mockResolvedValue({ success: true, message: 'Photo mise à jour' })
      h.deletePhoto.mockResolvedValue({ success: true, message: 'Photo supprimée' })
      const wrapper = mountPage()
      await flushPromises()

      const upload = wrapper.findComponent({ name: 'ProducerProfilePhotoUpload' })
      const file = new File(['x'], 'p.png', { type: 'image/png' })
      upload.vm.$emit('upload', file)
      await flushPromises()
      expect(h.uploadPhoto).toHaveBeenCalledWith(file)
      expect(h.toastSuccess).toHaveBeenCalledWith('Photo mise à jour')

      upload.vm.$emit('delete')
      await flushPromises()
      expect(h.deletePhoto).toHaveBeenCalledOnce()
      expect(h.toastSuccess).toHaveBeenCalledWith('Photo supprimée')
    })

    it('shows the bio with a 500 characters counter', async () => {
      const wrapper = mountPage()
      await flushPromises()

      expect((wrapper.find('[data-testid="bio-textarea"]').element as HTMLTextAreaElement).value).toBe(
        'Agence de production.'
      )
      expect(wrapper.find('[data-testid="bio-char-count"]').text()).toBe('21/500')
    })

    it('flags a bio over the limit and refuses to save it', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="bio-textarea"]').setValue('x'.repeat(520))
      expect(wrapper.find('[data-testid="bio-over-limit-warning"]').text()).toContain('500 caractères')

      await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
      await flushPromises()
      expect(h.saveBio).not.toHaveBeenCalled()
    })

    it('links to the public page of the Producer by slug, in a new tab', async () => {
      const wrapper = mountPage()
      await flushPromises()

      const link = wrapper.findComponent(RouterLinkStub)
      expect(link.props('to')).toEqual({ name: 'public-producer-profile', params: { slug: 'studio-awale' } })
      expect(link.text()).toContain('Voir ma fiche publique')
      expect(link.attributes('target')).toBe('_blank')
    })
  })

  describe('Coordonnées', () => {
    it('shows the WhatsApp number with its admin-only note, the read-only email and the locked account type', async () => {
      h.query = { tab: 'coordonnees' }
      const wrapper = mountPage()
      await flushPromises()

      const input = wrapper.find('[data-testid="whatsapp-number-input"]')
      expect((input.element as HTMLInputElement).value).toBe('+229 01 97 45 22 10')
      expect(input.attributes('maxlength')).toBe('30')
      expect(input.attributes('type')).toBe('tel')
      expect(wrapper.find('[data-testid="profile-panel-coordonnees"]').text()).toContain(
        "Visible uniquement par l'équipe WeAct"
      )
      expect(wrapper.find('[data-testid="account-email"]').text()).toBe('prod@example.com')
      expect(wrapper.find('[data-testid="email-change-stub"]').exists()).toBe(true)
      const type = wrapper.find('[data-testid="account-type"]')
      expect(type.text()).toBe('Agence')
      expect(type.find('svg').exists()).toBe(true)
    })

    it('labels an individual account as Particulier', async () => {
      setParticulier()
      h.query = { tab: 'coordonnees' }
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="account-type"]').text()).toBe('Particulier')
    })

    it('focuses the WhatsApp field when deep-linked from the banner (?focus=whatsapp), then drops the param', async () => {
      h.query = { focus: 'whatsapp', other: '1' }
      const wrapper = mountPage()
      document.body.appendChild(wrapper.element)
      await flushPromises()

      expect(wrapper.find('[data-testid="profile-tab-coordonnees"]').attributes('aria-selected')).toBe('true')
      expect(document.activeElement).toBe(wrapper.find('[data-testid="whatsapp-number-input"]').element)
      expect(h.routerReplace).toHaveBeenCalledWith({
        path: '/producer/profile',
        query: { other: '1' },
      })
      wrapper.unmount()
    })
  })

  describe('Sécurité', () => {
    it('hosts the password form (Google variant handled by PasswordChangeForm itself)', async () => {
      h.query = { tab: 'securite' }
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="password-change-stub"]').exists()).toBe(true)
    })
  })

  describe('dirty tracking and save bar', () => {
    it('is hidden while nothing changed', async () => {
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find(SAVE_BAR).exists()).toBe(false)
    })

    it('appears when the agency name changes and disappears when restored', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="agency-name-input"]').setValue('Autre nom')
      expect(wrapper.find(SAVE_BAR).exists()).toBe(true)

      await wrapper.find('[data-testid="agency-name-input"]').setValue('Studio Awalé')
      expect(wrapper.find(SAVE_BAR).exists()).toBe(false)
    })

    it('appears for a bio edit and for a WhatsApp edit (single bar for both tabs)', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="bio-textarea"]').setValue('Nouvelle bio')
      expect(wrapper.find(SAVE_BAR).exists()).toBe(true)
      await wrapper.find('[data-testid="r-save-cancel"]').trigger('click')
      expect(wrapper.find(SAVE_BAR).exists()).toBe(false)

      await wrapper.find('[data-testid="profile-tab-coordonnees"]').trigger('click')
      await wrapper.find('[data-testid="whatsapp-number-input"]').setValue('+229 01 11 11 11 11')
      expect(wrapper.find(SAVE_BAR).exists()).toBe(true)
    })

    it('ignores whitespace-only differences in bio and WhatsApp', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="bio-textarea"]').setValue('  Agence de production.  ')
      await wrapper.find('[data-testid="whatsapp-number-input"]').setValue(' +229 01 97 45 22 10 ')
      expect(wrapper.find(SAVE_BAR).exists()).toBe(false)
    })

    it('Annuler resets every field to the loaded values', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="agency-name-input"]').setValue('X')
      await wrapper.find('[data-testid="bio-textarea"]').setValue('Y')
      await wrapper.find('[data-testid="whatsapp-number-input"]').setValue('Z')
      await wrapper.find('[data-testid="r-save-cancel"]').trigger('click')

      expect((wrapper.find('[data-testid="agency-name-input"]').element as HTMLInputElement).value).toBe(
        'Studio Awalé'
      )
      expect((wrapper.find('[data-testid="bio-textarea"]').element as HTMLTextAreaElement).value).toBe(
        'Agence de production.'
      )
      expect((wrapper.find('[data-testid="whatsapp-number-input"]').element as HTMLInputElement).value).toBe(
        '+229 01 97 45 22 10'
      )
      expect(wrapper.find(SAVE_BAR).exists()).toBe(false)
      expect(h.updateBasicInfo).not.toHaveBeenCalled()
      expect(h.saveBio).not.toHaveBeenCalled()
    })

    it('Enregistrer sends the agency fields only to the basic-info endpoint when only they changed', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="agency-name-input"]').setValue('Nouvelle agence')
      await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
      await flushPromises()

      expect(h.updateBasicInfo).toHaveBeenCalledWith({
        agency_name: 'Nouvelle agence',
        whatsapp_number: '+229 01 97 45 22 10',
      })
      expect(h.saveBio).not.toHaveBeenCalled()
      expect(h.toastSuccess).toHaveBeenCalledWith('Informations mises à jour')
    })

    it('sends first/last name for an individual and null for a cleared WhatsApp number', async () => {
      setParticulier()
      h.refs.basicInfo.value = {
        type: 'particulier',
        first_name: 'Marie',
        last_name: 'Martin',
        whatsapp_number: '+229 01 00 00 00 00',
      }
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="last-name-input"]').setValue('')
      await wrapper.find('[data-testid="whatsapp-number-input"]').setValue('   ')
      await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
      await flushPromises()

      expect(h.updateBasicInfo).toHaveBeenCalledWith({
        first_name: 'Marie',
        last_name: '',
        whatsapp_number: null,
      })
    })

    it('does not require a last name (one-word names are valid)', async () => {
      setParticulier()
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="last-name-input"]').setValue('')
      await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
      await flushPromises()

      expect(h.updateBasicInfo).toHaveBeenCalledOnce()
    })

    it('refuses an empty agency name / first name without calling the API', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="agency-name-input"]').setValue('')
      await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
      await flushPromises()

      expect(h.updateBasicInfo).not.toHaveBeenCalled()
      expect(wrapper.find('[data-testid="name-error"]').text()).toContain("nom de l'agence")
    })

    it('saves the bio through the bio endpoint (trimmed, null when empty)', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="bio-textarea"]').setValue('  Nouvelle bio  ')
      await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
      await flushPromises()
      expect(h.saveBio).toHaveBeenCalledWith('Nouvelle bio')
      expect(h.updateBasicInfo).not.toHaveBeenCalled()

      await wrapper.find('[data-testid="bio-textarea"]').setValue('')
      await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
      await flushPromises()
      expect(h.saveBio).toHaveBeenLastCalledWith(null)
    })

    it('saves both endpoints at once and announces a single toast', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="agency-name-input"]').setValue('Nouvelle agence')
      await wrapper.find('[data-testid="bio-textarea"]').setValue('Nouvelle bio')
      await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
      await flushPromises()

      expect(h.updateBasicInfo).toHaveBeenCalledOnce()
      expect(h.saveBio).toHaveBeenCalledOnce()
      expect(h.toastSuccess).toHaveBeenCalledTimes(1)
      expect(h.toastSuccess).toHaveBeenCalledWith('Profil mis à jour avec succès')
    })

    it('the bar disappears once the saved values come back as the new baseline', async () => {
      const wrapper = mountPage()
      await flushPromises()

      h.updateBasicInfo.mockImplementation(async (data: Record<string, unknown>) => {
        h.refs.basicInfo.value = { type: 'agency', ...data }
        return { success: true, message: 'ok' }
      })
      await wrapper.find('[data-testid="agency-name-input"]').setValue('Nouvelle agence')
      await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
      await flushPromises()

      expect(wrapper.find(SAVE_BAR).exists()).toBe(false)
    })

    it('keeps the bar and shows the server error when the save fails', async () => {
      h.updateBasicInfo.mockImplementation(async () => {
        h.refs.basicError.value = 'Le nom est déjà pris.'
        return { success: false, message: 'Le nom est déjà pris.' }
      })
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-testid="agency-name-input"]').setValue('Pris')
      await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
      await flushPromises()

      expect(wrapper.find(SAVE_BAR).exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain('Le nom est déjà pris.')
      expect(h.toastSuccess).not.toHaveBeenCalled()
    })

    it('shows the bio server error under the bio field', async () => {
      h.refs.bioError.value = 'Erreur lors de la mise à jour de la bio'
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="bio-error"]').text()).toContain('Erreur lors de la mise à jour')
    })
  })

  describe('leave guard', () => {
    it('lets the user leave freely when nothing changed', async () => {
      mountPage()
      await flushPromises()

      expect(h.leaveGuard).toBeTypeOf('function')
      expect(h.leaveGuard!()).toBe(true)
    })

    it('asks for confirmation when leaving with unsaved changes: Rester blocks the navigation', async () => {
      const wrapper = mountPage()
      await flushPromises()
      await wrapper.find('[data-testid="agency-name-input"]').setValue('Modif')

      const result = h.leaveGuard!() as Promise<boolean>
      await flushPromises()
      expect(wrapper.find('[data-testid="leave-modal"]').exists()).toBe(true)

      await wrapper.find('[data-testid="leave-cancel"]').trigger('click')
      expect(await result).toBe(false)
      expect(wrapper.find('[data-testid="leave-modal"]').exists()).toBe(false)
    })

    it('lets the navigation through once the user confirms', async () => {
      const wrapper = mountPage()
      await flushPromises()
      await wrapper.find('[data-testid="bio-textarea"]').setValue('Modif')

      const result = h.leaveGuard!() as Promise<boolean>
      await flushPromises()
      await wrapper.find('[data-testid="leave-confirm"]').trigger('click')

      expect(await result).toBe(true)
    })

    it('warns on browser unload only while dirty', async () => {
      const wrapper = mountPage()
      await flushPromises()

      const clean = new Event('beforeunload', { cancelable: true })
      window.dispatchEvent(clean)
      expect(clean.defaultPrevented).toBe(false)

      await wrapper.find('[data-testid="agency-name-input"]').setValue('Modif')
      const dirty = new Event('beforeunload', { cancelable: true })
      window.dispatchEvent(dirty)
      expect(dirty.defaultPrevented).toBe(true)

      wrapper.unmount()
      const after = new Event('beforeunload', { cancelable: true })
      window.dispatchEvent(after)
      expect(after.defaultPrevented).toBe(false)
    })
  })
})
