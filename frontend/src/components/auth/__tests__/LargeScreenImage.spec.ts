import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import LargeScreenImage from '../LargeScreenImage.vue'

describe('LargeScreenImage', () => {
  it('ne référence l\'illustration que dans une source réservée aux écrans lg', () => {
    const wrapper = mount(LargeScreenImage, {
      props: { src: '/img/illustration.webp', alt: 'Illustration' },
      attrs: { class: 'h-full w-full object-cover' },
    })

    const source = wrapper.find('picture > source')
    expect(source.attributes('media')).toBe('(min-width: 1024px)')
    expect(source.attributes('srcset')).toBe('/img/illustration.webp')

    const img = wrapper.find('picture > img')
    expect(img.attributes('src')).toMatch(/^data:image\/gif;base64,/)
    expect(img.attributes('src')).not.toContain('illustration')
    expect(img.attributes('alt')).toBe('Illustration')
    expect(img.classes()).toContain('object-cover')
    expect(wrapper.find('picture').classes()).not.toContain('object-cover')
  })
})
