import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import HeroFace from '../HeroFace.vue'

describe('HeroFace - image LCP', () => {
  it('annonce la photo portrait comme prioritaire avec ses dimensions', () => {
    const wrapper = mount(HeroFace, {
      global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
    })

    const img = wrapper.find('.lg\\:hidden img')
    expect(img.attributes('fetchpriority')).toBe('high')
    expect(img.attributes('decoding')).toBe('async')
    expect(img.attributes('width')).toBe('640')
    expect(img.attributes('height')).toBe('1046')
    expect(img.attributes('loading')).toBe('eager')
  })

  it('propose des versions 960 et 1242 px pour les écrans haute densité', () => {
    const wrapper = mount(HeroFace, {
      global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
    })

    const portraits = wrapper.findAll('[data-testid="hero-images"] img[alt="Femme souriante en studio"]')
    expect(portraits).toHaveLength(2)

    for (const img of portraits) {
      const srcset = img.attributes('srcset') ?? ''
      expect(srcset).toContain('640w')
      expect(srcset).toContain('960w')
      expect(srcset).toContain('1242w')
      expect(img.attributes('sizes')).toBeTruthy()
    }
  })
})
