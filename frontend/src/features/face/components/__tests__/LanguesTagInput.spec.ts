import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import LanguesTagInput from '../LanguesTagInput.vue'

describe('LanguesTagInput', () => {
  function mountComponent(props: Partial<{
    langues: string[] | null
    isSaving: boolean
    error: string | null
    maxLangues: number
    maxLangueLength: number
  }> = {}) {
    return mount(LanguesTagInput, {
      props: {
        langues: null,
        isSaving: false,
        error: null,
        maxLangues: 10,
        maxLangueLength: 50,
        ...props,
      },
    })
  }

  it('renders the component with title', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="langues-tag-input"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Langues parlées')
  })

  it('displays existing tags', () => {
    const wrapper = mountComponent({
      langues: ['Français', 'Anglais'],
    })

    const tags = wrapper.findAll('[data-testid="langue-tag"]')
    expect(tags).toHaveLength(2)
    expect(tags[0].text()).toContain('Français')
    expect(tags[1].text()).toContain('Anglais')
  })

  it('adds a tag on Enter key', async () => {
    const wrapper = mountComponent()
    const input = wrapper.find('[data-testid="langue-input"]')

    await input.setValue('Français')
    await input.trigger('keydown', { key: 'Enter' })

    const tags = wrapper.findAll('[data-testid="langue-tag"]')
    expect(tags).toHaveLength(1)
    expect(tags[0].text()).toContain('Français')
  })

  it('adds a tag on comma key', async () => {
    const wrapper = mountComponent()
    const input = wrapper.find('[data-testid="langue-input"]')

    await input.setValue('Anglais')
    await input.trigger('keydown', { key: ',' })

    const tags = wrapper.findAll('[data-testid="langue-tag"]')
    expect(tags).toHaveLength(1)
    expect(tags[0].text()).toContain('Anglais')
  })

  it('removes a tag when clicking remove button', async () => {
    const wrapper = mountComponent({
      langues: ['Français', 'Anglais'],
    })

    expect(wrapper.findAll('[data-testid="langue-tag"]')).toHaveLength(2)

    const removeButtons = wrapper.findAll('[data-testid="remove-tag-button"]')
    await removeButtons[0].trigger('click')

    expect(wrapper.findAll('[data-testid="langue-tag"]')).toHaveLength(1)
    expect(wrapper.find('[data-testid="langue-tag"]').text()).toContain('Anglais')
  })

  it('does not add duplicate tags', async () => {
    const wrapper = mountComponent({
      langues: ['Français'],
    })

    const input = wrapper.find('[data-testid="langue-input"]')
    await input.setValue('Français')
    await input.trigger('keydown', { key: 'Enter' })

    expect(wrapper.findAll('[data-testid="langue-tag"]')).toHaveLength(1)
  })

  it('shows suggestions', () => {
    const wrapper = mountComponent()

    const suggestions = wrapper.findAll('[data-testid="suggestion-button"]')
    expect(suggestions.length).toBeGreaterThan(0)
    expect(suggestions[0].text()).toContain('Français')
  })

  it('adds a suggestion on click', async () => {
    const wrapper = mountComponent()

    const suggestions = wrapper.findAll('[data-testid="suggestion-button"]')
    await suggestions[0].trigger('click')

    const tags = wrapper.findAll('[data-testid="langue-tag"]')
    expect(tags).toHaveLength(1)
  })

  it('hides suggestion once added', async () => {
    const wrapper = mountComponent({
      langues: ['Français'],
    })

    const suggestions = wrapper.findAll('[data-testid="suggestion-button"]')
    const francaisButton = suggestions.find((s) => s.text().includes('Français'))
    expect(francaisButton).toBeUndefined()
  })

  it('emits save event with tags', async () => {
    const wrapper = mountComponent({
      langues: ['Français'],
    })

    // Add a tag to create a change
    const input = wrapper.find('[data-testid="langue-input"]')
    await input.setValue('Anglais')
    await input.trigger('keydown', { key: 'Enter' })

    const saveButton = wrapper.find('[data-testid="langues-save-button"]')
    await saveButton.trigger('click')

    expect(wrapper.emitted('save')).toBeTruthy()
    expect(wrapper.emitted('save')![0]).toEqual([['Français', 'Anglais']])
  })

  it('emits save with null when all tags are removed', async () => {
    const wrapper = mountComponent({
      langues: ['Français'],
    })

    // Remove the tag
    const removeButton = wrapper.find('[data-testid="remove-tag-button"]')
    await removeButton.trigger('click')

    const saveButton = wrapper.find('[data-testid="langues-save-button"]')
    await saveButton.trigger('click')

    expect(wrapper.emitted('save')).toBeTruthy()
    expect(wrapper.emitted('save')![0]).toEqual([null])
  })

  it('displays error message', () => {
    const wrapper = mountComponent({
      error: 'Une erreur est survenue',
    })

    const errorEl = wrapper.find('[data-testid="langues-error"]')
    expect(errorEl.exists()).toBe(true)
    expect(errorEl.text()).toContain('Une erreur est survenue')
  })

  it('disables save button when no changes', () => {
    const wrapper = mountComponent({
      langues: ['Français'],
    })

    const saveButton = wrapper.find('[data-testid="langues-save-button"]')
    expect((saveButton.element as HTMLButtonElement).disabled).toBe(true)
  })

  it('shows counter', () => {
    const wrapper = mountComponent({
      langues: ['Français', 'Anglais'],
    })

    expect(wrapper.text()).toContain('2/10')
  })
})
