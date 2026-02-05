import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfilePhotoSection from '../ProfilePhotoSection.vue'

describe('ProfilePhotoSection', () => {
  const defaultProps = {
    photoUrl: 'https://example.com/photo.jpg',
    prenom: 'Adjoua',
    isAvailable: true,
    hasAlbumPhotos: true,
    albumPhotosCount: 5,
  }

  it('renders the component', () => {
    const wrapper = mount(ProfilePhotoSection, { props: defaultProps })
    expect(wrapper.find('[data-testid="profile-photo-section"]').exists()).toBe(true)
  })

  it('displays the profile photo when photoUrl is provided', () => {
    const wrapper = mount(ProfilePhotoSection, { props: defaultProps })

    const img = wrapper.find('[data-testid="profile-photo"]')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/photo.jpg')
    expect(img.attributes('alt')).toBe('Photo de profil de Adjoua')
  })

  it('displays placeholder when photoUrl is null', () => {
    const wrapper = mount(ProfilePhotoSection, {
      props: { ...defaultProps, photoUrl: null },
    })

    expect(wrapper.find('[data-testid="profile-photo"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="profile-photo-placeholder"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Photo non disponible')
  })

  it('shows availability badge with "Disponible" when available', () => {
    const wrapper = mount(ProfilePhotoSection, {
      props: { ...defaultProps, isAvailable: true },
    })

    const badge = wrapper.find('[data-testid="availability-badge"]')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toBe('Disponible')
    expect(badge.classes()).toContain('bg-green-500/90')
  })

  it('shows availability badge with "Indisponible" when not available', () => {
    const wrapper = mount(ProfilePhotoSection, {
      props: { ...defaultProps, isAvailable: false },
    })

    const badge = wrapper.find('[data-testid="availability-badge"]')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toBe('Indisponible')
    expect(badge.classes()).toContain('bg-gray-500/90')
  })

  it('shows album indicator when hasAlbumPhotos is true', () => {
    const wrapper = mount(ProfilePhotoSection, {
      props: { ...defaultProps, hasAlbumPhotos: true, albumPhotosCount: 5 },
    })

    const indicator = wrapper.find('[data-testid="album-indicator"]')
    expect(indicator.exists()).toBe(true)
    expect(indicator.text()).toContain('+5 photos')
    expect(indicator.text()).toContain('Réservé aux Producteurs')
  })

  it('hides album indicator when hasAlbumPhotos is false', () => {
    const wrapper = mount(ProfilePhotoSection, {
      props: { ...defaultProps, hasAlbumPhotos: false, albumPhotosCount: 0 },
    })

    expect(wrapper.find('[data-testid="album-indicator"]').exists()).toBe(false)
  })

  it('hides album indicator when albumPhotosCount is 0', () => {
    const wrapper = mount(ProfilePhotoSection, {
      props: { ...defaultProps, hasAlbumPhotos: true, albumPhotosCount: 0 },
    })

    expect(wrapper.find('[data-testid="album-indicator"]').exists()).toBe(false)
  })

  it('displays the correct album count', () => {
    const wrapper = mount(ProfilePhotoSection, {
      props: { ...defaultProps, hasAlbumPhotos: true, albumPhotosCount: 12 },
    })

    expect(wrapper.text()).toContain('+12 photos')
  })

  it('includes prenom in the caption', () => {
    const wrapper = mount(ProfilePhotoSection, {
      props: { ...defaultProps, prenom: 'Kofi' },
    })

    expect(wrapper.text()).toContain('Photo officielle de Kofi')
  })
})
