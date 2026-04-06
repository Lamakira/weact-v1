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
