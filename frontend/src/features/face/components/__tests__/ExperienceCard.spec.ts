import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ExperienceCard from '../ExperienceCard.vue'
import type { Experience } from '../../types'

describe('ExperienceCard', () => {
  const mockExperience: Experience = {
    id: 1,
    titre: 'Publicité Coca-Cola',
    description: 'Tournage publicitaire pour campagne nationale',
    date_debut: '2024-01-15',
    date_fin: '2024-03-20',
    is_ongoing: false,
    formatted_period: '15/01/2024 - 20/03/2024',
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z',
  }

  const mockExperienceWithoutDescription: Experience = {
    id: 2,
    titre: 'Film documentaire',
    description: null,
    date_debut: '2023-06-15',
    date_fin: '2023-12-20',
    is_ongoing: false,
    formatted_period: '15/06/2023 - 20/12/2023',
    created_at: '2023-06-15T00:00:00.000Z',
    updated_at: '2023-06-15T00:00:00.000Z',
  }

  const mockOngoingExperience: Experience = {
    id: 3,
    titre: 'Projet en cours',
    description: 'Un projet actuellement en cours',
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

  it('renders experience data correctly', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: false,
      },
    })

    expect(wrapper.find('[data-testid="experience-title"]').text()).toBe('Publicité Coca-Cola')
    expect(wrapper.find('[data-testid="experience-period"]').text()).toContain('15/01/2024 - 20/03/2024')
    expect(wrapper.find('[data-testid="experience-description"]').text()).toBe(
      'Tournage publicitaire pour campagne nationale',
    )
  })

  it('renders ongoing experience with "Présent"', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockOngoingExperience,
        isDeleting: false,
      },
    })

    expect(wrapper.find('[data-testid="experience-period"]').text()).toContain('01/06/2024 - Présent')
  })

  it('applies teal styling for ongoing experiences', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockOngoingExperience,
        isDeleting: false,
      },
    })

    const periodBadge = wrapper.find('[data-testid="experience-period"]')
    expect(periodBadge.classes()).toContain('bg-teal-100')
    expect(periodBadge.classes()).toContain('text-teal-800')
  })

  it('applies gray styling for completed experiences', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: false,
      },
    })

    const periodBadge = wrapper.find('[data-testid="experience-period"]')
    expect(periodBadge.classes()).toContain('bg-gray-100')
    expect(periodBadge.classes()).toContain('text-gray-700')
  })

  it('hides description when null', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperienceWithoutDescription,
        isDeleting: false,
      },
    })

    expect(wrapper.find('[data-testid="experience-description"]').exists()).toBe(false)
  })

  it('renders edit button', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: false,
      },
    })

    expect(wrapper.find('[data-testid="edit-button"]').exists()).toBe(true)
  })

  it('renders delete button', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: false,
      },
    })

    expect(wrapper.find('[data-testid="delete-button"]').exists()).toBe(true)
  })

  it('emits edit event when edit button is clicked', async () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: false,
      },
    })

    await wrapper.find('[data-testid="edit-button"]').trigger('click')

    const emitted = wrapper.emitted('edit')
    expect(emitted).toBeTruthy()
    expect(emitted?.[0][0]).toEqual(mockExperience)
  })

  it('emits delete event when delete button is clicked', async () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: false,
      },
    })

    await wrapper.find('[data-testid="delete-button"]').trigger('click')

    const emitted = wrapper.emitted('delete')
    expect(emitted).toBeTruthy()
    expect(emitted?.[0][0]).toBe(1)
  })

  it('disables delete button when isDeleting is true', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: true,
      },
    })

    const deleteButton = wrapper.find('[data-testid="delete-button"]')
    expect(deleteButton.attributes('disabled')).toBeDefined()
  })

  it('shows spinner in delete button when isDeleting is true', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: true,
      },
    })

    const deleteButton = wrapper.find('[data-testid="delete-button"]')
    expect(deleteButton.find('svg.animate-spin').exists()).toBe(true)
  })

  it('shows trash icon when not deleting', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: false,
      },
    })

    const deleteButton = wrapper.find('[data-testid="delete-button"]')
    expect(deleteButton.find('svg.animate-spin').exists()).toBe(false)
    expect(deleteButton.find('svg').exists()).toBe(true)
  })

  it('has proper data-testid on card container', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: false,
      },
    })

    expect(wrapper.find('[data-testid="experience-card"]').exists()).toBe(true)
  })

  it('has hover effect classes for action buttons', () => {
    const wrapper = mount(ExperienceCard, {
      props: {
        experience: mockExperience,
        isDeleting: false,
      },
    })

    const actionButtons = wrapper.find('[data-testid="action-buttons"]')
    expect(actionButtons.classes()).toContain('group-hover:opacity-100')
  })
})
