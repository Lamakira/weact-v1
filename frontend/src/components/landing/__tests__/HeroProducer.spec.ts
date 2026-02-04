import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { RouterLinkStub } from '@vue/test-utils'
import HeroProducer from '../HeroProducer.vue'

describe('HeroProducer', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders producer-focused hero title', () => {
    const wrapper = mount(HeroProducer, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    expect(wrapper.text()).toContain('Trouvez votre prochain')
  })

  it('renders producer subtext', () => {
    const wrapper = mount(HeroProducer, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    expect(wrapper.text()).toContain('Accédez au plus grand catalogue de talents au Bénin')
  })

  it('renders CTA linking to producer registration', () => {
    const wrapper = mount(HeroProducer, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    const cta = wrapper.findComponent(RouterLinkStub)
    expect(cta.props('to')).toBe('/register/producer')
    expect(wrapper.text()).toContain('Publier une mission')
  })

  it('displays animated words for producer perspective', () => {
    const wrapper = mount(HeroProducer, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    const animatedWord = wrapper.find('[data-testid="hero-animated-word"]')
    expect(animatedWord.exists()).toBe(true)

    // Initial word should be one of the producer words
    const producerWords = ['acteur', 'créateur de contenu', 'influenceur', 'modèle photo']
    const currentWord = animatedWord.text()
    expect(producerWords).toContain(currentWord)
  })

  it('cycles through animated words every 2.5 seconds', async () => {
    const wrapper = mount(HeroProducer, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    const animatedWord = wrapper.find('[data-testid="hero-animated-word"]')
    const initialWord = animatedWord.text()

    // Advance time by 2.5 seconds + buffer and run all timers
    await vi.advanceTimersByTimeAsync(2600)
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()

    // Word should have changed
    const newWord = wrapper.find('[data-testid="hero-animated-word"]').text()
    expect(newWord).not.toBe(initialWord)
  })

  it('renders orbiting talent faces on desktop', () => {
    const wrapper = mount(HeroProducer, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    // Check for orbiting faces with floating-face class
    const floatingFaces = wrapper.findAll('.floating-face')
    expect(floatingFaces.length).toBeGreaterThan(0)
  })

  it('renders mobile talent indicators', () => {
    const wrapper = mount(HeroProducer, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    // Check for mobile talent indicators (5 overlapping faces)
    const mobileIndicators = wrapper.findAll('.lg\\:hidden img')
    expect(mobileIndicators.length).toBe(5)
  })

  it('has hidden silhouette element for testid compatibility', () => {
    const wrapper = mount(HeroProducer, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    const silhouette = wrapper.find('[data-testid="hero-silhouette"]')
    expect(silhouette.exists()).toBe(true)
  })

  it('has proper accessibility for animated text', () => {
    const wrapper = mount(HeroProducer, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    const animatedWord = wrapper.find('[data-testid="hero-animated-word"]')
    expect(animatedWord.attributes('aria-live')).toBe('polite')
    expect(animatedWord.attributes('aria-atomic')).toBe('true')
  })
})
