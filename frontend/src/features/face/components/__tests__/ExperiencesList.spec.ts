import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ExperiencesList from '../ExperiencesList.vue'
import type { Experience } from '../../types'

describe('ExperiencesList', () => {
  const mockExperiences: Experience[] = [
    {
      id: 1,
      titre: 'Publicité Coca-Cola',
      description: 'Tournage publicitaire',
      annee: 2024,
      created_at: '2024-01-01T00:00:00.000Z',
      updated_at: '2024-01-01T00:00:00.000Z',
    },
    {
      id: 2,
      titre: 'Film documentaire',
      description: null,
      annee: 2023,
      created_at: '2023-06-15T00:00:00.000Z',
      updated_at: '2023-06-15T00:00:00.000Z',
    },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('header', () => {
    it('renders header with title', () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.text()).toContain('Expériences professionnelles')
    })

    it('shows add button when not in add or edit mode', () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.find('[data-testid="add-experience-button"]').exists()).toBe(true)
    })
  })

  describe('loading state', () => {
    it('shows loading skeleton when isLoading is true', () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: [],
          isLoading: true,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.find('[data-testid="loading-skeleton"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="experiences-container"]').exists()).toBe(false)
    })

    it('does not show loading skeleton when isLoading is false', () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.find('[data-testid="loading-skeleton"]').exists()).toBe(false)
    })
  })

  describe('empty state', () => {
    it('shows empty state when no experiences and not loading', () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: [],
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.find('[data-testid="empty-state"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Aucune expérience ajoutée')
    })

    it('shows add button in empty state', () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: [],
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.find('[data-testid="empty-add-button"]').exists()).toBe(true)
    })

    it('clicking empty state add button shows add form', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: [],
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      await wrapper.find('[data-testid="empty-add-button"]').trigger('click')

      expect(wrapper.find('[data-testid="add-form-container"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="empty-state"]').exists()).toBe(false)
    })
  })

  describe('experiences list', () => {
    it('renders experience cards when experiences exist', () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      expect(wrapper.find('[data-testid="experiences-container"]').exists()).toBe(true)
      expect(wrapper.findAll('[data-testid="experience-card"]')).toHaveLength(2)
    })
  })

  describe('add mode', () => {
    it('shows add form when add button is clicked', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      await wrapper.find('[data-testid="add-experience-button"]').trigger('click')

      expect(wrapper.find('[data-testid="add-form-container"]').exists()).toBe(true)
    })

    it('hides add button when in add mode', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      await wrapper.find('[data-testid="add-experience-button"]').trigger('click')

      expect(wrapper.find('[data-testid="add-experience-button"]').exists()).toBe(false)
    })

    it('emits add event when form is submitted', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      await wrapper.find('[data-testid="add-experience-button"]').trigger('click')

      // Fill form and submit
      const form = wrapper.find('[data-testid="experience-form"]')
      await wrapper.find('[data-testid="titre-input"]').setValue('New Experience')
      await form.trigger('submit')
      await flushPromises()

      const emitted = wrapper.emitted('add')
      expect(emitted).toBeTruthy()
      expect(emitted?.[0][0]).toMatchObject({
        titre: 'New Experience',
      })
    })
  })

  describe('edit mode', () => {
    it('shows edit form when edit is clicked on a card', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      // Trigger edit on first card
      const editButton = wrapper.findAll('[data-testid="edit-button"]')[0]
      await editButton.trigger('click')

      expect(wrapper.find('[data-testid="edit-form-container"]').exists()).toBe(true)
    })

    it('hides the card being edited and shows form in its place', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      // Trigger edit on first card
      const editButton = wrapper.findAll('[data-testid="edit-button"]')[0]
      await editButton.trigger('click')

      // Only one card should be visible (the other is replaced by form)
      expect(wrapper.findAll('[data-testid="experience-card"]')).toHaveLength(1)
    })

    it('emits edit event when edit form is submitted', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      // Trigger edit on first card
      const editButton = wrapper.findAll('[data-testid="edit-button"]')[0]
      await editButton.trigger('click')

      // Submit form
      const form = wrapper.find('[data-testid="experience-form"]')
      await form.trigger('submit')
      await flushPromises()

      const emitted = wrapper.emitted('edit')
      expect(emitted).toBeTruthy()
      expect(emitted?.[0][0]).toBe(1) // id of first experience
    })

    it('hides add button when in edit mode', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      // Trigger edit
      const editButton = wrapper.findAll('[data-testid="edit-button"]')[0]
      await editButton.trigger('click')

      expect(wrapper.find('[data-testid="add-experience-button"]').exists()).toBe(false)
    })
  })

  describe('delete confirmation', () => {
    it('shows delete confirmation dialog when delete is clicked', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
        global: {
          stubs: {
            teleport: true,
          },
        },
      })

      // Trigger delete on first card
      const deleteButton = wrapper.findAll('[data-testid="delete-button"]')[0]
      await deleteButton.trigger('click')

      expect(wrapper.find('[data-testid="delete-confirm-dialog"]').exists()).toBe(true)
    })

    it('emits delete event when confirmation is clicked', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
        global: {
          stubs: {
            teleport: true,
          },
        },
      })

      // Trigger delete on first card
      const deleteButton = wrapper.findAll('[data-testid="delete-button"]')[0]
      await deleteButton.trigger('click')

      // Confirm deletion
      await wrapper.find('[data-testid="delete-confirm-button"]').trigger('click')

      const emitted = wrapper.emitted('delete')
      expect(emitted).toBeTruthy()
      expect(emitted?.[0][0]).toBe(1)
    })

    it('hides dialog when cancel is clicked', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
        global: {
          stubs: {
            teleport: true,
          },
        },
      })

      // Trigger delete
      const deleteButton = wrapper.findAll('[data-testid="delete-button"]')[0]
      await deleteButton.trigger('click')

      // Cancel
      await wrapper.find('[data-testid="delete-cancel-button"]').trigger('click')

      expect(wrapper.find('[data-testid="delete-confirm-dialog"]').exists()).toBe(false)
    })
  })

  describe('resetFormStates', () => {
    it('exposes resetFormStates method', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      // Enter add mode
      await wrapper.find('[data-testid="add-experience-button"]').trigger('click')
      expect(wrapper.find('[data-testid="add-form-container"]').exists()).toBe(true)

      // Call resetFormStates
      wrapper.vm.resetFormStates()
      await flushPromises()

      expect(wrapper.find('[data-testid="add-form-container"]').exists()).toBe(false)
    })
  })

  describe('state transitions', () => {
    it('closes add form when switching to edit mode', async () => {
      const wrapper = mount(ExperiencesList, {
        props: {
          experiences: mockExperiences,
          isLoading: false,
          isSaving: false,
          isDeleting: false,
          error: null,
          validationErrors: {},
        },
      })

      // Enter add mode
      await wrapper.find('[data-testid="add-experience-button"]').trigger('click')
      expect(wrapper.find('[data-testid="add-form-container"]').exists()).toBe(true)

      // Click edit on a card
      const editButton = wrapper.findAll('[data-testid="edit-button"]')[0]
      await editButton.trigger('click')

      expect(wrapper.find('[data-testid="add-form-container"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="edit-form-container"]').exists()).toBe(true)
    })
  })
})
