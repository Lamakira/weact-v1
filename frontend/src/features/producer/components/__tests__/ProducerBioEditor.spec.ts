import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ProducerBioEditor from '../ProducerBioEditor.vue'

describe('ProducerBioEditor', () => {
  const defaultProps = {
    bio: null as string | null,
    isSaving: false,
    error: null as string | null,
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('rendering', () => {
    it('should render the bio editor', () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      expect(wrapper.find('[data-testid="bio-editor"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="bio-textarea"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="bio-char-count"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="bio-save-button"]').exists()).toBe(true)
    })

    it('should display bio text in textarea', () => {
      const wrapper = mount(ProducerBioEditor, {
        props: { ...defaultProps, bio: 'Test bio content' },
      })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      expect((textarea.element as HTMLTextAreaElement).value).toBe('Test bio content')
    })

    it('should display error message when error prop is set', () => {
      const wrapper = mount(ProducerBioEditor, {
        props: { ...defaultProps, error: 'Test error message' },
      })

      expect(wrapper.find('[data-testid="bio-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="bio-error"]').text()).toBe('Test error message')
    })

    it('should not display error when error is null', () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      expect(wrapper.find('[data-testid="bio-error"]').exists()).toBe(false)
    })
  })

  describe('character counter', () => {
    it('should show 0/500 when bio is null', () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      expect(wrapper.find('[data-testid="bio-char-count"]').text()).toBe('0/500')
    })

    it('should update character count when typing', async () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('Hello World')

      expect(wrapper.find('[data-testid="bio-char-count"]').text()).toBe('11/500')
    })

    it('should show warning style when near limit', async () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('a'.repeat(460))

      const charCount = wrapper.find('[data-testid="bio-char-count"]')
      expect(charCount.classes()).toContain('text-yellow-600')
    })

    it('should show error style when over limit', async () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('a'.repeat(510))

      const charCount = wrapper.find('[data-testid="bio-char-count"]')
      expect(charCount.classes()).toContain('text-red-600')
    })

    it('should show over limit warning message', async () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('a'.repeat(510))

      expect(wrapper.find('[data-testid="bio-over-limit-warning"]').exists()).toBe(true)
    })
  })

  describe('save button', () => {
    it('should be disabled when no changes', () => {
      const wrapper = mount(ProducerBioEditor, {
        props: { ...defaultProps, bio: 'Test bio' },
      })

      const saveButton = wrapper.find('[data-testid="bio-save-button"]')
      expect((saveButton.element as HTMLButtonElement).disabled).toBe(true)
    })

    it('should be enabled after typing', async () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('New bio content')

      const saveButton = wrapper.find('[data-testid="bio-save-button"]')
      expect((saveButton.element as HTMLButtonElement).disabled).toBe(false)
    })

    it('should be disabled when over character limit', async () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('a'.repeat(510))

      const saveButton = wrapper.find('[data-testid="bio-save-button"]')
      expect((saveButton.element as HTMLButtonElement).disabled).toBe(true)
    })

    it('should be disabled when saving', () => {
      const wrapper = mount(ProducerBioEditor, {
        props: { ...defaultProps, isSaving: true },
      })

      const saveButton = wrapper.find('[data-testid="bio-save-button"]')
      expect((saveButton.element as HTMLButtonElement).disabled).toBe(true)
      expect(saveButton.text()).toBe('Enregistrement...')
    })

    it('should emit save event with bio text when clicked', async () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('New bio content')

      const saveButton = wrapper.find('[data-testid="bio-save-button"]')
      await saveButton.trigger('click')

      expect(wrapper.emitted('save')).toBeTruthy()
      expect(wrapper.emitted('save')![0]).toEqual(['New bio content'])
    })

    it('should emit null for empty bio', async () => {
      const wrapper = mount(ProducerBioEditor, {
        props: { ...defaultProps, bio: 'Existing bio' },
      })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('')

      const saveButton = wrapper.find('[data-testid="bio-save-button"]')
      await saveButton.trigger('click')

      expect(wrapper.emitted('save')).toBeTruthy()
      expect(wrapper.emitted('save')![0]).toEqual([null])
    })
  })

  describe('reset button', () => {
    it('should not show reset button when no changes', () => {
      const wrapper = mount(ProducerBioEditor, {
        props: { ...defaultProps, bio: 'Test bio' },
      })

      expect(wrapper.find('[data-testid="bio-reset-button"]').exists()).toBe(false)
    })

    it('should show reset button after changes', async () => {
      const wrapper = mount(ProducerBioEditor, {
        props: { ...defaultProps, bio: 'Original bio' },
      })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('Modified bio')

      expect(wrapper.find('[data-testid="bio-reset-button"]').exists()).toBe(true)
    })

    it('should reset to original bio when clicked', async () => {
      const wrapper = mount(ProducerBioEditor, {
        props: { ...defaultProps, bio: 'Original bio' },
      })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('Modified bio')

      const resetButton = wrapper.find('[data-testid="bio-reset-button"]')
      await resetButton.trigger('click')

      // After reset, textarea should have original value
      expect((textarea.element as HTMLTextAreaElement).value).toBe('Original bio')
    })
  })

  describe('textarea state', () => {
    it('should disable textarea when saving', () => {
      const wrapper = mount(ProducerBioEditor, {
        props: { ...defaultProps, isSaving: true },
      })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      expect((textarea.element as HTMLTextAreaElement).disabled).toBe(true)
    })

    it('should apply warning border style when near limit', async () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('a'.repeat(460))

      expect(textarea.classes()).toContain('border-yellow-400')
    })

    it('should apply error border style when over limit', async () => {
      const wrapper = mount(ProducerBioEditor, { props: defaultProps })

      const textarea = wrapper.find('[data-testid="bio-textarea"]')
      await textarea.setValue('a'.repeat(510))

      expect(textarea.classes()).toContain('border-red-500')
    })
  })
})
