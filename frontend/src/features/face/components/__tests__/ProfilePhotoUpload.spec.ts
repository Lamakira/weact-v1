import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ProfilePhotoUpload from '../ProfilePhotoUpload.vue'
import type { FaceProfile } from '../../types'

describe('ProfilePhotoUpload', () => {
  const mockProfile: FaceProfile = {
    id: 1,
    nom: 'Dupont',
    prenom: 'Jean',
    username: 'jeandupont',
    profile_photo_url: 'http://localhost/storage/avatars/faces/photo.jpg',
    thumbnail_url: 'http://localhost/storage/avatars/faces/thumbnails/photo.jpg',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders placeholder when no profile photo', () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: null,
      },
    })

    expect(wrapper.find('[data-testid="photo-placeholder"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="profile-photo"]').exists()).toBe(false)
  })

  it('renders profile photo when available', () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: mockProfile,
      },
    })

    expect(wrapper.find('[data-testid="profile-photo"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="photo-placeholder"]').exists()).toBe(false)

    const img = wrapper.find('[data-testid="profile-photo"]')
    expect(img.attributes('src')).toBe(mockProfile.profile_photo_url)
  })

  it('shows upload button with "Ajouter une photo" text when no photo', () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: null,
      },
    })

    const uploadButton = wrapper.find('[data-testid="upload-button"]')
    expect(uploadButton.exists()).toBe(true)
    expect(uploadButton.text()).toBe('Ajouter une photo')
  })

  it('shows upload button with "Modifier" text when photo exists', () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: mockProfile,
      },
    })

    const uploadButton = wrapper.find('[data-testid="upload-button"]')
    expect(uploadButton.exists()).toBe(true)
    expect(uploadButton.text()).toBe('Modifier')
  })

  it('shows delete button only when photo exists', () => {
    const wrapperWithPhoto = mount(ProfilePhotoUpload, {
      props: {
        profile: mockProfile,
      },
    })

    const wrapperWithoutPhoto = mount(ProfilePhotoUpload, {
      props: {
        profile: null,
      },
    })

    expect(wrapperWithPhoto.find('[data-testid="delete-button"]').exists()).toBe(true)
    expect(wrapperWithoutPhoto.find('[data-testid="delete-button"]').exists()).toBe(false)
  })

  it('emits upload event when file is selected', async () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: null,
      },
    })

    const fileInput = wrapper.find('[data-testid="file-input"]')
    const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

    // Create a mock FileList
    Object.defineProperty(fileInput.element, 'files', {
      value: [file],
      writable: false,
    })

    await fileInput.trigger('change')
    await flushPromises()

    const emitted = wrapper.emitted('upload')
    expect(emitted).toBeTruthy()
    expect(emitted?.[0]).toEqual([file])
  })

  it('emits delete event when delete button is clicked', async () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: mockProfile,
      },
    })

    const deleteButton = wrapper.find('[data-testid="delete-button"]')
    await deleteButton.trigger('click')

    expect(wrapper.emitted('delete')).toBeTruthy()
  })

  it('displays error message when error prop is set', () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: null,
        error: 'Le fichier est trop volumineux',
      },
    })

    const errorMessage = wrapper.find('[data-testid="upload-error"]')
    expect(errorMessage.exists()).toBe(true)
    expect(errorMessage.text()).toContain('Le fichier est trop volumineux')
  })

  it('disables buttons when uploading', () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: null,
        isUploading: true,
      },
    })

    const uploadButton = wrapper.find('[data-testid="upload-button"]')
    expect(uploadButton.attributes('disabled')).toBeDefined()
  })

  it('disables buttons when deleting', () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: mockProfile,
        isDeleting: true,
      },
    })

    const uploadButton = wrapper.find('[data-testid="upload-button"]')
    const deleteButton = wrapper.find('[data-testid="delete-button"]')

    expect(uploadButton.attributes('disabled')).toBeDefined()
    expect(deleteButton.attributes('disabled')).toBeDefined()
  })

  it('has correct file input accept attribute', () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: null,
      },
    })

    const fileInput = wrapper.find('[data-testid="file-input"]')
    expect(fileInput.attributes('accept')).toBe('image/jpeg,image/png')
  })

  it('displays help text about file format and size', () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: null,
      },
    })

    expect(wrapper.text()).toContain('Format JPG ou PNG')
    expect(wrapper.text()).toContain('Taille max: 5 Mo')
  })

  it('clears preview and shows original photo when error occurs after file selection', async () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: mockProfile,
        error: null,
      },
    })

    // Initially shows the profile photo
    expect(wrapper.find('[data-testid="profile-photo"]').attributes('src')).toBe(
      mockProfile.profile_photo_url,
    )

    // Simulate file selection (which would create a preview)
    const fileInput = wrapper.find('[data-testid="file-input"]')
    const file = new File(['test'], 'test.pdf', { type: 'application/pdf' })

    Object.defineProperty(fileInput.element, 'files', {
      value: [file],
      writable: false,
    })

    await fileInput.trigger('change')
    await flushPromises()

    // File was selected, upload event emitted
    expect(wrapper.emitted('upload')).toBeTruthy()

    // Now simulate error being set (validation failed)
    await wrapper.setProps({ error: 'Le fichier doit être au format JPG ou PNG' })
    await flushPromises()

    // Error should be displayed
    expect(wrapper.find('[data-testid="upload-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="upload-error"]').text()).toContain('JPG ou PNG')

    // Photo should revert to original (not the invalid preview)
    const photo = wrapper.find('[data-testid="profile-photo"]')
    expect(photo.exists()).toBe(true)
    expect(photo.attributes('src')).toBe(mockProfile.profile_photo_url)
  })

  it('shows placeholder when error occurs with no existing photo', async () => {
    const wrapper = mount(ProfilePhotoUpload, {
      props: {
        profile: null,
        error: null,
      },
    })

    // Initially shows placeholder
    expect(wrapper.find('[data-testid="photo-placeholder"]').exists()).toBe(true)

    // Simulate file selection
    const fileInput = wrapper.find('[data-testid="file-input"]')
    const file = new File(['test'], 'test.gif', { type: 'image/gif' })

    Object.defineProperty(fileInput.element, 'files', {
      value: [file],
      writable: false,
    })

    await fileInput.trigger('change')
    await flushPromises()

    // Now simulate error being set
    await wrapper.setProps({ error: 'Le fichier doit être au format JPG ou PNG' })
    await flushPromises()

    // Should show placeholder again (preview cleared)
    expect(wrapper.find('[data-testid="photo-placeholder"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="profile-photo"]').exists()).toBe(false)
  })
})
