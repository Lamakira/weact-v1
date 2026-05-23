import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AlbumPhotoUpload from '../AlbumPhotoUpload.vue'

describe('AlbumPhotoUpload', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders upload area when not full', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isFull: false,
      },
    })

    expect(wrapper.find('[data-testid="album-photo-upload"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="upload-area"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Cliquez pour ajouter')
  })

  it('shows full album message when isFull is true', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isFull: true,
        uploadLimit: 4,
      },
    })

    expect(wrapper.text()).toContain('Album complet (4 photos maximum pour votre abonnement)')
  })

  it('shows add button when not full', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isFull: false,
      },
    })

    expect(wrapper.text()).toContain('Cliquez pour ajouter')
    expect(wrapper.text()).not.toContain('Album complet')
  })

  it('disables upload when full', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isFull: true,
      },
    })

    const uploadArea = wrapper.find('[data-testid="upload-area"]')
    expect(uploadArea.classes()).toContain('cursor-not-allowed')
    expect(uploadArea.classes()).toContain('opacity-50')
  })

  it('shows uploading spinner when isUploading is true', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isUploading: true,
      },
    })

    expect(wrapper.find('[data-testid="uploading-spinner"]').exists()).toBe(true)
  })

  it('emits upload event when file is selected', async () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isFull: false,
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

  it('displays error message when error prop is set', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        error: 'Le fichier est trop volumineux',
      },
    })

    const errorMessage = wrapper.find('[data-testid="album-error"]')
    expect(errorMessage.exists()).toBe(true)
    expect(errorMessage.text()).toContain('Le fichier est trop volumineux')
  })

  it('has correct file input accept attribute', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isFull: false,
      },
    })

    const fileInput = wrapper.find('[data-testid="file-input"]')
    expect(fileInput.attributes('accept')).toBe('image/jpeg,image/png')
  })

  it('displays help text about file format and size', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isFull: false,
      },
    })

    expect(wrapper.text()).toContain('Format JPG ou PNG')
    expect(wrapper.text()).toContain('Taille max: 8 Mo')
  })

  it('file input is disabled when full', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isFull: true,
      },
    })

    const fileInput = wrapper.find('[data-testid="file-input"]')
    expect(fileInput.attributes('disabled')).toBeDefined()
  })

  it('file input is disabled when uploading', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isUploading: true,
      },
    })

    const fileInput = wrapper.find('[data-testid="file-input"]')
    expect(fileInput.attributes('disabled')).toBeDefined()
  })

  it('does not emit upload if the component becomes locked after the picker opens', async () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        isFull: false,
      },
    })
    const fileInput = wrapper.find('[data-testid="file-input"]')
    const file = new File(['test'], 'photo.jpg', { type: 'image/jpeg' })

    Object.defineProperty(fileInput.element, 'files', {
      value: [file],
      writable: false,
    })

    await wrapper.setProps({ lockedByQuota: true })
    await fileInput.trigger('change')
    await flushPromises()

    expect(wrapper.emitted('upload')).toBeFalsy()
  })

  it('shows dynamic quota label when lockedByQuota is true with uploadLimit prop', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        lockedByQuota: true,
        uploadLimit: 2,
      },
    })

    expect(wrapper.text()).toContain('Album complet (2 photos maximum pour votre abonnement)')

    const uploadArea = wrapper.find('[data-testid="upload-area"]')
    expect(uploadArea.classes()).toContain('cursor-not-allowed')
    expect(uploadArea.classes()).toContain('opacity-50')
  })

  it('shows current count over upload limit hint when not full', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        currentCount: 1,
        uploadLimit: 2,
        isFull: false,
        lockedByQuota: false,
      },
    })

    const label = wrapper.find('[data-testid="album-quota-label"]')
    expect(label.exists()).toBe(true)
    expect(label.text()).toContain('Cliquez pour ajouter une photo')
    expect(label.text()).toContain('(1/2)')
  })

  it('defaults the quota label to the Élite ceiling of 6 when no uploadLimit prop is passed', () => {
    const wrapper = mount(AlbumPhotoUpload, {
      props: {
        currentCount: 1,
        isFull: false,
        lockedByQuota: false,
      },
    })

    const label = wrapper.find('[data-testid="album-quota-label"]')
    expect(label.text()).toContain('(1/6)')
  })
})
