import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import CategoryNicheForm from '../CategoryNicheForm.vue'
import type { CategoryNicheInfo, CategoryOption, NicheOption } from '../../types'

describe('CategoryNicheForm', () => {
  const mockCategoryNicheInfo: CategoryNicheInfo = {
    categorie: 'acteur',
    categorie_label: 'Acteur',
    niche: 'beaute',
    niche_label: 'Beauté',
  }

  const mockCategoryOptions: CategoryOption[] = [
    { value: 'acteur', label: 'Acteur' },
    { value: 'influenceur', label: 'Influenceur' },
    { value: 'createur', label: 'Créateur de contenu' },
    { value: 'mannequin', label: 'Mannequin' },
    { value: 'figurant', label: 'Figurant' },
  ]

  const mockNicheOptions: NicheOption[] = [
    { value: 'beaute', label: 'Beauté' },
    { value: 'nourriture', label: 'Nourriture' },
    { value: 'decouverte', label: 'Découverte' },
    { value: 'mode', label: 'Mode' },
  ]

  const defaultProps = {
    categoryNicheInfo: null,
    categoryOptions: mockCategoryOptions,
    nicheOptions: mockNicheOptions,
    isSaving: false,
    error: null,
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders form with initial values', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        categoryNicheInfo: mockCategoryNicheInfo,
      },
    })

    const categorieSelect = wrapper.find('[data-testid="categorie-select"]')
    const nicheSelect = wrapper.find('[data-testid="niche-select"]')

    expect((categorieSelect.element as HTMLSelectElement).value).toBe('acteur')
    expect((nicheSelect.element as HTMLSelectElement).value).toBe('beaute')
  })

  it('renders form with null values', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        categoryNicheInfo: {
          categorie: null,
          categorie_label: null,
          niche: null,
          niche_label: null,
        },
      },
    })

    const categorieSelect = wrapper.find('[data-testid="categorie-select"]')
    const nicheSelect = wrapper.find('[data-testid="niche-select"]')

    expect((categorieSelect.element as HTMLSelectElement).value).toBe('')
    expect((nicheSelect.element as HTMLSelectElement).value).toBe('')
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

  it('emits save event with form data', async () => {
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
        categorie: 'acteur',
        niche: 'beaute',
      },
    ])
  })

  it('emits save event with null for empty values', async () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        categoryNicheInfo: null,
      },
    })

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.emitted('save')).toBeTruthy()
    expect(wrapper.emitted('save')![0]).toEqual([
      {
        categorie: null,
        niche: null,
      },
    ])
  })

  it('renders dropdown options correctly', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: defaultProps,
    })

    const categorieSelect = wrapper.find('[data-testid="categorie-select"]')
    const nicheSelect = wrapper.find('[data-testid="niche-select"]')

    const categorieOptions = categorieSelect.findAll('option')
    const nicheOptionsElements = nicheSelect.findAll('option')

    // +1 for the placeholder option
    expect(categorieOptions).toHaveLength(mockCategoryOptions.length + 1)
    expect(nicheOptionsElements).toHaveLength(mockNicheOptions.length + 1)

    // Check placeholder
    expect(categorieOptions[0].text()).toBe('Sélectionnez une catégorie')
    expect(nicheOptionsElements[0].text()).toBe('Sélectionnez une niche')

    // Check category options
    expect(categorieOptions[1].text()).toBe('Acteur')
    expect(categorieOptions[2].text()).toBe('Influenceur')

    // Check niche options
    expect(nicheOptionsElements[1].text()).toBe('Beauté')
    expect(nicheOptionsElements[2].text()).toBe('Nourriture')
  })

  it('displays current selection badges when values exist', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        categoryNicheInfo: mockCategoryNicheInfo,
      },
    })

    const currentSelection = wrapper.find('[data-testid="current-selection"]')
    const categoryBadge = wrapper.find('[data-testid="category-badge"]')
    const nicheBadge = wrapper.find('[data-testid="niche-badge"]')

    expect(currentSelection.exists()).toBe(true)
    expect(categoryBadge.text()).toBe('Acteur')
    expect(nicheBadge.text()).toBe('Beauté')
  })

  it('does not display badges when values are null', () => {
    const wrapper = mount(CategoryNicheForm, {
      props: {
        ...defaultProps,
        categoryNicheInfo: null,
      },
    })

    const currentSelection = wrapper.find('[data-testid="current-selection"]')

    expect(currentSelection.exists()).toBe(false)
  })

  it('updates form when categoryNicheInfo changes', async () => {
    const wrapper = mount(CategoryNicheForm, {
      props: defaultProps,
    })

    await wrapper.setProps({
      categoryNicheInfo: mockCategoryNicheInfo,
    })

    const categorieSelect = wrapper.find('[data-testid="categorie-select"]')
    const nicheSelect = wrapper.find('[data-testid="niche-select"]')

    expect((categorieSelect.element as HTMLSelectElement).value).toBe('acteur')
    expect((nicheSelect.element as HTMLSelectElement).value).toBe('beaute')
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
})
