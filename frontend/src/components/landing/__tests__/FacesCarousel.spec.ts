import { describe, it, expect } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import FacesCarousel from '../FacesCarousel.vue'
import type { LandingFace } from '@/features/landing/types'

function makeFace(overrides: Partial<LandingFace> = {}): LandingFace {
  return {
    id: '1',
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

function mountComponent(profiles: LandingFace[] = [makeFace()]) {
  return mount(FacesCarousel, {
    props: { profiles },
    global: {
      stubs: { RouterLink: RouterLinkStub },
    },
  })
}

describe('FacesCarousel', () => {
  it('renders profile cards in the marquee', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('[data-testid="faces-carousel"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Alice')
  })

  it('prefers the grid variant over the original when available', () => {
    const wrapper = mountComponent([
      makeFace({ profile_photo_grid_url: 'https://example.com/grid.webp' }),
    ])

    const images = wrapper.findAll('[data-testid="faces-carousel"] img')
    expect(images.length).toBeGreaterThan(0)
    images.forEach((img) => {
      expect(img.attributes('src')).toBe('https://example.com/grid.webp')
    })
  })

  it('falls back to the original for legacy payloads without grid', () => {
    const wrapper = mountComponent([makeFace({ profile_photo_grid_url: null })])

    const images = wrapper.findAll('[data-testid="faces-carousel"] img')
    expect(images.length).toBeGreaterThan(0)
    images.forEach((img) => {
      expect(img.attributes('src')).toBe('https://example.com/photo.jpg')
    })
  })

  it('lazy loads carousel images', () => {
    const wrapper = mountComponent()
    const images = wrapper.findAll('[data-testid="faces-carousel"] img')
    images.forEach((img) => {
      expect(img.attributes('loading')).toBe('lazy')
    })
  })
})
