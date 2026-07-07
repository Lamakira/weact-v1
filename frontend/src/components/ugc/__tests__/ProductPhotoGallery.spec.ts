import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProductPhotoGallery from '../ProductPhotoGallery.vue'
import type { ProductPhoto } from '../ugc'

function makePhoto(overrides: Partial<ProductPhoto> = {}): ProductPhoto {
  return {
    id: 'pp-1',
    position: 1,
    photo_url: 'http://x/products/original.jpg',
    grid_url: 'http://x/products/grid/photo.webp',
    large_url: 'http://x/products/large/photo.webp',
    // Spread (pas de ??) : un override `null` explicite doit être conservé
    // (cas « variantes pas encore générées »).
    ...overrides,
  }
}

function mountGallery(photos: ProductPhoto[], title?: string) {
  return mount(ProductPhotoGallery, {
    props: { photos, title },
    global: { stubs: { teleport: true } },
  })
}

describe('ProductPhotoGallery', () => {
  it('renders nothing at all without photos (bookings/missions pré-deploy)', () => {
    const wrapper = mountGallery([])

    expect(wrapper.find('[data-testid="product-photo-gallery"]').exists()).toBe(false)
    expect(wrapper.text()).toBe('')
  })

  it('renders one grid thumbnail per photo', () => {
    const wrapper = mountGallery([makePhoto(), makePhoto({ id: 'pp-2', position: 2 })])

    const thumbs = wrapper.findAll('[data-testid="product-photo-thumb"] img')
    expect(thumbs).toHaveLength(2)
    expect(thumbs[0]!.attributes('src')).toBe('http://x/products/grid/photo.webp')
  })

  it('falls back to the original when grid_url is null (variantes pas encore générées)', () => {
    const wrapper = mountGallery([makePhoto({ grid_url: null })])

    expect(wrapper.find('[data-testid="product-photo-thumb"] img').attributes('src')).toBe(
      'http://x/products/original.jpg',
    )
  })

  it('opens the lightbox on the large variant with « Voir l\'original » available', async () => {
    const wrapper = mountGallery([makePhoto()])

    await wrapper.find('[data-testid="product-photo-thumb"]').trigger('click')

    expect(wrapper.find('[data-testid="product-lightbox-image"]').attributes('src')).toBe(
      'http://x/products/large/photo.webp',
    )
    expect(wrapper.find('[data-testid="product-lightbox-view-original"]').exists()).toBe(true)
  })

  it('switches to the original after clicking « Voir l\'original »', async () => {
    const wrapper = mountGallery([makePhoto()])

    await wrapper.find('[data-testid="product-photo-thumb"]').trigger('click')
    await wrapper.find('[data-testid="product-lightbox-view-original"]').trigger('click')

    expect(wrapper.find('[data-testid="product-lightbox-image"]').attributes('src')).toBe(
      'http://x/products/original.jpg',
    )
    expect(wrapper.find('[data-testid="product-lightbox-view-original"]').exists()).toBe(false)
  })

  it('hides « Voir l\'original » when large_url already equals the original', async () => {
    const wrapper = mountGallery([
      makePhoto({ large_url: 'http://x/products/original.jpg' }),
    ])

    await wrapper.find('[data-testid="product-photo-thumb"]').trigger('click')

    expect(wrapper.find('[data-testid="product-lightbox-view-original"]').exists()).toBe(false)
  })

  it('resets the HQ state when navigating between photos', async () => {
    const wrapper = mountGallery([makePhoto(), makePhoto({ id: 'pp-2', position: 2 })])

    await wrapper.find('[data-testid="product-photo-thumb"]').trigger('click')
    await wrapper.find('[data-testid="product-lightbox-view-original"]').trigger('click')
    // Navigation → retour à la variante large de la photo suivante.
    await wrapper.find('[aria-label="Photo suivante"]').trigger('click')

    expect(wrapper.find('[data-testid="product-lightbox-image"]').attributes('src')).toBe(
      'http://x/products/large/photo.webp',
    )
    expect(wrapper.find('[data-testid="product-lightbox-view-original"]').exists()).toBe(true)
  })

  it('renders the optional title', () => {
    const wrapper = mountGallery([makePhoto()], 'Photos du produit')

    expect(wrapper.text()).toContain('Photos du produit')
  })
})
