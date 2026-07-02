import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfileInfoSection from '../ProfileInfoSection.vue'
import WBadge from '@/components/ui/WBadge.vue'

describe('ProfileInfoSection', () => {
  const defaultProps = {
    prenom: 'Adjoua',
    ville: 'Cotonou',
    categories: [{ value: 'acteur', label: 'Acteur' }],
    niches: [{ value: 'publicite', label: 'Publicité' }],
    averageRating: 4.5,
    ratingsCount: 12,
    tarifHoraire: null,
    tarifJournalier: null,
  }

  it('renders the component', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a :href="to"><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    expect(wrapper.find('[data-testid="profile-info-section"]').exists()).toBe(true)
  })

  it('displays the prenom as heading', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    const heading = wrapper.find('[data-testid="face-prenom"]')
    expect(heading.exists()).toBe(true)
    expect(heading.text()).toBe('Adjoua')
    expect(heading.element.tagName).toBe('H1')
  })

  it('displays ville when provided', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    const villeElement = wrapper.find('[data-testid="face-ville"]')
    expect(villeElement.exists()).toBe(true)
    expect(villeElement.text()).toBe('Cotonou')
  })

  it('hides ville when null', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: { ...defaultProps, ville: null },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    expect(wrapper.find('[data-testid="face-ville"]').exists()).toBe(false)
  })

  it('displays categorie badge', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    const badge = wrapper.find('[data-testid="categorie-badge"]')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toBe('Acteur')
    expect(badge.classes()).toContain('bg-[#198496]/10')
    expect(badge.classes()).toContain('text-[#198496]')
  })

  it('displays niche badge when provided', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    const badge = wrapper.find('[data-testid="niche-badge"]')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toBe('Publicité')
  })

  it('hides niche badge when niches array is empty', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: { ...defaultProps, niches: [] },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    expect(wrapper.find('[data-testid="niche-badge"]').exists()).toBe(false)
  })

  it('displays rating section with correct values', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    const ratingSection = wrapper.find('[data-testid="rating-section"]')
    expect(ratingSection.exists()).toBe(true)
    expect(ratingSection.text()).toContain('4.5')
    expect(ratingSection.text()).toContain('(12 avis)')
  })

  it('displays 0.0 rating when averageRating is null', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: { ...defaultProps, averageRating: null, ratingsCount: 0 },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    expect(wrapper.text()).toContain('0.0')
    expect(wrapper.text()).toContain('(0 avis)')
  })

  it('shows pricing section when tarif props are provided', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: { ...defaultProps, tarifHoraire: '25 000 XOF', tarifJournalier: '50 000 XOF' },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    const pricingSection = wrapper.find('[data-testid="tarifs-section"]')
    expect(pricingSection.exists()).toBe(true)
    expect(pricingSection.text()).toContain('25 000 XOF')
    expect(pricingSection.text()).toContain('50 000 XOF')
  })

  it('hides pricing section when no tarif props are provided', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    expect(wrapper.find('[data-testid="tarifs-section"]').exists()).toBe(false)
  })

  it('does not render legacy producer CTA inside the info section', () => {
    const wrapper = mount(ProfileInfoSection, { props: defaultProps })

    expect(wrapper.find('[data-testid="producer-cta"]').exists()).toBe(false)
  })

  it('does not display sensitive information', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    // Ensure component does NOT have fields for sensitive data
    // These should never be passed as props or displayed
    expect(wrapper.text()).not.toContain('nom')
    expect(wrapper.text()).not.toContain('bio')
    expect(wrapper.text()).not.toContain('tarif')
    expect(wrapper.text()).not.toContain('taille')
    expect(wrapper.text()).not.toContain('poids')
    expect(wrapper.text()).not.toContain('email')
    expect(wrapper.text()).not.toContain('@')
  })

  it('has proper accessibility attributes for rating', () => {
    const wrapper = mount(ProfileInfoSection, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    const ratingSection = wrapper.find('[data-testid="rating-section"]')
    expect(ratingSection.attributes('aria-label')).toContain('4.5')
    expect(ratingSection.attributes('aria-label')).toContain('étoiles')
  })

  describe('FP-2.12 — Élite badge (WBadge V13 design refresh)', () => {
    const baseProps = {
      prenom: 'Adjoua',
      ville: 'Cotonou',
      categories: [{ value: 'acteur', label: 'Acteur' }],
      niches: [],
      averageRating: 4.5,
      ratingsCount: 12,
    }

    it('renders the WBadge in elite tier at 22px when hasEliteBadge prop is true', () => {
      const wrapper = mount(ProfileInfoSection, {
        props: { ...baseProps, hasEliteBadge: true },
      })
      const badge = wrapper.findComponent(WBadge)
      expect(badge.exists()).toBe(true)
      expect(badge.props('tier')).toBe('elite')
      expect(badge.props('size')).toBe(22)
    })

    it('does not render the Élite badge when hasEliteBadge prop is false', () => {
      const wrapper = mount(ProfileInfoSection, {
        props: { ...baseProps, hasEliteBadge: false },
      })
      expect(wrapper.findComponent(WBadge).exists()).toBe(false)
    })

    it('does not render the Élite badge when hasEliteBadge prop is undefined (default false)', () => {
      const wrapper = mount(ProfileInfoSection, { props: baseProps })
      expect(wrapper.findComponent(WBadge).exists()).toBe(false)
    })
  })
})
