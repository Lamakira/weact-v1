import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ExperienceForm from '../ExperienceForm.vue'
import type { Experience } from '../../types'

describe('ExperienceForm', () => {
  const currentYear = new Date().getFullYear()

  const mockExperience: Experience = {
    id: 1,
    titre: 'Publicité Coca-Cola',
    description: 'Tournage publicitaire',
    annee: 2024,
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('add mode (no experience prop)', () => {
    it('renders empty form with default values', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      const anneeInput = wrapper.find('[data-testid="annee-input"]')
      const descriptionInput = wrapper.find('[data-testid="description-input"]')

      expect((titreInput.element as HTMLInputElement).value).toBe('')
      expect((anneeInput.element as HTMLInputElement).value).toBe(String(currentYear))
      expect((descriptionInput.element as HTMLTextAreaElement).value).toBe('')
    })

    it('does not show cancel button in add mode by default', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.find('[data-testid="cancel-button"]').exists()).toBe(false)
    })

    it('shows cancel button in add mode when showCancel is true', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
          showCancel: true,
        },
      })

      expect(wrapper.find('[data-testid="cancel-button"]').exists()).toBe(true)
    })

    it('shows "Ajouter" text on submit button', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const submitButton = wrapper.find('[data-testid="submit-button"]')
      expect(submitButton.text()).toBe('Ajouter')
    })
  })

  describe('edit mode (with experience prop)', () => {
    it('renders form with experience values', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: mockExperience,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      const anneeInput = wrapper.find('[data-testid="annee-input"]')
      const descriptionInput = wrapper.find('[data-testid="description-input"]')

      expect((titreInput.element as HTMLInputElement).value).toBe('Publicité Coca-Cola')
      expect((anneeInput.element as HTMLInputElement).value).toBe('2024')
      expect((descriptionInput.element as HTMLTextAreaElement).value).toBe('Tournage publicitaire')
    })

    it('shows cancel button in edit mode', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: mockExperience,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.find('[data-testid="cancel-button"]').exists()).toBe(true)
    })

    it('shows "Mettre à jour" text on submit button', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: mockExperience,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const submitButton = wrapper.find('[data-testid="submit-button"]')
      expect(submitButton.text()).toBe('Mettre à jour')
    })

    it('emits cancel event when cancel button is clicked', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: mockExperience,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      await wrapper.find('[data-testid="cancel-button"]').trigger('click')

      expect(wrapper.emitted('cancel')).toBeTruthy()
    })
  })

  describe('form submission', () => {
    it('emits submit event with form data', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      const anneeInput = wrapper.find('[data-testid="annee-input"]')
      const descriptionInput = wrapper.find('[data-testid="description-input"]')

      await titreInput.setValue('Nouvelle expérience')
      await anneeInput.setValue(2023)
      await descriptionInput.setValue('Ma description')

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      const emitted = wrapper.emitted('submit')
      expect(emitted).toBeTruthy()
      expect(emitted?.[0][0]).toEqual({
        titre: 'Nouvelle expérience',
        description: 'Ma description',
        annee: 2023,
      })
    })

    it('emits submit with null description when empty', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      await titreInput.setValue('Test')

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      const emitted = wrapper.emitted('submit')
      expect(emitted?.[0][0]).toMatchObject({
        description: null,
      })
    })
  })

  describe('saving state', () => {
    it('disables submit button when saving', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: true,
          error: null,
          validationErrors: {},
        },
      })

      const submitButton = wrapper.find('[data-testid="submit-button"]')
      expect(submitButton.attributes('disabled')).toBeDefined()
    })

    it('shows loading spinner when saving', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: true,
          error: null,
          validationErrors: {},
        },
      })

      const submitButton = wrapper.find('[data-testid="submit-button"]')
      expect(submitButton.find('svg.animate-spin').exists()).toBe(true)
    })

    it('shows "Enregistrement..." text when saving', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: true,
          error: null,
          validationErrors: {},
        },
      })

      const submitButton = wrapper.find('[data-testid="submit-button"]')
      expect(submitButton.text()).toContain('Enregistrement...')
    })
  })

  describe('error handling', () => {
    it('displays global error message', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: 'Une erreur est survenue',
          validationErrors: {},
        },
      })

      const errorMessage = wrapper.find('[data-testid="error-message"]')
      expect(errorMessage.exists()).toBe(true)
      expect(errorMessage.text()).toContain('Une erreur est survenue')
    })

    it('does not display global error when there are validation errors', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: 'Une erreur est survenue',
          validationErrors: { titre: ['Le titre est requis'] },
        },
      })

      const errorMessage = wrapper.find('[data-testid="error-message"]')
      expect(errorMessage.exists()).toBe(false)
    })

    it('displays titre validation error', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: { titre: ['Le titre est requis'] },
        },
      })

      const titreError = wrapper.find('[data-testid="titre-error"]')
      expect(titreError.exists()).toBe(true)
      expect(titreError.text()).toBe('Le titre est requis')
    })

    it('displays annee validation error', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: { annee: ["L'année doit être entre 1950 et 2024"] },
        },
      })

      const anneeError = wrapper.find('[data-testid="annee-error"]')
      expect(anneeError.exists()).toBe(true)
      expect(anneeError.text()).toBe("L'année doit être entre 1950 et 2024")
    })

    it('displays description validation error', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: { description: ['La description ne peut pas dépasser 500 caractères'] },
        },
      })

      const descriptionError = wrapper.find('[data-testid="description-error"]')
      expect(descriptionError.exists()).toBe(true)
      expect(descriptionError.text()).toBe('La description ne peut pas dépasser 500 caractères')
    })

    it('applies error styling to titre input when validation error exists', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: { titre: ['Error'] },
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      expect(titreInput.classes()).toContain('border-red-300')
    })

    it('sets aria-invalid and aria-describedby when validation error exists', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: { titre: ['Le titre est requis'] },
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      expect(titreInput.attributes('aria-invalid')).toBe('true')
      expect(titreInput.attributes('aria-describedby')).toBe('titre-error')
    })

    it('does not set aria-invalid when no validation error', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      expect(titreInput.attributes('aria-invalid')).toBe('false')
      expect(titreInput.attributes('aria-describedby')).toBeUndefined()
    })
  })

  describe('input attributes', () => {
    it('titre input has maxlength 150', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      expect(titreInput.attributes('maxlength')).toBe('150')
    })

    it('description textarea has maxlength 500', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const descriptionInput = wrapper.find('[data-testid="description-input"]')
      expect(descriptionInput.attributes('maxlength')).toBe('500')
    })

    it('annee input has min 1950 and max current year', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const anneeInput = wrapper.find('[data-testid="annee-input"]')
      expect(anneeInput.attributes('min')).toBe('1950')
      expect(anneeInput.attributes('max')).toBe(String(currentYear))
    })
  })

  describe('character counter', () => {
    it('displays character count for description', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      // Initial state
      expect(wrapper.text()).toContain('0/500')

      // After typing
      const descriptionInput = wrapper.find('[data-testid="description-input"]')
      await descriptionInput.setValue('Test text')

      expect(wrapper.text()).toContain('9/500')
    })
  })

  describe('watch behavior', () => {
    it('updates form when experience prop changes', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      // Initially empty
      expect((wrapper.find('[data-testid="titre-input"]').element as HTMLInputElement).value).toBe('')

      // Update props
      await wrapper.setProps({ experience: mockExperience })
      await flushPromises()

      expect((wrapper.find('[data-testid="titre-input"]').element as HTMLInputElement).value).toBe(
        'Publicité Coca-Cola',
      )
    })

    it('resets form when experience prop becomes null', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: mockExperience,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      // Initially has data
      expect((wrapper.find('[data-testid="titre-input"]').element as HTMLInputElement).value).toBe(
        'Publicité Coca-Cola',
      )

      // Update props to null
      await wrapper.setProps({ experience: null })
      await flushPromises()

      expect((wrapper.find('[data-testid="titre-input"]').element as HTMLInputElement).value).toBe('')
      expect((wrapper.find('[data-testid="annee-input"]').element as HTMLInputElement).value).toBe(
        String(currentYear),
      )
    })
  })

  describe('accessibility', () => {
    it('has proper labels for all inputs', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.find('label[for="titre"]').exists()).toBe(true)
      expect(wrapper.find('label[for="annee"]').exists()).toBe(true)
      expect(wrapper.find('label[for="description"]').exists()).toBe(true)

      expect(wrapper.find('#titre').exists()).toBe(true)
      expect(wrapper.find('#annee').exists()).toBe(true)
      expect(wrapper.find('#description').exists()).toBe(true)
    })

    it('error message has role="alert" for accessibility', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: 'Une erreur',
          validationErrors: {},
        },
      })

      const errorMessage = wrapper.find('[data-testid="error-message"]')
      expect(errorMessage.attributes('role')).toBe('alert')
    })

    it('form has novalidate attribute', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.find('form').attributes('novalidate')).toBeDefined()
    })
  })
})
