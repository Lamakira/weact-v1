import { afterEach, describe, it, expect, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { cleanupPortals, clickOption, getOptionLabels, openSelect } from '@/components/ui/__tests__/selectTestUtils'
import PersonalInfoForm from '../PersonalInfoForm.vue'

// Le bloc d'alertes WhatsApp a ses propres tests (WhatsappAlertsToggle.spec.ts) et dépend de Pinia.
vi.mock('@/features/notification/components/WhatsappAlertsToggle.vue', () => ({
  default: {
    name: 'WhatsappAlertsToggle',
    props: ['typedNumber', 'savedNumber'],
    template: '<div data-testid="whatsapp-alerts-toggle-stub" />',
  },
}))

describe('PersonalInfoForm — Show Age Toggle', () => {
  function mountForm(personalInfo = {
    sexe: null,
    date_naissance: null,
    nationalite: null,
    pays: null,
    show_age: true,
    whatsapp_number: null as string | null,
  }) {
    return mount(PersonalInfoForm, {
      props: {
        personalInfo,
        isSaving: false,
        error: null,
      },
    })
  }

  it('renders the show age toggle', () => {
    const wrapper = mountForm()
    const toggle = wrapper.find('[data-testid="show-age-toggle"]')
    expect(toggle.exists()).toBe(true)
    expect(toggle.attributes('role')).toBe('switch')
  })

  it('toggle defaults to checked when show_age is true', () => {
    const wrapper = mountForm()
    const toggle = wrapper.find('[data-testid="show-age-toggle"]')
    expect(toggle.attributes('aria-checked')).toBe('true')
  })

  it('toggle reflects unchecked state when show_age is false', () => {
    const wrapper = mountForm({
      sexe: null,
      date_naissance: null,
      nationalite: null,
      pays: null,
      show_age: false,
      whatsapp_number: null,
    })
    const toggle = wrapper.find('[data-testid="show-age-toggle"]')
    expect(toggle.attributes('aria-checked')).toBe('false')
  })

  it('toggles show_age when clicked', async () => {
    const wrapper = mountForm()
    const toggle = wrapper.find('[data-testid="show-age-toggle"]')

    expect(toggle.attributes('aria-checked')).toBe('true')
    await toggle.trigger('click')
    expect(toggle.attributes('aria-checked')).toBe('false')
  })
})

describe('PersonalInfoForm — WhatsApp Field', () => {
  function mountForm(personalInfo = {
    sexe: null,
    date_naissance: null,
    nationalite: null,
    pays: null,
    show_age: true,
    whatsapp_number: null as string | null,
  }) {
    return mount(PersonalInfoForm, {
      props: {
        personalInfo,
        isSaving: false,
        error: null,
      },
    })
  }

  it('renders the WhatsApp field', () => {
    const wrapper = mountForm()
    const field = wrapper.find('[data-testid="whatsapp-number-input"]')
    expect(field.exists()).toBe(true)
  })

  it('pre-fills WhatsApp field from personalInfo prop', () => {
    const wrapper = mountForm({
      sexe: null,
      date_naissance: null,
      nationalite: null,
      pays: null,
      show_age: true,
      whatsapp_number: '+22990001122',
    })
    const input = wrapper.find('[data-testid="whatsapp-number-input"]')
    expect((input.element as HTMLInputElement).value).toBe('+22990001122')
  })

  it('includes whatsapp_number in save emit', async () => {
    const wrapper = mountForm({
      sexe: null,
      date_naissance: null,
      nationalite: null,
      pays: null,
      show_age: true,
      whatsapp_number: '+22997001234',
    })

    await wrapper.find('form').trigger('submit')

    const emitted = wrapper.emitted('save')
    expect(emitted).toBeTruthy()
    expect(emitted![0][0]).toHaveProperty('whatsapp_number', '+22997001234')
  })
})

describe('PersonalInfoForm — nationalité et pays (listes avec recherche)', () => {
  const baseInfo = {
    sexe: null,
    date_naissance: null,
    nationalite: null as string | null,
    pays: null as string | null,
    show_age: true,
    whatsapp_number: null,
  }

  function mountForm(info = baseInfo) {
    return mount(PersonalInfoForm, {
      attachTo: document.body,
      props: { personalInfo: info, isSaving: false, error: null },
    })
  }

  async function typeSearch(placeholder: string, value: string): Promise<void> {
    const input = document.body.querySelector<HTMLInputElement>(`input[placeholder="${placeholder}"]`)!
    input.value = value
    input.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
  }

  afterEach(() => {
    cleanupPortals()
  })

  it('affiche le libellé « Nationalité » accentué', () => {
    const wrapper = mountForm()
    expect(wrapper.find('label[for="personal-nationalite"]').text()).toBe('Nationalité')
    wrapper.unmount()
  })

  it('propose le pays « Bénin » en tête et le choisit depuis la recherche', async () => {
    const wrapper = mountForm()
    await openSelect(wrapper.get('[data-testid="pays-input"]'))

    expect(getOptionLabels()[1]).toBe('Bénin')

    await typeSearch('Rechercher un pays…', 'benin')
    expect(getOptionLabels()).toEqual(['Bénin'])

    await clickOption('Bénin')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('save')![0][0]).toHaveProperty('pays', 'Bénin')
    wrapper.unmount()
  })

  it('trouve « Béninoise » en tapant « beninoise » et émet la valeur', async () => {
    const wrapper = mountForm()
    await openSelect(wrapper.get('[data-testid="nationalite-input"]'))

    await typeSearch('Rechercher une nationalité…', 'beninoise')
    expect(getOptionLabels()).toEqual(['Béninoise'])

    await clickOption('Béninoise')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('save')![0][0]).toHaveProperty('nationalite', 'Béninoise')
    wrapper.unmount()
  })

  it('choisit « Bénin » au clavier (flèche bas puis Entrée dans la recherche)', async () => {
    const wrapper = mountForm()
    await openSelect(wrapper.get('[data-testid="pays-input"]'))
    await typeSearch('Rechercher un pays…', 'benin')

    const input = document.body.querySelector<HTMLInputElement>('input[placeholder="Rechercher un pays…"]')!
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true, cancelable: true }))
    await flushPromises()
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }))
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('save')![0][0]).toHaveProperty('pays', 'Bénin')
    wrapper.unmount()
  })

  it('garde affichée une ancienne valeur hors liste', async () => {
    const wrapper = mountForm({ ...baseInfo, nationalite: 'Béninois', pays: 'Béni' })

    expect(wrapper.get('[data-testid="nationalite-input"]').text()).toBe('Béninois')
    expect(wrapper.get('[data-testid="pays-input"]').text()).toBe('Béni')

    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('save')![0][0]).toMatchObject({ nationalite: 'Béninois', pays: 'Béni' })
    wrapper.unmount()
  })
})

describe('PersonalInfoForm — bloc alertes WhatsApp', () => {
  it('passe au bloc la saisie en cours et le numéro enregistré', async () => {
    const wrapper = mount(PersonalInfoForm, {
      props: {
        personalInfo: {
          sexe: null,
          date_naissance: null,
          nationalite: null,
          pays: null,
          show_age: true,
          whatsapp_number: '+229 01 97 45 12 33',
        },
        isSaving: false,
        error: null,
      },
    })
    const toggle = wrapper.findComponent({ name: 'WhatsappAlertsToggle' })
    expect(toggle.props('savedNumber')).toBe('+229 01 97 45 12 33')
    expect(toggle.props('typedNumber')).toBe('+229 01 97 45 12 33')

    await wrapper.find('[data-testid="whatsapp-number-input"]').setValue('+229 01 11 11 11 11')
    expect(toggle.props('typedNumber')).toBe('+229 01 11 11 11 11')
    expect(toggle.props('savedNumber')).toBe('+229 01 97 45 12 33')
  })
})
