import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import TarifsForm from '../TarifsForm.vue'
import type { TarifsInfo } from '../../types'

describe('TarifsForm', () => {
  const mockTarifsInfo: TarifsInfo = {
    tarif_horaire: 31250,
    tarif_journalier: 250000,
    formatted_tarif_horaire: '31 250 XOF/heure',
    formatted_tarif_journalier: '250 000 XOF/jour',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  function mountForm(props: Record<string, unknown> = {}) {
    return mount(TarifsForm, {
      props: {
        tarifsInfo: null,
        isSaving: false,
        error: null,
        ...props,
      },
    })
  }

  it('renders the journalier input from props and no separate horaire field', () => {
    const wrapper = mountForm({ tarifsInfo: mockTarifsInfo })

    expect((wrapper.find('input#tarif_journalier').element as HTMLInputElement).value).toBe('250000')
    expect(wrapper.find('input#tarif_horaire').exists()).toBe(false)
  })

  it('renders an empty form when no tarifs are provided', () => {
    const wrapper = mountForm()

    expect((wrapper.find('input#tarif_journalier').element as HTMLInputElement).value).toBe('')
  })

  it('shows the current error and saving states', () => {
    const wrapper = mountForm({
      isSaving: true,
      error: 'Le tarif journalier doit être positif',
    })

    expect(wrapper.find('[data-testid="error-message"]').text()).toContain('Le tarif journalier doit être positif')
    expect(wrapper.find('[data-testid="save-button"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-testid="save-button"]').text()).toContain('Enregistrement...')
  })

  it('emits derived hourly and daily pricing on submit', async () => {
    const wrapper = mountForm()

    await wrapper.find('input#tarif_journalier').setValue(240000)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.emitted('save')?.[0][0]).toEqual({
      tarif_horaire: 30000,
      tarif_journalier: 240000,
    })
  })

  it('emits null values when the field is empty', async () => {
    const wrapper = mountForm({ tarifsInfo: mockTarifsInfo })

    await wrapper.find('input#tarif_journalier').setValue('')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.emitted('save')?.[0][0]).toEqual({
      tarif_horaire: null,
      tarif_journalier: null,
    })
  })

  it('updates the form when tarifsInfo changes', async () => {
    const wrapper = mountForm()

    expect((wrapper.find('input#tarif_journalier').element as HTMLInputElement).value).toBe('')

    await wrapper.setProps({ tarifsInfo: mockTarifsInfo })
    await flushPromises()

    expect((wrapper.find('input#tarif_journalier').element as HTMLInputElement).value).toBe('250000')
  })

  it('shows the pricing preview for daily and half-day amounts', async () => {
    const wrapper = mountForm()

    await wrapper.find('input#tarif_journalier').setValue(250000)
    await flushPromises()

    const preview = wrapper.find('[data-testid="tarif-preview"]')

    expect(preview.exists()).toBe(true)
    expect(preview.text()).toContain('250')
    expect(preview.text()).toContain('125')
    expect(preview.text()).toContain('F CFA')
  })
})
