import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import BioLocationForm from '../BioLocationForm.vue'
import type { BioLocationInfo } from '../../types'

describe('BioLocationForm', () => {
  const mockBioLocationInfo: BioLocationInfo = {
    bio: 'Je suis acteur professionnel',
    ville: 'Cotonou',
    quartier: 'Akpakpa',
    pays: 'Bénin',
    formatted_location: 'Cotonou, Akpakpa, Bénin',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders form with initial values from props', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: mockBioLocationInfo,
        isSaving: false,
        error: null,
      },
    })

    const bioInput = wrapper.find('[data-testid="bio-input"]')
    const villeInput = wrapper.find('[data-testid="ville-input"]')
    const quartierInput = wrapper.find('[data-testid="quartier-input"]')
    const paysInput = wrapper.find('[data-testid="pays-input"]')

    expect((bioInput.element as HTMLTextAreaElement).value).toBe(mockBioLocationInfo.bio)
    expect((villeInput.element as HTMLInputElement).value).toBe(mockBioLocationInfo.ville)
    expect((quartierInput.element as HTMLInputElement).value).toBe(mockBioLocationInfo.quartier)
    expect((paysInput.element as HTMLInputElement).value).toBe(mockBioLocationInfo.pays)
  })

  it('renders empty form when bioLocationInfo is null', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const bioInput = wrapper.find('[data-testid="bio-input"]')
    const villeInput = wrapper.find('[data-testid="ville-input"]')
    const quartierInput = wrapper.find('[data-testid="quartier-input"]')
    const paysInput = wrapper.find('[data-testid="pays-input"]')

    expect((bioInput.element as HTMLTextAreaElement).value).toBe('')
    expect((villeInput.element as HTMLInputElement).value).toBe('')
    expect((quartierInput.element as HTMLInputElement).value).toBe('')
    expect((paysInput.element as HTMLInputElement).value).toBe('Bénin') // default value
  })

  it('displays character counter correctly', async () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const characterCounter = wrapper.find('[data-testid="character-counter"]')
    expect(characterCounter.text()).toBe('0 / 500 caractères')

    // Type some text
    const bioInput = wrapper.find('[data-testid="bio-input"]')
    await bioInput.setValue('Test bio')

    expect(characterCounter.text()).toBe('8 / 500 caractères')
  })

  it('shows red color when exactly at character limit', async () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const bioInput = wrapper.find('[data-testid="bio-input"]')
    // Set to exactly 500 characters (at limit)
    await bioInput.setValue('a'.repeat(500))

    const characterCounter = wrapper.find('[data-testid="character-counter"]')
    expect(characterCounter.classes()).toContain('text-red-500')
  })

  it('shows normal color when below character limit', async () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const bioInput = wrapper.find('[data-testid="bio-input"]')
    // Set to 499 characters (below limit)
    await bioInput.setValue('a'.repeat(499))

    const characterCounter = wrapper.find('[data-testid="character-counter"]')
    expect(characterCounter.classes()).toContain('text-gray-500')
  })

  it('displays error message when error prop is set', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: 'La bio ne peut pas dépasser 500 caractères',
      },
    })

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.exists()).toBe(true)
    expect(errorMessage.text()).toContain('La bio ne peut pas dépasser 500 caractères')
  })

  it('does not display error when error prop is null', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.exists()).toBe(false)
  })

  it('disables save button when saving', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: true,
        error: null,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.attributes('disabled')).toBeDefined()
  })

  it('shows loading spinner when saving', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: true,
        error: null,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.text()).toContain('Enregistrement...')
    expect(saveButton.find('svg.animate-spin').exists()).toBe(true)
  })

  it('shows "Enregistrer" text when not saving', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.text()).toBe('Enregistrer')
  })

  it('has maxlength attribute on bio textarea', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const bioInput = wrapper.find('[data-testid="bio-input"]')
    expect(bioInput.attributes('maxlength')).toBe('500')
  })

  it('emits save event with form data on submit', async () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const bioInput = wrapper.find('[data-testid="bio-input"]')
    const villeInput = wrapper.find('[data-testid="ville-input"]')
    const quartierInput = wrapper.find('[data-testid="quartier-input"]')
    const paysInput = wrapper.find('[data-testid="pays-input"]')

    await bioInput.setValue('Ma nouvelle bio')
    await villeInput.setValue('Porto-Novo')
    await quartierInput.setValue('Centre')
    await paysInput.setValue('Bénin')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted).toBeTruthy()
    expect(emitted?.[0][0]).toEqual({
      bio: 'Ma nouvelle bio',
      ville: 'Porto-Novo',
      quartier: 'Centre',
      pays: 'Bénin',
    })
  })

  it('emits save event with null for empty values', async () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: mockBioLocationInfo,
        isSaving: false,
        error: null,
      },
    })

    // Clear the bio
    const bioInput = wrapper.find('[data-testid="bio-input"]')
    await bioInput.setValue('')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted?.[0][0]).toMatchObject({
      bio: null,
    })
  })

  it('updates form when bioLocationInfo prop changes', async () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: null,
      },
    })

    // Initially empty (with default pays)
    expect((wrapper.find('[data-testid="bio-input"]').element as HTMLTextAreaElement).value).toBe('')

    // Update props
    await wrapper.setProps({ bioLocationInfo: mockBioLocationInfo })
    await flushPromises()

    expect((wrapper.find('[data-testid="bio-input"]').element as HTMLTextAreaElement).value).toBe(
      mockBioLocationInfo.bio,
    )
    expect((wrapper.find('[data-testid="ville-input"]').element as HTMLInputElement).value).toBe(
      mockBioLocationInfo.ville,
    )
  })

  it('has proper accessibility labels', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: null,
      },
    })

    expect(wrapper.find('label[for="bio"]').exists()).toBe(true)
    expect(wrapper.find('label[for="ville"]').exists()).toBe(true)
    expect(wrapper.find('label[for="quartier"]').exists()).toBe(true)
    expect(wrapper.find('label[for="pays"]').exists()).toBe(true)

    expect(wrapper.find('#bio').exists()).toBe(true)
    expect(wrapper.find('#ville').exists()).toBe(true)
    expect(wrapper.find('#quartier').exists()).toBe(true)
    expect(wrapper.find('#pays').exists()).toBe(true)
  })

  it('error message has role="alert" for accessibility', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: null,
        isSaving: false,
        error: 'Une erreur',
      },
    })

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.attributes('role')).toBe('alert')
  })
})
