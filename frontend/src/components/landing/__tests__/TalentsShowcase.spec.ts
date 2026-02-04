import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { RouterLinkStub } from '@vue/test-utils'
import TalentsShowcase from '../TalentsShowcase.vue'

describe('TalentsShowcase', () => {
  it('renders section title', () => {
    const wrapper = mount(TalentsShowcase, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    expect(wrapper.text()).toContain('Découvrez nos talents')
  })

  it('renders grid of talent cards', () => {
    const wrapper = mount(TalentsShowcase, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    const talentCards = wrapper.findAll('[data-testid^="talent-card-"]')
    expect(talentCards.length).toBeGreaterThanOrEqual(6)
  })

  it('renders talent name and category for each card', () => {
    const wrapper = mount(TalentsShowcase, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    // Check first card has name and category
    const firstCard = wrapper.find('[data-testid="talent-card-1"]')
    expect(firstCard.exists()).toBe(true)
    expect(firstCard.text()).toBeTruthy()
  })

  it('renders talent images', () => {
    const wrapper = mount(TalentsShowcase, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    const images = wrapper.findAll('[data-testid^="talent-card-"] img')
    expect(images.length).toBeGreaterThanOrEqual(6)
    images.forEach((img) => {
      expect(img.attributes('src')).toBeTruthy()
      expect(img.attributes('alt')).toBeTruthy()
    })
  })

  it('renders CTA linking to faces list', () => {
    const wrapper = mount(TalentsShowcase, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    const cta = wrapper.findComponent(RouterLinkStub)
    expect(cta.exists()).toBe(true)
    expect(cta.props('to')).toBe('/faces')
    expect(wrapper.text()).toContain('Explorer tous les talents')
  })

  it('has carousel container', () => {
    const wrapper = mount(TalentsShowcase, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    const carousel = wrapper.find('[data-testid="talents-carousel"]')
    expect(carousel.exists()).toBe(true)
    // Check for overflow hidden for carousel effect
    const classes = carousel.classes().join(' ')
    expect(classes).toContain('overflow-hidden')
  })

  it('renders category badges on cards', () => {
    const wrapper = mount(TalentsShowcase, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    // Categories should be visible
    const categories = ['Actrice', 'Influenceur', 'Mannequin', 'Créateur', 'Figurant', 'Acteur']
    const text = wrapper.text()
    const hasAtLeastOneCategory = categories.some((cat) => text.includes(cat))
    expect(hasAtLeastOneCategory).toBe(true)
  })

  it('has hover effect classes on cards', () => {
    const wrapper = mount(TalentsShowcase, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    const firstCard = wrapper.find('[data-testid="talent-card-1"]')
    const classes = firstCard.classes().join(' ')
    expect(classes).toContain('transition')
  })
})
