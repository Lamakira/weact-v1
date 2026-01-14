import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ExperienceForm from '../ExperienceForm.vue'
import type { Experience } from '../../types'

describe('ExperienceForm', () => {
  const today = new Date().toISOString().split('T')[0]

  const mockExperience: Experience = {
    id: 1,
    titre: 'Publicité Coca-Cola',
    description: 'Tournage publicitaire',
    date_debut: '2024-01-15',
    date_fin: '2024-03-20',
    is_ongoing: false,
    formatted_period: '15/01/2024 - 20/03/2024',
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z',
  }

  const mockOngoingExperience: Experience = {
    id: 2,
    titre: 'Projet en cours',
    description: null,
    date_debut: '2024-06-01',
    date_fin: null,
    is_ongoing: true,
    formatted_period: '01/06/2024 - Présent',
    created_at: '2024-06-01T00:00:00.000Z',
    updated_at: '2024-06-01T00:00:00.000Z',
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
      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')
      const isOngoingCheckbox = wrapper.find('[data-testid="is_ongoing-checkbox"]')
      const descriptionInput = wrapper.find('[data-testid="description-input"]')

      expect((titreInput.element as HTMLInputElement).value).toBe('')
      expect((dateDebutInput.element as HTMLInputElement).value).toBe('')
      expect((dateFinInput.element as HTMLInputElement).value).toBe('')
      expect((isOngoingCheckbox.element as HTMLInputElement).checked).toBe(false)
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
      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')
      const isOngoingCheckbox = wrapper.find('[data-testid="is_ongoing-checkbox"]')
      const descriptionInput = wrapper.find('[data-testid="description-input"]')

      expect((titreInput.element as HTMLInputElement).value).toBe('Publicité Coca-Cola')
      expect((dateDebutInput.element as HTMLInputElement).value).toBe('2024-01-15')
      expect((dateFinInput.element as HTMLInputElement).value).toBe('2024-03-20')
      expect((isOngoingCheckbox.element as HTMLInputElement).checked).toBe(false)
      expect((descriptionInput.element as HTMLTextAreaElement).value).toBe('Tournage publicitaire')
    })

    it('renders form with ongoing experience values', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: mockOngoingExperience,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')
      const isOngoingCheckbox = wrapper.find('[data-testid="is_ongoing-checkbox"]')

      expect((dateDebutInput.element as HTMLInputElement).value).toBe('2024-06-01')
      expect((dateFinInput.element as HTMLInputElement).value).toBe('')
      expect((isOngoingCheckbox.element as HTMLInputElement).checked).toBe(true)
      expect((dateFinInput.element as HTMLInputElement).disabled).toBe(true)
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
      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')
      const descriptionInput = wrapper.find('[data-testid="description-input"]')

      await titreInput.setValue('Nouvelle expérience')
      await dateDebutInput.setValue('2023-01-15')
      await dateFinInput.setValue('2023-06-30')
      await descriptionInput.setValue('Ma description')

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      const emitted = wrapper.emitted('submit')
      expect(emitted).toBeTruthy()
      expect(emitted?.[0][0]).toEqual({
        titre: 'Nouvelle expérience',
        description: 'Ma description',
        date_debut: '2023-01-15',
        date_fin: '2023-06-30',
      })
    })

    it('emits submit with null date_fin when ongoing', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      const isOngoingCheckbox = wrapper.find('[data-testid="is_ongoing-checkbox"]')

      await titreInput.setValue('Expérience en cours')
      await dateDebutInput.setValue('2024-01-15')
      await isOngoingCheckbox.setValue(true)

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      const emitted = wrapper.emitted('submit')
      expect(emitted?.[0][0]).toMatchObject({
        titre: 'Expérience en cours',
        date_debut: '2024-01-15',
        date_fin: null,
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
      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      await titreInput.setValue('Test')
      await dateDebutInput.setValue('2024-01-15')

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

    it('displays date_debut validation error', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: { date_debut: ['La date de début est requise.'] },
        },
      })

      const dateDebutError = wrapper.find('[data-testid="date_debut-error"]')
      expect(dateDebutError.exists()).toBe(true)
      expect(dateDebutError.text()).toBe('La date de début est requise.')
    })

    it('displays date_fin validation error', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: { date_fin: ['La date de fin doit être postérieure ou égale à la date de début.'] },
        },
      })

      const dateFinError = wrapper.find('[data-testid="date_fin-error"]')
      expect(dateFinError.exists()).toBe(true)
      expect(dateFinError.text()).toBe('La date de fin doit être postérieure ou égale à la date de début.')
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

    it('date inputs have max attribute set to today', () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')
      expect(dateDebutInput.attributes('max')).toBe(today)
      expect(dateFinInput.attributes('max')).toBe(today)
    })

    it('date_fin input has min attribute linked to date_debut', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')

      await dateDebutInput.setValue('2024-01-15')
      expect(dateFinInput.attributes('min')).toBe('2024-01-15')
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
      expect((wrapper.find('[data-testid="date_debut-input"]').element as HTMLInputElement).value).toBe(
        '2024-01-15',
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
      expect((wrapper.find('[data-testid="date_debut-input"]').element as HTMLInputElement).value).toBe('')
      expect((wrapper.find('[data-testid="date_fin-input"]').element as HTMLInputElement).value).toBe('')
      expect((wrapper.find('[data-testid="is_ongoing-checkbox"]').element as HTMLInputElement).checked).toBe(false)
    })

    it('clears date_fin when is_ongoing is checked', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')
      const isOngoingCheckbox = wrapper.find('[data-testid="is_ongoing-checkbox"]')

      await dateFinInput.setValue('2024-06-30')
      expect((dateFinInput.element as HTMLInputElement).value).toBe('2024-06-30')

      await isOngoingCheckbox.setValue(true)
      await flushPromises()

      // date_fin should be cleared and disabled
      expect((dateFinInput.element as HTMLInputElement).disabled).toBe(true)
    })

    it('restores date_fin when is_ongoing is unchecked', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')
      const isOngoingCheckbox = wrapper.find('[data-testid="is_ongoing-checkbox"]')

      // Set date_fin
      await dateFinInput.setValue('2024-06-30')
      expect((dateFinInput.element as HTMLInputElement).value).toBe('2024-06-30')

      // Check "En cours" - clears date_fin
      await isOngoingCheckbox.setValue(true)
      await flushPromises()
      expect((dateFinInput.element as HTMLInputElement).disabled).toBe(true)

      // Uncheck "En cours" - should restore date_fin
      await isOngoingCheckbox.setValue(false)
      await flushPromises()
      expect((dateFinInput.element as HTMLInputElement).disabled).toBe(false)
      expect((dateFinInput.element as HTMLInputElement).value).toBe('2024-06-30')
    })
  })

  describe('client-side validation', () => {
    it('prevents submit when date_fin is before date_debut', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')

      await titreInput.setValue('Test')
      await dateDebutInput.setValue('2024-06-01')
      await dateFinInput.setValue('2024-01-01') // Before start date

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      // Should NOT emit submit
      expect(wrapper.emitted('submit')).toBeFalsy()

      // Should show client-side error
      const dateFinError = wrapper.find('[data-testid="date_fin-error"]')
      expect(dateFinError.exists()).toBe(true)
      expect(dateFinError.text()).toBe('La date de fin doit être après la date de début')
    })

    it('allows submit when date_fin equals date_debut (same day)', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')

      await titreInput.setValue('One day event')
      await dateDebutInput.setValue('2024-06-15')
      await dateFinInput.setValue('2024-06-15') // Same day

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      // Should emit submit
      const emitted = wrapper.emitted('submit')
      expect(emitted).toBeTruthy()
      expect(emitted?.[0][0]).toMatchObject({
        titre: 'One day event',
        date_debut: '2024-06-15',
        date_fin: '2024-06-15',
      })
    })

    it('clears client-side error on subsequent valid submit', async () => {
      const wrapper = mount(ExperienceForm, {
        props: {
          experience: null,
          isSaving: false,
          error: null,
          validationErrors: {},
        },
      })

      const titreInput = wrapper.find('[data-testid="titre-input"]')
      const dateDebutInput = wrapper.find('[data-testid="date_debut-input"]')
      const dateFinInput = wrapper.find('[data-testid="date_fin-input"]')

      await titreInput.setValue('Test')
      await dateDebutInput.setValue('2024-06-01')
      await dateFinInput.setValue('2024-01-01') // Invalid

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      // Error should be shown
      expect(wrapper.find('[data-testid="date_fin-error"]').exists()).toBe(true)

      // Fix the date
      await dateFinInput.setValue('2024-07-01')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      // Error should be cleared and submit should happen
      expect(wrapper.find('[data-testid="date_fin-error"]').exists()).toBe(false)
      expect(wrapper.emitted('submit')).toBeTruthy()
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
      expect(wrapper.find('label[for="date_debut"]').exists()).toBe(true)
      expect(wrapper.find('label[for="date_fin"]').exists()).toBe(true)
      expect(wrapper.find('label[for="is_ongoing"]').exists()).toBe(true)
      expect(wrapper.find('label[for="description"]').exists()).toBe(true)

      expect(wrapper.find('#titre').exists()).toBe(true)
      expect(wrapper.find('#date_debut').exists()).toBe(true)
      expect(wrapper.find('#date_fin').exists()).toBe(true)
      expect(wrapper.find('#is_ongoing').exists()).toBe(true)
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
