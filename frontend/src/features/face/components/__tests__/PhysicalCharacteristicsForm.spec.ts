import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PhysicalCharacteristicsForm from '../PhysicalCharacteristicsForm.vue'
import type { PhysicalCharacteristicsInfo } from '../../types'

describe('PhysicalCharacteristicsForm', () => {
  const mockPhysicalCharacteristicsInfo: PhysicalCharacteristicsInfo = {
    taille: 175,
    poids: 70,
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders form with initial values from props', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: mockPhysicalCharacteristicsInfo,
        isSaving: false,
        error: null,
      },
    })

    const tailleInput = wrapper.find('[data-testid="taille-input"]')
    const poidsInput = wrapper.find('[data-testid="poids-input"]')

    expect((tailleInput.element as HTMLInputElement).value).toBe('175')
    expect((poidsInput.element as HTMLInputElement).value).toBe('70')
  })

  it('renders empty form when physicalCharacteristicsInfo is null', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tailleInput = wrapper.find('[data-testid="taille-input"]')
    const poidsInput = wrapper.find('[data-testid="poids-input"]')

    expect((tailleInput.element as HTMLInputElement).value).toBe('')
    expect((poidsInput.element as HTMLInputElement).value).toBe('')
  })

  it('displays error message when error prop is set', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: 'La taille doit être entre 50 et 300 cm',
      },
    })

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.exists()).toBe(true)
    expect(errorMessage.text()).toContain('La taille doit être entre 50 et 300 cm')
  })

  it('does not display error when error prop is null', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.exists()).toBe(false)
  })

  it('disables save button when saving', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: true,
        error: null,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.attributes('disabled')).toBeDefined()
  })

  it('shows loading spinner when saving', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: true,
        error: null,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.text()).toContain('Enregistrement...')
    expect(saveButton.find('svg.animate-spin').exists()).toBe(true)
  })

  it('shows "Enregistrer" text when not saving', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.text()).toBe('Enregistrer')
  })

  it('has min and max attributes on taille input', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tailleInput = wrapper.find('[data-testid="taille-input"]')
    expect(tailleInput.attributes('min')).toBe('50')
    expect(tailleInput.attributes('max')).toBe('300')
    expect(tailleInput.attributes('type')).toBe('number')
  })

  it('has min and max attributes on poids input', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const poidsInput = wrapper.find('[data-testid="poids-input"]')
    expect(poidsInput.attributes('min')).toBe('20')
    expect(poidsInput.attributes('max')).toBe('500')
    expect(poidsInput.attributes('type')).toBe('number')
  })

  it('emits save event with form data on submit', async () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tailleInput = wrapper.find('[data-testid="taille-input"]')
    const poidsInput = wrapper.find('[data-testid="poids-input"]')

    await tailleInput.setValue(180)
    await poidsInput.setValue(75)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted).toBeTruthy()
    expect(emitted?.[0][0]).toEqual({
      taille: 180,
      poids: 75,
    })
  })

  it('emits save event with null for empty values', async () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: mockPhysicalCharacteristicsInfo,
        isSaving: false,
        error: null,
      },
    })

    // Clear the inputs
    const tailleInput = wrapper.find('[data-testid="taille-input"]')
    const poidsInput = wrapper.find('[data-testid="poids-input"]')
    await tailleInput.setValue('')
    await poidsInput.setValue('')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted?.[0][0]).toEqual({
      taille: null,
      poids: null,
    })
  })

  it('updates form when physicalCharacteristicsInfo prop changes', async () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    // Initially empty
    expect((wrapper.find('[data-testid="taille-input"]').element as HTMLInputElement).value).toBe('')

    // Update props
    await wrapper.setProps({ physicalCharacteristicsInfo: mockPhysicalCharacteristicsInfo })
    await flushPromises()

    expect((wrapper.find('[data-testid="taille-input"]').element as HTMLInputElement).value).toBe(
      '175',
    )
    expect((wrapper.find('[data-testid="poids-input"]').element as HTMLInputElement).value).toBe(
      '70',
    )
  })

  it('has proper accessibility labels', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    expect(wrapper.find('label[for="taille"]').exists()).toBe(true)
    expect(wrapper.find('label[for="poids"]').exists()).toBe(true)

    expect(wrapper.find('#taille').exists()).toBe(true)
    expect(wrapper.find('#poids').exists()).toBe(true)
  })

  it('error message has role="alert" for accessibility', () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: 'Une erreur',
      },
    })

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.attributes('role')).toBe('alert')
  })

  it('emits save with only taille when poids is empty', async () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tailleInput = wrapper.find('[data-testid="taille-input"]')
    await tailleInput.setValue(180)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted?.[0][0]).toEqual({
      taille: 180,
      poids: null,
    })
  })

  it('emits save with only poids when taille is empty', async () => {
    const wrapper = mount(PhysicalCharacteristicsForm, {
      props: {
        physicalCharacteristicsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const poidsInput = wrapper.find('[data-testid="poids-input"]')
    await poidsInput.setValue(75)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted?.[0][0]).toEqual({
      taille: null,
      poids: 75,
    })
  })
})
