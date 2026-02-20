import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import CategoryNicheForm from '../CategoryNicheForm.vue'
import type { CategoryNicheInfo, CategoryOption, NicheOption } from '../../types'

describe('CategoryNicheForm', () => {
  const mockCategoryNicheInfo: CategoryNicheInfo = {
    categories: [
      { value: 'acteur', label: 'Acteur' },
      { value: 'mannequin', label: 'Mannequin' },
    ],
    niches: [{ value: 'beaute', label: 'Beauté' }],
  }

  const mockCategoryOptions: CategoryOption[] = [
    { value: 'acteur', label: 'Acteur' },
    { value: 'influenceur', label: 'Influenceur' },
    { value: 'createur', label: 'Créateur de contenu' },
    { value: 'mannequin', label: 'Mannequin' },
    { value: 'figurant', label: 'Figurant' },
    { value: 'modele_photo', label: 'Modèle Photo' },
    { value: 'egerie', label: 'Égérie' },
  ]

  const mockNicheOptions: NicheOption[] = [
    { value: 'beaute', label: 'Beauté' },
    { value: 'nourriture', label: 'Nourriture' },
    { value: 'decouverte', label: 'Découverte' },
    { value: 'mode', label: 'Mode' },
  ]

  const defaultProps = {
    categoryNicheInfo: null as CategoryNicheInfo | null,
    categoryOptions: mockCategoryOptions,
    nicheOptions: mockNicheOptions,
    isSaving: false,
    error: null as string | null,
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders category and niche chip groups', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: defaultProps,
    })

    const categoriesSection = wrapper.find('[data-testid="categories-section"]')
    const nichesSection = wrapper.find('[data-testid="niches-section"]')

    expect(categoriesSection.exists()).toBe(true)
    expect(nichesSection.exists()).toBe(true)
  })

  it('renders all category chips', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: defaultProps,
    })

    for (const option of mockCategoryOptions) {
      const chip = wrapper.find(`[data-testid="category-chip-${option.value}"]`)
      expect(chip.exists()).toBe(true)
      expect(chip.text()).toContain(option.label)
    }
  })

  it('renders all niche chips', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: defaultProps,
    })

    for (const option of mockNicheOptions) {
      const chip = wrapper.find(`[data-testid="niche-chip-${option.value}"]`)
      expect(chip.exists()).toBe(true)
      expect(chip.text()).toContain(option.label)
    }
  })

  it('marks selected categories as active', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        categoryNicheInfo: mockCategoryNicheInfo,
      },
    })

    const acteurChip = wrapper.find('[data-testid="category-chip-acteur"]')
    const mannequinChip = wrapper.find('[data-testid="category-chip-mannequin"]')
    const influenceurChip = wrapper.find('[data-testid="category-chip-influenceur"]')

    // Selected chips should have teal bg
    expect(acteurChip.classes()).toContain('bg-teal-600')
    expect(mannequinChip.classes()).toContain('bg-teal-600')
    // Unselected chip should not
    expect(influenceurChip.classes()).not.toContain('bg-teal-600')
  })

  it('marks selected niches as active', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        categoryNicheInfo: mockCategoryNicheInfo,
      },
    })

    const beauteChip = wrapper.find('[data-testid="niche-chip-beaute"]')
    const modeChip = wrapper.find('[data-testid="niche-chip-mode"]')

    expect(beauteChip.classes()).toContain('bg-purple-600')
    expect(modeChip.classes()).not.toContain('bg-purple-600')
  })

  it('toggles category on click', async () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        categoryNicheInfo: mockCategoryNicheInfo,
      },
    })

    // Click to deselect 'acteur'
    await wrapper.find('[data-testid="category-chip-acteur"]').trigger('click')
    expect(wrapper.find('[data-testid="category-chip-acteur"]').classes()).not.toContain('bg-teal-600')

    // Click to select 'influenceur'
    await wrapper.find('[data-testid="category-chip-influenceur"]').trigger('click')
    expect(wrapper.find('[data-testid="category-chip-influenceur"]').classes()).toContain('bg-teal-600')
  })

  it('emits save event with selected arrays', async () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        categoryNicheInfo: mockCategoryNicheInfo,
      },
    })

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.emitted('save')).toBeTruthy()
    expect(wrapper.emitted('save')![0]).toEqual([
      {
        categories: ['acteur', 'mannequin'],
        niches: ['beaute'],
      },
    ])
  })

  it('emits save event with null for empty selections', async () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        categoryNicheInfo: {
          categories: [],
          niches: [],
        },
      },
    })

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.emitted('save')).toBeTruthy()
    expect(wrapper.emitted('save')![0]).toEqual([
      {
        categories: null,
        niches: null,
      },
    ])
  })

  it('renders save button disabled when saving', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        isSaving: true,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')

    expect(saveButton.attributes('disabled')).toBeDefined()
    expect(saveButton.text()).toContain('Enregistrement...')
  })

  it('renders error message when error is provided', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        error: "La catégorie sélectionnée n'est pas valide",
      },
    })

    const errorMessage = wrapper.find('[data-testid="error-message"]')

    expect(errorMessage.exists()).toBe(true)
    expect(errorMessage.text()).toContain("La catégorie sélectionnée n'est pas valide")
  })

  it('shows loading spinner when saving', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        isSaving: true,
      },
    })

    const saveButton = wrapper.find('[data-testid="save-button"]')
    const spinner = saveButton.find('svg.animate-spin')

    expect(spinner.exists()).toBe(true)
  })

  it('updates form when categoryNicheInfo changes', async () => {
    const wrapper = mount(CategoryNicheForm, {
      props: defaultProps,
    })

    await wrapper.setProps({
      categoryNicheInfo: mockCategoryNicheInfo,
    })

    // acteur and mannequin should now be selected
    expect(wrapper.find('[data-testid="category-chip-acteur"]').classes()).toContain('bg-teal-600')
    expect(wrapper.find('[data-testid="category-chip-mannequin"]').classes()).toContain('bg-teal-600')
    expect(wrapper.find('[data-testid="niche-chip-beaute"]').classes()).toContain('bg-purple-600')
  })
})
