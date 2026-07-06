import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { RouterLinkStub } from '@vue/test-utils'
import TalentsShowcase from '../TalentsShowcase.vue'
import type { LandingFace } from '@/features/landing/types'

function makeFace(overrides: Partial<LandingFace> = {}): LandingFace {
  return {
    id: 1,
    username: 'face1',
    prenom: 'Alice',
    nom: 'Dupont',
    ville: 'Cotonou',
    categories: [{ value: 'acteur', label: 'Acteur' }],
    is_available: true,
    profile_photo_url: 'https://example.com/photo.jpg',
    profile_photo_grid_url: null,
    profile_photo_thumbnail_url: 'https://example.com/thumb.jpg',
    average_rating: 4.0,
    ...overrides,
  }
}

const sampleTalents: LandingFace[] = [
  makeFace({ id: 1, prenom: 'Alice', categories: [{ value: 'acteur', label: 'Actrice' }] }),
  makeFace({ id: 2, prenom: 'Bob', categories: [{ value: 'influenceur', label: 'Influenceur' }] }),
  makeFace({ id: 3, prenom: 'Charlie', categories: [{ value: 'mannequin', label: 'Mannequin' }] }),
  makeFace({ id: 4, prenom: 'Diane', categories: [{ value: 'createur', label: 'Créateur' }] }),
  makeFace({ id: 5, prenom: 'Eve', categories: [{ value: 'figurant', label: 'Figurant' }] }),
  makeFace({ id: 6, prenom: 'Frank', categories: [{ value: 'acteur', label: 'Acteur' }] }),
]

function mountComponent(props: { talents: LandingFace[]; totalCount?: number } = { talents: sampleTalents }) {
  return mount(TalentsShowcase, {
    props,
    global: {
      stubs: {
        RouterLink: RouterLinkStub,
      },
    },
  })
}

describe('TalentsShowcase', () => {
  it('renders section title', () => {
    const wrapper = mountComponent()
    expect(wrapper.text()).toContain('Découvrez nos talents')
  })

  it('renders talent cards (original + duplicates for infinite loop)', () => {
    const wrapper = mountComponent()
    const talentCards = wrapper.findAll('[data-testid^="talent-card-"]')
    // 6 originals + 6 duplicates = 12
    expect(talentCards.length).toBe(sampleTalents.length * 2)
  })

  it('renders talent name and category for each card', () => {
    const wrapper = mountComponent()
    const firstCard = wrapper.find('[data-testid="talent-card-1"]')
    expect(firstCard.exists()).toBe(true)
    expect(firstCard.text()).toContain('Alice')
    expect(firstCard.text()).toContain('Actrice')
  })

  it('renders talent images', () => {
    const wrapper = mountComponent()
    const images = wrapper.findAll('[data-testid^="talent-card-"] img')
    expect(images.length).toBe(sampleTalents.length * 2)
    images.forEach((img) => {
      expect(img.attributes('src')).toBeTruthy()
      expect(img.attributes('alt')).toBeTruthy()
    })
  })

  it('prefers the grid variant over the original when available', () => {
    const wrapper = mountComponent({
      talents: [makeFace({ id: 1, profile_photo_grid_url: 'https://example.com/grid.webp' })],
    })
    const img = wrapper.find('[data-testid="talent-card-1"] img')
    expect(img.attributes('src')).toBe('https://example.com/grid.webp')
  })

  it('falls back to the original for legacy payloads without grid', () => {
    const wrapper = mountComponent({
      talents: [makeFace({ id: 1, profile_photo_grid_url: null })],
    })
    const img = wrapper.find('[data-testid="talent-card-1"] img')
    expect(img.attributes('src')).toBe('https://example.com/photo.jpg')
  })

  it('lazy loads talent images', () => {
    const wrapper = mountComponent()
    const images = wrapper.findAll('[data-testid^="talent-card-"] img')
    images.forEach((img) => {
      expect(img.attributes('loading')).toBe('lazy')
    })
  })

  it('renders CTA linking to faces list', () => {
    const wrapper = mountComponent()
    const cta = wrapper.findComponent(RouterLinkStub)
    expect(cta.exists()).toBe(true)
    expect(cta.props('to')).toBe('/faces')
    expect(wrapper.text()).toContain('Explorer tous les talents')
  })

  it('has carousel container', () => {
    const wrapper = mountComponent()
    const carousel = wrapper.find('[data-testid="talents-carousel"]')
    expect(carousel.exists()).toBe(true)
    const classes = carousel.classes().join(' ')
    expect(classes).toContain('overflow-hidden')
  })

  it('renders category badges on cards', () => {
    const wrapper = mountComponent()
    const categories = ['Actrice', 'Influenceur', 'Mannequin', 'Créateur', 'Figurant', 'Acteur']
    const text = wrapper.text()
    const hasAtLeastOneCategory = categories.some((cat) => text.includes(cat))
    expect(hasAtLeastOneCategory).toBe(true)
  })

  it('has hover effect classes on cards', () => {
    const wrapper = mountComponent()
    const firstCard = wrapper.find('[data-testid="talent-card-1"]')
    const classes = firstCard.classes().join(' ')
    expect(classes).toContain('transition')
  })

  it('shows count display when totalCount > 20', () => {
    const wrapper = mountComponent({ talents: sampleTalents, totalCount: 50 })
    expect(wrapper.find('[data-testid="talents-count"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('+50 talents disponibles sur la plateforme')
  })

  it('hides count display when totalCount <= 20', () => {
    const wrapper = mountComponent({ talents: sampleTalents, totalCount: 15 })
    expect(wrapper.find('[data-testid="talents-count"]').exists()).toBe(false)
  })
})
