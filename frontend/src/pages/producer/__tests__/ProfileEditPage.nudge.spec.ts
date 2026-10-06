import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { Ref } from 'vue'
import ProfileEditPage from '../ProfileEditPage.vue'

// Producers deliberately have no completion percentage (see the page's script
// comment) — the nudge is the whole mechanism, so it is worth mounting for real.
//
// The composables must hand back genuine refs: the page destructures them and
// uses them bare in the template, which only unwraps actual refs.
const h = vi.hoisted(() => ({
  userId: 7 as number | null,
  query: {} as Record<string, string>,
  routerReplace: vi.fn(),
  fetchProfile: vi.fn().mockResolvedValue(undefined),
  fetchBio: vi.fn().mockResolvedValue(undefined),
  refs: {} as {
    profile: Ref<Record<string, unknown> | null>
    isLoading: Ref<boolean>
    bio: Ref<string | null>
    isBioLoading: Ref<boolean>
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
      uploadPhoto: vi.fn(),
      deletePhoto: vi.fn(),
    }),
  }
})

vi.mock('@/features/producer/composables/useProducerBio', async () => {
  const { ref } = await import('vue')
  h.refs.bio = ref(null)
  h.refs.isBioLoading = ref(false)

  return {
    useProducerBio: () => ({
      bio: h.refs.bio,
      isLoading: h.refs.isBioLoading,
      isSaving: ref(false),
      error: ref(null),
      fetchBio: h.fetchBio,
      saveBio: vi.fn(),
    }),
  }
})

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}))

// The page reads ?focus= from the route (WhatsApp banner deep-link).
vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/producer/profile', query: h.query }),
  useRouter: () => ({ replace: h.routerReplace }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: h.userId === null ? null : { id: h.userId, email: 'prod@example.com' },
  }),
}))

const stubs = {
  ProducerProfilePhotoUpload: true,
  ProducerBioEditor: true,
  AgencyLogoUpload: true,
  BasicInfoSection: true,
  EmailChangeForm: true,
  PasswordChangeForm: true,
  DataPrivacySection: true,
}

const mountPage = () => mount(ProfileEditPage, { global: { stubs } })

describe('ProducerProfileEditPage — completion nudge', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()
    h.userId = 7
    h.query = {}
    h.routerReplace.mockReset()
    h.refs.profile.value = {
      type: 'particulier',
      profile_photo_url: null,
      agency_logo_url: null,
    }
    h.refs.bio.value = null
    h.refs.isLoading.value = false
    h.refs.isBioLoading.value = false
  })

  it('shows the nudge when both the photo and the bio are missing', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const nudge = wrapper.find('[data-testid="producer-profile-nudge"]')
    expect(nudge.exists()).toBe(true)
    expect(nudge.text()).toContain('photo de profil')
    expect(nudge.text()).toContain('présentez votre activité')
  })

  it('asks an agency for its logo, not a profile photo', async () => {
    h.refs.profile.value = { type: 'agency', profile_photo_url: null, agency_logo_url: null }
    const wrapper = mountPage()
    await flushPromises()

    const nudge = wrapper.find('[data-testid="producer-profile-nudge"]')
    expect(nudge.text()).toContain('logo de votre agence')
    expect(nudge.text()).not.toContain('photo de profil')
  })

  it('still shows the nudge when only the bio is missing', async () => {
    h.refs.profile.value = {
      type: 'particulier',
      profile_photo_url: 'https://cdn/photo.jpg',
      agency_logo_url: null,
    }
    const wrapper = mountPage()
    await flushPromises()

    const nudge = wrapper.find('[data-testid="producer-profile-nudge"]')
    expect(nudge.exists()).toBe(true)
    expect(nudge.text()).not.toContain('photo de profil')
    expect(nudge.text()).toContain('présentez votre activité')
  })

  it('hides the nudge once both items are filled', async () => {
    h.refs.profile.value = {
      type: 'particulier',
      profile_photo_url: 'https://cdn/photo.jpg',
      agency_logo_url: null,
    }
    h.refs.bio.value = 'Studio de production basé à Cotonou.'
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="producer-profile-nudge"]').exists()).toBe(false)
  })

  it('stays hidden while the bio is still loading', async () => {
    h.refs.isBioLoading.value = true
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="producer-profile-nudge"]').exists()).toBe(false)
  })

  it('dismissal persists across mounts', async () => {
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-testid="producer-profile-nudge-dismiss"]').trigger('click')
    expect(wrapper.find('[data-testid="producer-profile-nudge"]').exists()).toBe(false)

    const remounted = mountPage()
    await flushPromises()
    expect(remounted.find('[data-testid="producer-profile-nudge"]').exists()).toBe(false)
  })

  it('keys the dismissal per user: another account on the same browser still sees it', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await wrapper.find('[data-testid="producer-profile-nudge-dismiss"]').trigger('click')

    expect(localStorage.getItem('producer_profile_nudge_dismissed:7')).toBe('1')
    expect(localStorage.getItem('producer_profile_nudge_dismissed')).toBeNull()

    h.userId = 8
    const other = mountPage()
    await flushPromises()
    expect(other.find('[data-testid="producer-profile-nudge"]').exists()).toBe(true)

    h.userId = 7
    const same = mountPage()
    await flushPromises()
    expect(same.find('[data-testid="producer-profile-nudge"]').exists()).toBe(false)
  })

  it('survives a storage that throws on read and write', async () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('denied')
    })

    const wrapper = mountPage()
    await flushPromises()
    expect(wrapper.find('[data-testid="producer-profile-nudge"]').exists()).toBe(true)

    await wrapper.find('[data-testid="producer-profile-nudge-dismiss"]').trigger('click')
    // Dismissed for this page view even though nothing could be persisted.
    expect(wrapper.find('[data-testid="producer-profile-nudge"]').exists()).toBe(false)
  })

  it('assembles the sentence from what is missing', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="producer-profile-nudge"]').text()).toContain(
      'Complétez votre profil — ajoutez une photo de profil et présentez votre activité en quelques lignes — les Faces répondent bien plus souvent aux Producteurs identifiables.'
    )
  })

  describe('?focus=whatsapp wiring', () => {
    it('does not ask for the focus without the query', async () => {
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.findComponent({ name: 'BasicInfoSection' }).props('focusWhatsapp')).toBe(false)
    })

    it('passes focus=whatsapp from the route query to the basic-info section', async () => {
      h.query = { focus: 'whatsapp' }
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.findComponent({ name: 'BasicInfoSection' }).props('focusWhatsapp')).toBe(true)
    })

    it('removes only `focus` from the URL once the field is focused', async () => {
      h.query = { focus: 'whatsapp', other: '1' }
      const wrapper = mountPage()
      await flushPromises()

      wrapper.findComponent({ name: 'BasicInfoSection' }).vm.$emit('focused')

      expect(h.routerReplace).toHaveBeenCalledWith({ path: '/producer/profile', query: { other: '1' } })
    })
  })
})
