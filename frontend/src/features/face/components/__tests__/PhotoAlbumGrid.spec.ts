import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PhotoAlbumGrid from '../PhotoAlbumGrid.vue'
import type { FacePhoto } from '../../types'

function makePhoto(id: number, position: number): FacePhoto {
  // Round 2 P8 — `FacePhoto.id` is typed `string` in `types.ts`; the previous
  // shorthand `id,` assigned a number, drifting the fixture from the contract.
  return {
    id: String(id),
    photo_url: `http://localhost/storage/albums/photo${id}.jpg`,
    thumbnail_url: `http://localhost/storage/albums/thumbnails/photo${id}.jpg`,
    position,
  }
}

describe('PhotoAlbumGrid (FP-2.7 tier-aware)', () => {
  const photo1 = makePhoto(1, 1)
  const photo2 = makePhoto(2, 2)

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders maxAlbumPhotos empty slots when the album is empty', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [], maxAlbumPhotos: 4 },
    })

    expect(wrapper.find('[data-testid="photo-album-grid"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="album-slot-"]')).toHaveLength(4)
    expect(wrapper.findAll('[data-testid^="add-photo-slot-"]')).toHaveLength(4)
  })

  it('renders photos plus filler slots up to the tier quota', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [photo1, photo2], maxAlbumPhotos: 4 },
    })

    expect(wrapper.find(`[data-testid="album-photo-${photo1.id}"]`).exists()).toBe(true)
    expect(wrapper.find(`[data-testid="album-photo-${photo2.id}"]`).exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="album-slot-"]')).toHaveLength(4)
    expect(wrapper.findAll('[data-testid^="add-photo-slot-"]')).toHaveLength(2)
  })

  it('shows the delete button overlay on photos', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [photo1], maxAlbumPhotos: 4 },
    })
    expect(wrapper.find(`[data-testid="delete-photo-${photo1.id}"]`).exists()).toBe(true)
  })

  it('emits delete when a delete button is clicked', async () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [photo1], maxAlbumPhotos: 4 },
    })

    await wrapper.find(`[data-testid="delete-photo-${photo1.id}"]`).trigger('click')

    expect(wrapper.emitted('delete')?.[0]).toEqual([photo1.id])
  })

  it('emits add-click when an empty slot is clicked', async () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [], maxAlbumPhotos: 4, canAddMore: true },
    })

    await wrapper.find('[data-testid="add-photo-slot-0"]').trigger('click')

    expect(wrapper.emitted('add-click')).toBeTruthy()
  })

  it('shows disabled placeholders instead of add buttons when canAddMore is false', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [photo1], maxAlbumPhotos: 4, canAddMore: false },
    })

    expect(wrapper.findAll('[data-testid^="add-photo-slot-"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid^="empty-slot-"]')).toHaveLength(3)
  })

  it('displays the loading overlay', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [], maxAlbumPhotos: 4, isLoading: true },
    })
    expect(wrapper.find('[data-testid="loading-overlay"]').exists()).toBe(true)
  })

  it('applies an opacity class while processing', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [photo1], maxAlbumPhotos: 4, isDeleting: true },
    })
    expect(wrapper.find('[data-testid="album-slot-0"]').classes()).toContain('opacity-50')
  })

  it('renders the full tier-quota slot count even with fewer photos', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [photo1], maxAlbumPhotos: 4 },
    })
    expect(wrapper.findAll('[data-testid^="album-slot-"]')).toHaveLength(4)
  })

  it('renders a locked badge on photos beyond the tier quota', () => {
    const photo3 = makePhoto(3, 3)
    const photo4 = makePhoto(4, 4)
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [photo1, photo2, photo3, photo4], maxAlbumPhotos: 2 },
    })

    expect(wrapper.find('[data-testid="album-locked-badge-3"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="album-locked-badge-4"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="album-locked-badge-1"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="album-locked-badge-2"]').exists()).toBe(false)
  })

  it('uses each photo position, not its array index, for the locked badge', () => {
    const lateButPublic = makePhoto(10, 1)
    const earlyButOverQuota = makePhoto(11, 4)
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [earlyButOverQuota, lateButPublic], maxAlbumPhotos: 2 },
    })

    expect(wrapper.find('[data-testid="album-locked-badge-11"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="album-locked-badge-10"]').exists()).toBe(false)
  })

  it('renders no locked badge when the tier quota covers every photo', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: {
        photos: [photo1, photo2, makePhoto(3, 3), makePhoto(4, 4)],
        maxAlbumPhotos: 4,
      },
    })
    expect(wrapper.findAll('[data-testid^="album-locked-badge-"]')).toHaveLength(0)
  })

  it('renders six slots for an Élite Face', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos: [], maxAlbumPhotos: 6 },
    })
    expect(wrapper.findAll('[data-testid^="album-slot-"]')).toHaveLength(6)
  })

  it('locks the photos beyond quota after a downgrade (6 photos, Pro quota of 4)', () => {
    const photos = [1, 2, 3, 4, 5, 6].map((n) => makePhoto(n, n))
    const wrapper = mount(PhotoAlbumGrid, {
      props: { photos, maxAlbumPhotos: 4 },
    })

    expect(wrapper.findAll('[data-testid^="album-slot-"]')).toHaveLength(6)
    expect(wrapper.find('[data-testid="album-locked-badge-5"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="album-locked-badge-6"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="album-locked-badge-4"]').exists()).toBe(false)
  })
})
