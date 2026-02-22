import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import TarifsForm from '../TarifsForm.vue'
import type { TarifsInfo } from '../../types'

describe('TarifsForm', () => {
  const mockTarifsInfo: TarifsInfo = {
    tarif_horaire: 75000,
    tarif_journalier: 250000,
    formatted_tarif_horaire: '75 000 XOF/heure',
    formatted_tarif_journalier: '250 000 XOF/jour',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders form with initial values from props', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: mockTarifsInfo,
        isSaving: false,
        error: null,
      },
    })

    const tarifHoraireInput = wrapper.find('[data-testid="tarif-horaire-input"]')
    const tarifJournalierInput = wrapper.find('[data-testid="tarif-journalier-input"]')

    expect((tarifHoraireInput.element as HTMLInputElement).value).toBe('75000')
    expect((tarifJournalierInput.element as HTMLInputElement).value).toBe('250000')
  })

  it('renders empty form when tarifsInfo is null', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tarifHoraireInput = wrapper.find('[data-testid="tarif-horaire-input"]')
    const tarifJournalierInput = wrapper.find('[data-testid="tarif-journalier-input"]')

    expect((tarifHoraireInput.element as HTMLInputElement).value).toBe('')
    expect((tarifJournalierInput.element as HTMLInputElement).value).toBe('')
  })

  it('displays error message when error prop is set', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: 'Le tarif horaire doit être positif',
      },
    })

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.exists()).toBe(true)
    expect(errorMessage.text()).toContain('Le tarif horaire doit être positif')
  })

  it('does not display error when error prop is null', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.exists()).toBe(false)
  })

  it('disables save button when saving', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: true,
        error: null,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.attributes('disabled')).toBeDefined()
  })

  it('shows loading spinner when saving', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: true,
        error: null,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.text()).toContain('Enregistrement...')
    expect(saveButton.find('svg.animate-spin').exists()).toBe(true)
  })

  it('shows "Enregistrer" text when not saving', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.text()).toBe('Enregistrer')
  })

  it('has min and max attributes on tarif horaire input', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tarifHoraireInput = wrapper.find('[data-testid="tarif-horaire-input"]')
    expect(tarifHoraireInput.attributes('min')).toBe('0')
    expect(tarifHoraireInput.attributes('max')).toBe('10000000')
    expect(tarifHoraireInput.attributes('type')).toBe('number')
  })

  it('has min and max attributes on tarif journalier input', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tarifJournalierInput = wrapper.find('[data-testid="tarif-journalier-input"]')
    expect(tarifJournalierInput.attributes('min')).toBe('0')
    expect(tarifJournalierInput.attributes('max')).toBe('100000000')
    expect(tarifJournalierInput.attributes('type')).toBe('number')
  })

  it('emits save event with form data on submit', async () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tarifHoraireInput = wrapper.find('[data-testid="tarif-horaire-input"]')
    const tarifJournalierInput = wrapper.find('[data-testid="tarif-journalier-input"]')

    await tarifHoraireInput.setValue(75000)
    await tarifJournalierInput.setValue(250000)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted).toBeTruthy()
    expect(emitted?.[0][0]).toEqual({
      tarif_horaire: 75000,
      tarif_journalier: 250000,
    })
  })

  it('emits save event with null for empty values', async () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: mockTarifsInfo,
        isSaving: false,
        error: null,
      },
    })

    // Clear the inputs
    const tarifHoraireInput = wrapper.find('[data-testid="tarif-horaire-input"]')
    const tarifJournalierInput = wrapper.find('[data-testid="tarif-journalier-input"]')
    await tarifHoraireInput.setValue('')
    await tarifJournalierInput.setValue('')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted?.[0][0]).toEqual({
      tarif_horaire: null,
      tarif_journalier: null,
    })
  })

  it('updates form when tarifsInfo prop changes', async () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    // Initially empty
    expect(
      (wrapper.find('[data-testid="tarif-horaire-input"]').element as HTMLInputElement).value,
    ).toBe('')

    // Update props
    await wrapper.setProps({ tarifsInfo: mockTarifsInfo })
    await flushPromises()

    expect(
      (wrapper.find('[data-testid="tarif-horaire-input"]').element as HTMLInputElement).value,
    ).toBe('75000')
    expect(
      (wrapper.find('[data-testid="tarif-journalier-input"]').element as HTMLInputElement).value,
    ).toBe('250000')
  })

  it('has proper accessibility labels', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    expect(wrapper.find('label[for="tarif_horaire"]').exists()).toBe(true)
    expect(wrapper.find('label[for="tarif_journalier"]').exists()).toBe(true)

    expect(wrapper.find('#tarif_horaire').exists()).toBe(true)
    expect(wrapper.find('#tarif_journalier').exists()).toBe(true)
  })

  it('error message has role="alert" for accessibility', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: 'Une erreur',
      },
    })

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.attributes('role')).toBe('alert')
  })

  it('emits save with only tarif_horaire when tarif_journalier is empty', async () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tarifHoraireInput = wrapper.find('[data-testid="tarif-horaire-input"]')
    await tarifHoraireInput.setValue(75000)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted?.[0][0]).toEqual({
      tarif_horaire: 75000,
      tarif_journalier: null,
    })
  })

  it('emits save with only tarif_journalier when tarif_horaire is empty', async () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tarifJournalierInput = wrapper.find('[data-testid="tarif-journalier-input"]')
    await tarifJournalierInput.setValue(250000)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const emitted = wrapper.emitted('save')
    expect(emitted?.[0][0]).toEqual({
      tarif_horaire: null,
      tarif_journalier: 250000,
    })
  })

  it('displays formatted currency preview for tarif horaire', async () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tarifHoraireInput = wrapper.find('[data-testid="tarif-horaire-input"]')
    await tarifHoraireInput.setValue(75000)
    await flushPromises()

    const preview = wrapper.find('[data-testid="tarif-horaire-preview"]')
    expect(preview.exists()).toBe(true)
    expect(preview.text()).toContain('XOF/demi-journée')
  })

  it('displays formatted currency preview for tarif journalier', async () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tarifJournalierInput = wrapper.find('[data-testid="tarif-journalier-input"]')
    await tarifJournalierInput.setValue(250000)
    await flushPromises()

    const preview = wrapper.find('[data-testid="tarif-journalier-preview"]')
    expect(preview.exists()).toBe(true)
    expect(preview.text()).toContain('XOF/jour')
  })

  it('does not display preview when input is empty', () => {
    const wrapper = mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
      },
    })

    const tarifHorairePreview = wrapper.find('[data-testid="tarif-horaire-preview"]')
    const tarifJournalierPreview = wrapper.find('[data-testid="tarif-journalier-preview"]')

    expect(tarifHorairePreview.exists()).toBe(false)
    expect(tarifJournalierPreview.exists()).toBe(false)
  })
})
