import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PhotoAlbumGrid from '../PhotoAlbumGrid.vue'
import type { FacePhoto } from '../../types'

describe('PhotoAlbumGrid', () => {
  const mockPhoto1: FacePhoto = {
    id: 1,
    photo_url: 'http://localhost/storage/avatars/faces/albums/photo1.jpg',
    thumbnail_url: 'http://localhost/storage/avatars/faces/albums/thumbnails/photo1.jpg',
    position: 1,
  }

  const mockPhoto2: FacePhoto = {
    id: 2,
    photo_url: 'http://localhost/storage/avatars/faces/albums/photo2.jpg',
    thumbnail_url: 'http://localhost/storage/avatars/faces/albums/thumbnails/photo2.jpg',
    position: 2,
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders empty grid with placeholders when no photos', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: {
        photos: [],
      },
    })

    expect(wrapper.find('[data-testid="photo-album-grid"]').exists()).toBe(true)
    // Should have 4 slots
    expect(wrapper.findAll('[data-testid^="album-slot-"]')).toHaveLength(4)
    // Should have 4 add buttons (since canAddMore is true by default)
    expect(wrapper.findAll('[data-testid^="add-photo-slot-"]')).toHaveLength(4)
  })

  it('renders photos in grid', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: {
        photos: [mockPhoto1, mockPhoto2],
      },
    })

    // Should have 2 photos
    expect(wrapper.find(`[data-testid="album-photo-${mockPhoto1.id}"]`).exists()).toBe(true)
    expect(wrapper.find(`[data-testid="album-photo-${mockPhoto2.id}"]`).exists()).toBe(true)

    // Check src attributes
    const photo1 = wrapper.find(`[data-testid="album-photo-${mockPhoto1.id}"]`)
    expect(photo1.attributes('src')).toBe(mockPhoto1.photo_url)

    // Should have 2 empty slots with add buttons
    expect(wrapper.findAll('[data-testid^="add-photo-slot-"]')).toHaveLength(2)
  })

  it('shows delete button overlay on photos', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: {
        photos: [mockPhoto1],
      },
    })

    // Delete button should exist (visible on hover via CSS)
    expect(wrapper.find(`[data-testid="delete-photo-${mockPhoto1.id}"]`).exists()).toBe(true)
  })

  it('emits delete event when delete button is clicked', async () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: {
        photos: [mockPhoto1],
      },
    })

    const deleteButton = wrapper.find(`[data-testid="delete-photo-${mockPhoto1.id}"]`)
    await deleteButton.trigger('click')

    expect(wrapper.emitted('delete')).toBeTruthy()
    expect(wrapper.emitted('delete')?.[0]).toEqual([mockPhoto1.id])
  })

  it('emits add-click event when empty slot is clicked', async () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: {
        photos: [],
        canAddMore: true,
      },
    })

    const addButton = wrapper.find('[data-testid="add-photo-slot-0"]')
    await addButton.trigger('click')

    expect(wrapper.emitted('add-click')).toBeTruthy()
  })

  it('shows disabled placeholder when canAddMore is false', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: {
        photos: [mockPhoto1],
        canAddMore: false,
      },
    })

    // Empty slots should show disabled placeholder, not add button
    expect(wrapper.findAll('[data-testid^="add-photo-slot-"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid^="empty-slot-"]')).toHaveLength(3)
  })

  it('displays loading state', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: {
        photos: [],
        isLoading: true,
      },
    })

    expect(wrapper.find('[data-testid="loading-overlay"]').exists()).toBe(true)
  })

  it('applies opacity when processing', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: {
        photos: [mockPhoto1],
        isDeleting: true,
      },
    })

    const slot = wrapper.find('[data-testid="album-slot-0"]')
    expect(slot.classes()).toContain('opacity-50')
  })

  it('renders all 4 slots even with some photos', () => {
    const wrapper = mount(PhotoAlbumGrid, {
      props: {
        photos: [mockPhoto1],
      },
    })

    // Should always have 4 slots
    expect(wrapper.findAll('[data-testid^="album-slot-"]')).toHaveLength(4)
  })
})
