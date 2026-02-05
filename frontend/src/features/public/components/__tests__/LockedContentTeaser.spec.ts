import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import LockedContentTeaser from '../LockedContentTeaser.vue'
import { Video } from 'lucide-vue-next'

describe('LockedContentTeaser', () => {
  const defaultProps = {
    title: 'Vidéos disponibles',
    description: 'Inscrivez-vous pour voir les vidéos',
    ctaText: 'Créer un compte',
    ctaLink: '/register/producer',
  }

  it('renders the component with all props', () => {
    const wrapper = mount(LockedContentTeaser, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a :href="to" data-testid="locked-content-cta"><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    expect(wrapper.find('[data-testid="locked-content-teaser"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="locked-content-title"]').text()).toBe(
      'Vidéos disponibles'
    )
    expect(wrapper.find('[data-testid="locked-content-description"]').text()).toBe(
      'Inscrivez-vous pour voir les vidéos'
    )
    expect(wrapper.find('[data-testid="locked-content-cta"]').text()).toBe('Créer un compte')
  })

  it('renders without description when not provided', () => {
    const wrapper = mount(LockedContentTeaser, {
      props: {
        title: 'Test Title',
        ctaText: 'CTA Button',
        ctaLink: '/test',
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a :href="to"><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    expect(wrapper.find('[data-testid="locked-content-description"]').exists()).toBe(false)
  })

  it('links to the correct registration page', () => {
    const wrapper = mount(LockedContentTeaser, {
      props: defaultProps,
      global: {
        stubs: {
          RouterLink: {
            template: '<a :href="to" data-testid="locked-content-cta"><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    const link = wrapper.find('[data-testid="locked-content-cta"]')
    expect(link.attributes('href')).toBe('/register/producer')
  })

  it('renders custom icon when provided', () => {
    const wrapper = mount(LockedContentTeaser, {
      props: {
        ...defaultProps,
        icon: Video,
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    // Component should render the custom Video icon
    expect(wrapper.html()).toContain('lucide')
  })

  it('has proper accessibility attributes', () => {
    const wrapper = mount(LockedContentTeaser, {
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

    // Icon container should be hidden from screen readers
    const iconWrapper = wrapper.find('[aria-hidden="true"]')
    expect(iconWrapper.exists()).toBe(true)
  })
})
