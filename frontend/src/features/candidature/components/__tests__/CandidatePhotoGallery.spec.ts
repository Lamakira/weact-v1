import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CandidatePhotoGallery from '../CandidatePhotoGallery.vue'
import type { FacePhoto } from '../../types'

function makePhoto(id: number, position: number): FacePhoto {
  return {
    id: String(id),
    photo_url: `http://localhost/storage/albums/photo${id}.jpg`,
    grid_url: `http://localhost/storage/albums/grid/photo${id}.webp`,
    large_url: `http://localhost/storage/albums/large/photo${id}.webp`,
    thumbnail_url: `http://localhost/storage/albums/thumbnails/photo${id}.jpg`,
    position,
  }
}

const photos = [makePhoto(1, 1), makePhoto(2, 2)]

function mountGallery(photoList: FacePhoto[] = photos) {
  return mount(CandidatePhotoGallery, {
    props: { photos: photoList },
    global: { stubs: { teleport: true } },
  })
}

describe('CandidatePhotoGallery', () => {
  it('renders the empty state without photos', () => {
    const wrapper = mountGallery([])
    expect(wrapper.text()).toContain('Aucune photo dans le portfolio')
  })

  it('renders grid thumbnails with the grid variant, lazily loaded', () => {
    const wrapper = mountGallery()
    const images = wrapper.findAll('.grid img')

    expect(images).toHaveLength(2)
    expect(images[0]!.attributes('src')).toBe(photos[0]!.grid_url)
    expect(images[0]!.attributes('loading')).toBe('lazy')
  })

  it('falls back to the original in the grid when grid_url is empty (legacy payload)', () => {
    const legacy = { ...makePhoto(1, 1), grid_url: '' }
    const wrapper = mountGallery([legacy])

    expect(wrapper.find('.grid img').attributes('src')).toBe(legacy.photo_url)
  })

  it('opens the lightbox on the large variant, never the original by default', async () => {
    const wrapper = mountGallery()

    await wrapper.findAll('.grid button')[0]!.trigger('click')

    const lightboxImg = wrapper.find('[data-testid="lightbox-image"]')
    expect(lightboxImg.exists()).toBe(true)
    expect(lightboxImg.attributes('src')).toBe(photos[0]!.large_url)
  })

  it('swaps to the original only after clicking « Voir l\'original »', async () => {
    const wrapper = mountGallery()

    await wrapper.findAll('.grid button')[0]!.trigger('click')

    const button = wrapper.find('[data-testid="lightbox-view-original"]')
    expect(button.exists()).toBe(true)
    expect(button.text()).toBe("Voir l'original")

    await button.trigger('click')

    expect(wrapper.find('[data-testid="lightbox-image"]').attributes('src')).toBe(
      photos[0]!.photo_url
    )
    expect(wrapper.find('[data-testid="lightbox-view-original"]').exists()).toBe(false)
  })

  it('resets to the large variant when navigating to another photo', async () => {
    const wrapper = mountGallery()

    await wrapper.findAll('.grid button')[0]!.trigger('click')
    await wrapper.find('[data-testid="lightbox-view-original"]').trigger('click')

    // Navigate (« next » arrow, h-12 right-4): the HQ toggle must not leak
    // onto the next photo
    await wrapper.find('button.h-12.right-4').trigger('click')

    expect(wrapper.find('[data-testid="lightbox-image"]').attributes('src')).toBe(
      photos[1]!.large_url
    )
    expect(wrapper.find('[data-testid="lightbox-view-original"]').exists()).toBe(true)
  })

  it('hides « Voir l\'original » when the large variant already is the original (legacy)', async () => {
    const legacy = { ...makePhoto(1, 1), large_url: makePhoto(1, 1).photo_url }
    const wrapper = mountGallery([legacy])

    await wrapper.findAll('.grid button')[0]!.trigger('click')

    expect(wrapper.find('[data-testid="lightbox-image"]').attributes('src')).toBe(legacy.photo_url)
    expect(wrapper.find('[data-testid="lightbox-view-original"]').exists()).toBe(false)
  })

  it('hides « Voir l\'original » when large_url is missing (legacy payload)', async () => {
    const legacy = { ...makePhoto(1, 1), large_url: null }
    const wrapper = mountGallery([legacy])

    await wrapper.findAll('.grid button')[0]!.trigger('click')

    expect(wrapper.find('[data-testid="lightbox-image"]').attributes('src')).toBe(legacy.photo_url)
    expect(wrapper.find('[data-testid="lightbox-view-original"]').exists()).toBe(false)
  })
})
