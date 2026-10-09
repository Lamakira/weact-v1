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
})
