import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import BioLocationForm from '../BioLocationForm.vue'
import type { BioLocationInfo } from '../../types'
import { getSelectLabels, getSelectValue, setSelectValue } from '@/components/ui/__tests__/selectTestUtils'

const VILLE = '[data-testid="ville-input"]'
const PAYS = '[data-testid="pays-input"]'

describe('BioLocationForm', () => {
  const mockBioLocationInfo: BioLocationInfo = {
    bio: 'Je suis acteur professionnel',
    ville: 'Cotonou',
    pays: 'Bénin',
    formatted_location: 'Cotonou, Bénin',
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

    expect((bioInput.element as HTMLTextAreaElement).value).toBe(mockBioLocationInfo.bio)
    expect(getSelectValue(wrapper, VILLE)).toBe(mockBioLocationInfo.ville)
    expect(getSelectValue(wrapper, PAYS)).toBe(mockBioLocationInfo.pays)
    expect(wrapper.find('[data-testid="quartier-input"]').exists()).toBe(false)
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

    expect((bioInput.element as HTMLTextAreaElement).value).toBe('')
    expect(getSelectValue(wrapper, VILLE)).toBe('')
    expect(getSelectValue(wrapper, PAYS)).toBe('Bénin') // default value
    expect(wrapper.find('[data-testid="quartier-input"]').exists()).toBe(false)
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

    await bioInput.setValue('Ma nouvelle bio')
    await setSelectValue(wrapper, VILLE, 'Porto-Novo')
    await setSelectValue(wrapper, PAYS, 'Bénin')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted).toBeTruthy()
    expect(emitted?.[0][0]).toEqual({
      bio: 'Ma nouvelle bio',
      ville: 'Porto-Novo',
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
    expect(getSelectValue(wrapper, VILLE)).toBe(mockBioLocationInfo.ville)
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
    expect(wrapper.find('label[for="pays"]').exists()).toBe(true)

    expect(wrapper.find('#bio').exists()).toBe(true)
    expect(wrapper.find('#ville').exists()).toBe(true)
    expect(wrapper.find('#pays').exists()).toBe(true)
  })

  it('renders pays as a select dropdown, not a text input', () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: mockBioLocationInfo,
        isSaving: false,
        error: null,
      },
    })

    const paysField = wrapper.find(PAYS)

    expect(paysField.attributes('data-slot')).toBe('select-trigger')
    expect(getSelectValue(wrapper, PAYS)).toBe('Bénin')
  })

  it('preserves a legacy free-text pays value in the dropdown', async () => {
    const legacyPays = 'Benin Republic'
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: {
          bio: 'Bio',
          ville: null,
          pays: legacyPays,
          formatted_location: legacyPays,
        },
        isSaving: false,
        error: null,
      },
    })

    await flushPromises()

    expect(getSelectValue(wrapper, PAYS)).toBe(legacyPays)
    expect(getSelectLabels(wrapper, PAYS)).toContain(legacyPays)
  })

  it('disables city select and clears it when pays is not Bénin', async () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: mockBioLocationInfo,
        isSaving: false,
        error: null,
      },
    })

    await setSelectValue(wrapper, PAYS, 'Togo')

    expect(wrapper.find(VILLE).attributes('disabled')).toBeDefined()
    expect(getSelectValue(wrapper, VILLE)).toBe('')
  })

  it('loads a non-Benin profile with city select disabled and cleared', async () => {
    const wrapper = mount(BioLocationForm, {
      props: {
        bioLocationInfo: {
          bio: 'Bio',
          ville: 'Cotonou',
          pays: 'Togo',
          formatted_location: 'Togo',
        },
        isSaving: false,
        error: null,
      },
    })

    await flushPromises()

    expect(wrapper.find(VILLE).attributes('disabled')).toBeDefined()
    expect(getSelectValue(wrapper, VILLE)).toBe('')
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
