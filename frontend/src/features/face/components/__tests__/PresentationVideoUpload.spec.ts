import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PresentationVideoUpload from '../PresentationVideoUpload.vue'
import type { PresentationVideoInfo } from '../../types'

describe('PresentationVideoUpload', () => {
  const mockVideoInfo: PresentationVideoInfo = {
    presentation_video_url: 'http://localhost/storage/videos/faces/presentation/video.mp4',
    presentation_video_thumbnail_url:
      'http://localhost/storage/videos/faces/presentation/thumbnails/thumbnail.jpg',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders placeholder when no video', () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: null,
      },
    })

    expect(wrapper.find('[data-testid="video-placeholder"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="video-player"]').exists()).toBe(false)
  })

  it('renders video player when video available', () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: mockVideoInfo,
      },
    })

    expect(wrapper.find('[data-testid="video-player"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="video-placeholder"]').exists()).toBe(false)

    const video = wrapper.find('[data-testid="video-player"]')
    expect(video.attributes('src')).toBe(mockVideoInfo.presentation_video_url)
  })

  it('shows upload button with "Ajouter une vidéo" text when no video', () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: null,
      },
    })

    const uploadButton = wrapper.find('[data-testid="upload-button"]')
    expect(uploadButton.exists()).toBe(true)
    expect(uploadButton.text()).toBe('Ajouter une vidéo')
  })

  it('shows upload button with "Changer la vidéo" text when video exists', () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: mockVideoInfo,
      },
    })

    const uploadButton = wrapper.find('[data-testid="upload-button"]')
    expect(uploadButton.exists()).toBe(true)
    expect(uploadButton.text()).toBe('Changer la vidéo')
  })

  it('shows delete button only when video exists', () => {
    const wrapperWithVideo = mount(PresentationVideoUpload, {
      props: {
        videoInfo: mockVideoInfo,
      },
    })

    const wrapperWithoutVideo = mount(PresentationVideoUpload, {
      props: {
        videoInfo: null,
      },
    })

    expect(wrapperWithVideo.find('[data-testid="delete-button"]').exists()).toBe(true)
    expect(wrapperWithoutVideo.find('[data-testid="delete-button"]').exists()).toBe(false)
  })

  it('emits upload event when file is selected', async () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: null,
      },
    })

    const fileInput = wrapper.find('[data-testid="file-input"]')
    const file = new File(['test'], 'test.mp4', { type: 'video/mp4' })

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

  it('emits delete event when delete button is clicked and confirmed via modal', async () => {
    // Create a container element for Teleport target
    const teleportTarget = document.createElement('div')
    teleportTarget.id = 'teleport-target'
    document.body.appendChild(teleportTarget)

    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: mockVideoInfo,
      },
      global: {
        stubs: {
          teleport: true,
        },
      },
    })

    // Click delete button to open modal
    const deleteButton = wrapper.find('[data-testid="delete-button"]')
    await deleteButton.trigger('click')
    await flushPromises()

    // Find and click confirm button in the modal
    const confirmButton = wrapper.findComponent({ name: 'ConfirmModal' })
    expect(confirmButton.exists()).toBe(true)

    // Emit confirm event from the modal
    confirmButton.vm.$emit('confirm')
    await flushPromises()

    expect(wrapper.emitted('delete')).toBeTruthy()

    // Cleanup
    document.body.removeChild(teleportTarget)
  })

  it('does not emit delete event when confirmation is cancelled via modal', async () => {
    // Create a container element for Teleport target
    const teleportTarget = document.createElement('div')
    teleportTarget.id = 'teleport-target'
    document.body.appendChild(teleportTarget)

    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: mockVideoInfo,
      },
      global: {
        stubs: {
          teleport: true,
        },
      },
    })

    // Click delete button to open modal
    const deleteButton = wrapper.find('[data-testid="delete-button"]')
    await deleteButton.trigger('click')
    await flushPromises()

    // Find and click cancel button in the modal
    const confirmModal = wrapper.findComponent({ name: 'ConfirmModal' })
    expect(confirmModal.exists()).toBe(true)

    // Emit cancel event from the modal
    confirmModal.vm.$emit('cancel')
    await flushPromises()

    expect(wrapper.emitted('delete')).toBeFalsy()

    // Cleanup
    document.body.removeChild(teleportTarget)
  })

  it('displays error message when error prop is set', () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: null,
        error: 'La vidéo est trop volumineuse',
      },
    })

    const errorMessage = wrapper.find('[data-testid="upload-error"]')
    expect(errorMessage.exists()).toBe(true)
    expect(errorMessage.text()).toContain('La vidéo est trop volumineuse')
  })

  it('disables buttons when uploading', () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: null,
        isUploading: true,
      },
    })

    const uploadButton = wrapper.find('[data-testid="upload-button"]')
    expect(uploadButton.attributes('disabled')).toBeDefined()
  })

  it('disables buttons when deleting', () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: mockVideoInfo,
        isDeleting: true,
      },
    })

    const uploadButton = wrapper.find('[data-testid="upload-button"]')
    const deleteButton = wrapper.find('[data-testid="delete-button"]')

    expect(uploadButton.attributes('disabled')).toBeDefined()
    expect(deleteButton.attributes('disabled')).toBeDefined()
  })

  it('has correct file input accept attribute', () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: null,
      },
    })

    const fileInput = wrapper.find('[data-testid="file-input"]')
    expect(fileInput.attributes('accept')).toContain('video/mp4')
    expect(fileInput.attributes('accept')).toContain('video/quicktime')
    expect(fileInput.attributes('accept')).toContain('.mp4')
    expect(fileInput.attributes('accept')).toContain('.mov')
    expect(fileInput.attributes('accept')).toContain('.avi')
  })

  it('displays help text about file format and size', () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: null,
      },
    })

    expect(wrapper.text()).toContain('MP4, MOV ou AVI')
    expect(wrapper.text()).toContain('50MB')
    expect(wrapper.text()).toContain('2 minutes')
  })

  it('displays upload progress when uploading', () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: null,
        isUploading: true,
        uploadProgress: {
          loaded: 5000000,
          total: 10000000,
          percentage: 50,
        },
      },
    })

    const progressBar = wrapper.find('[data-testid="upload-progress"]')
    expect(progressBar.exists()).toBe(true)
    expect(progressBar.text()).toContain('50%')
  })

  it('clears preview and shows original video when error occurs after file selection', async () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: mockVideoInfo,
        error: null,
      },
    })

    // Initially shows the video player
    expect(wrapper.find('[data-testid="video-player"]').attributes('src')).toBe(
      mockVideoInfo.presentation_video_url,
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
    await wrapper.setProps({ error: 'Le fichier doit être au format MP4, MOV ou AVI' })
    await flushPromises()

    // Error should be displayed
    expect(wrapper.find('[data-testid="upload-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="upload-error"]').text()).toContain('MP4, MOV ou AVI')

    // Video should revert to original (not the invalid preview)
    const video = wrapper.find('[data-testid="video-player"]')
    expect(video.exists()).toBe(true)
    expect(video.attributes('src')).toBe(mockVideoInfo.presentation_video_url)
  })

  it('shows placeholder when error occurs with no existing video', async () => {
    const wrapper = mount(PresentationVideoUpload, {
      props: {
        videoInfo: null,
        error: null,
      },
    })

    // Initially shows placeholder
    expect(wrapper.find('[data-testid="video-placeholder"]').exists()).toBe(true)

    // Simulate file selection
    const fileInput = wrapper.find('[data-testid="file-input"]')
    const file = new File(['test'], 'test.wmv', { type: 'video/x-ms-wmv' })

    Object.defineProperty(fileInput.element, 'files', {
      value: [file],
      writable: false,
    })

    await fileInput.trigger('change')
    await flushPromises()

    // Now simulate error being set
    await wrapper.setProps({ error: 'Le fichier doit être au format MP4, MOV ou AVI' })
    await flushPromises()

    // Should show placeholder again (preview cleared)
    expect(wrapper.find('[data-testid="video-placeholder"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="video-player"]').exists()).toBe(false)
  })

  describe('canUpload=false (FP-2.7.1 Free-tier gate)', () => {
    it('renders the tier-locked banner and suppresses the drop zone when canUpload=false and no stored video', () => {
      const wrapper = mount(PresentationVideoUpload, {
        props: { videoInfo: null, canUpload: false },
      })

      const banner = wrapper.find('[data-testid="presentation-video-tier-locked"]')
      expect(banner.exists()).toBe(true)
      expect(banner.text()).toContain(
        "L'ajout d'une vidéo de présentation est réservé aux abonnés payants.",
      )
      // The drop zone / video placeholder is suppressed.
      expect(wrapper.find('[data-testid="video-placeholder"]').exists()).toBe(false)
    })

    it('renders the lock ribbon above the video and disables the action buttons when canUpload=false but a stored video exists', () => {
      const wrapper = mount(PresentationVideoUpload, {
        props: {
          videoInfo: mockVideoInfo,
          canUpload: false,
        },
      })

      // Lock ribbon visible
      expect(wrapper.find('[data-testid="presentation-video-lock-badge"]').exists()).toBe(true)
      // Video player remains visible
      expect(wrapper.find('[data-testid="video-player"]').exists()).toBe(true)
      // Tier-locked banner NOT shown (since hasVideo is true)
      expect(wrapper.find('[data-testid="presentation-video-tier-locked"]').exists()).toBe(false)
      // Buttons disabled
      expect(wrapper.find('[data-testid="upload-button"]').attributes('disabled')).toBeDefined()
      expect(wrapper.find('[data-testid="delete-button"]').attributes('disabled')).toBeDefined()
    })

    it('emits navigate-pricing once when the tier-locked CTA is clicked', async () => {
      const wrapper = mount(PresentationVideoUpload, {
        props: { videoInfo: null, canUpload: false },
      })

      await wrapper.find('[data-testid="presentation-video-tier-locked-cta"]').trigger('click')

      expect(wrapper.emitted('navigate-pricing')).toHaveLength(1)
      expect(wrapper.emitted('navigate-pricing')?.[0]).toEqual([])
    })

    it('preserves the existing behaviour when canUpload=true (default) — drop zone visible, no tier-locked banner', () => {
      const wrapper = mount(PresentationVideoUpload, {
        props: { videoInfo: null, canUpload: true },
      })

      expect(wrapper.find('[data-testid="presentation-video-tier-locked"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="presentation-video-lock-badge"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="video-placeholder"]').exists()).toBe(true)
      // Upload button enabled
      expect(wrapper.find('[data-testid="upload-button"]').attributes('disabled')).toBeUndefined()
    })

    it('does NOT emit upload when a tier-locked user drops a file on the video container (P1)', async () => {
      const wrapper = mount(PresentationVideoUpload, {
        props: { videoInfo: mockVideoInfo, canUpload: false },
      })

      // Find the drop container (the one with @drop handler on the video tile).
      const drop = wrapper.find('.relative.aspect-video')
      expect(drop.exists()).toBe(true)

      const file = new File(['x'], 'video.mp4', { type: 'video/mp4' })
      const dataTransfer = { files: [file] } as unknown as DataTransfer
      await drop.trigger('drop', { dataTransfer })

      expect(wrapper.emitted('upload')).toBeUndefined()
    })

    it('does NOT emit upload when a tier-locked user selects a file via the hidden input (P1)', async () => {
      const wrapper = mount(PresentationVideoUpload, {
        props: { videoInfo: mockVideoInfo, canUpload: false },
      })

      const input = wrapper.find('[data-testid="file-input"]')
      const file = new File(['x'], 'video.mp4', { type: 'video/mp4' })
      Object.defineProperty(input.element, 'files', { value: [file], writable: false })

      await input.trigger('change')

      expect(wrapper.emitted('upload')).toBeUndefined()
    })
  })
})
