import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PersonalInfoForm from '../PersonalInfoForm.vue'

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
