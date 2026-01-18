import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref, computed } from 'vue'
import AgencyLogoUpload from '../AgencyLogoUpload.vue'
import { useAgencyLogo } from '../../composables/useAgencyLogo'

// Mock the useAgencyLogo composable
vi.mock('../../composables/useAgencyLogo', () => ({
  useAgencyLogo: vi.fn(),
}))

describe('AgencyLogoUpload', () => {
  // Use refs for reactive state in tests
  const logoUrl = ref<string | null>(null)
  const thumbnailUrl = ref<string | null>(null)
  const isLoading = ref(false)
  const isUploading = ref(false)
  const isDeleting = ref(false)
  const error = ref<string | null>(null)

  // Mock functions
  const fetchLogo = vi.fn().mockResolvedValue({ success: true })
  const uploadLogo = vi.fn().mockResolvedValue({ success: true })
  const deleteLogo = vi.fn().mockResolvedValue({ success: true })
  const validateFile = vi.fn().mockReturnValue({ valid: true })

  // Create the mock composable with computed hasLogo
  const createMockComposable = () => ({
    logoUrl,
    thumbnailUrl,
    isLoading,
    isUploading,
    isDeleting,
    error,
    hasLogo: computed(() => !!logoUrl.value),
    fetchLogo,
    uploadLogo,
    deleteLogo,
    validateFile,
  })

  beforeEach(() => {
    vi.clearAllMocks()
    // Reset mock composable values
    logoUrl.value = null
    thumbnailUrl.value = null
    isLoading.value = false
    isUploading.value = false
    isDeleting.value = false
    error.value = null
    fetchLogo.mockResolvedValue({ success: true })
    uploadLogo.mockResolvedValue({ success: true })
    deleteLogo.mockResolvedValue({ success: true })
    validateFile.mockReturnValue({ valid: true })

    vi.mocked(useAgencyLogo).mockReturnValue(createMockComposable() as ReturnType<typeof useAgencyLogo>)
  })

  describe('rendering', () => {
    it('should render the logo upload component', () => {
      const wrapper = mount(AgencyLogoUpload)

      expect(wrapper.find('[data-testid="agency-logo-upload"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="logo-preview-area"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="upload-button"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="file-input"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="help-text"]').exists()).toBe(true)
    })

    it('should display placeholder when no logo', () => {
      const wrapper = mount(AgencyLogoUpload)

      expect(wrapper.find('[data-testid="logo-placeholder"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="logo-image"]').exists()).toBe(false)
    })

    it('should display logo image when logo exists', async () => {
      logoUrl.value = 'http://example.com/logo.jpg'
      thumbnailUrl.value = 'http://example.com/logo-thumb.jpg'

      const wrapper = mount(AgencyLogoUpload)
      await flushPromises()

      expect(wrapper.find('[data-testid="logo-image"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="logo-placeholder"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="logo-image"]').attributes('src')).toBe(
        'http://example.com/logo-thumb.jpg'
      )
    })

    it('should show delete button when logo exists', async () => {
      logoUrl.value = 'http://example.com/logo.jpg'

      const wrapper = mount(AgencyLogoUpload)
      await flushPromises()

      expect(wrapper.find('[data-testid="delete-button"]').exists()).toBe(true)
    })

    it('should hide delete button when no logo', () => {
      const wrapper = mount(AgencyLogoUpload)

      expect(wrapper.find('[data-testid="delete-button"]').exists()).toBe(false)
    })

    it('should display help text with file limits', () => {
      const wrapper = mount(AgencyLogoUpload)

      expect(wrapper.find('[data-testid="help-text"]').text()).toContain('JPG')
      expect(wrapper.find('[data-testid="help-text"]').text()).toContain('PNG')
      expect(wrapper.find('[data-testid="help-text"]').text()).toContain('2 Mo')
    })
  })

  describe('loading states', () => {
    it('should show loading overlay when loading', async () => {
      isLoading.value = true

      const wrapper = mount(AgencyLogoUpload)

      expect(wrapper.find('[data-testid="loading-overlay"]').exists()).toBe(true)
    })

    it('should show loading overlay when uploading', async () => {
      isUploading.value = true

      const wrapper = mount(AgencyLogoUpload)

      expect(wrapper.find('[data-testid="loading-overlay"]').exists()).toBe(true)
    })

    it('should show loading overlay when deleting', async () => {
      isDeleting.value = true

      const wrapper = mount(AgencyLogoUpload)

      expect(wrapper.find('[data-testid="loading-overlay"]').exists()).toBe(true)
    })

    it('should disable buttons when uploading', () => {
      isUploading.value = true

      const wrapper = mount(AgencyLogoUpload)

      const uploadButton = wrapper.find('[data-testid="upload-button"]')
      expect(uploadButton.attributes('disabled')).toBeDefined()
    })
  })

  describe('error handling', () => {
    it('should display error message when error exists', () => {
      error.value = 'Test error message'

      const wrapper = mount(AgencyLogoUpload)

      expect(wrapper.find('[data-testid="error-message"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain('Test error message')
    })

    it('should hide error message when no error', () => {
      const wrapper = mount(AgencyLogoUpload)

      expect(wrapper.find('[data-testid="error-message"]').exists()).toBe(false)
    })
  })

  describe('file upload', () => {
    it('should call uploadLogo when file is selected', async () => {
      const wrapper = mount(AgencyLogoUpload)

      const fileInput = wrapper.find('[data-testid="file-input"]')
      const file = new File(['test'], 'logo.jpg', { type: 'image/jpeg' })

      // Simulate file selection
      Object.defineProperty(fileInput.element, 'files', {
        value: [file],
        writable: false,
      })

      await fileInput.trigger('change')
      await flushPromises()

      expect(uploadLogo).toHaveBeenCalledWith(file)
    })

    it('should validate file before upload', async () => {
      const wrapper = mount(AgencyLogoUpload)

      const fileInput = wrapper.find('[data-testid="file-input"]')
      const file = new File(['test'], 'logo.jpg', { type: 'image/jpeg' })

      Object.defineProperty(fileInput.element, 'files', {
        value: [file],
        writable: false,
      })

      await fileInput.trigger('change')
      await flushPromises()

      expect(validateFile).toHaveBeenCalledWith(file)
    })

    it('should not call uploadLogo when file validation fails', async () => {
      validateFile.mockReturnValue({ valid: false, error: 'Invalid file' })

      const wrapper = mount(AgencyLogoUpload)

      const fileInput = wrapper.find('[data-testid="file-input"]')
      const file = new File(['test'], 'logo.gif', { type: 'image/gif' })

      Object.defineProperty(fileInput.element, 'files', {
        value: [file],
        writable: false,
      })

      await fileInput.trigger('change')
      await flushPromises()

      expect(uploadLogo).not.toHaveBeenCalled()
    })

    it('should trigger file input when upload button is clicked', async () => {
      const wrapper = mount(AgencyLogoUpload)

      const uploadButton = wrapper.find('[data-testid="upload-button"]')
      const fileInput = wrapper.find('[data-testid="file-input"]')

      // Mock the click method
      const clickSpy = vi.spyOn(fileInput.element as HTMLInputElement, 'click')

      await uploadButton.trigger('click')

      expect(clickSpy).toHaveBeenCalled()
    })

    it('should show "Ajouter" on upload button when no logo', () => {
      // Make sure logoUrl is null so hasLogo computed returns false
      logoUrl.value = null

      const wrapper = mount(AgencyLogoUpload)

      const uploadButton = wrapper.find('[data-testid="upload-button"]')
      expect(uploadButton.text()).toContain('Ajouter')
    })

    it('should show "Changer" on upload button when logo exists', () => {
      logoUrl.value = 'http://example.com/logo.jpg'

      const wrapper = mount(AgencyLogoUpload)

      const uploadButton = wrapper.find('[data-testid="upload-button"]')
      expect(uploadButton.text()).toContain('Changer')
    })
  })

  describe('file deletion', () => {
    it('should call deleteLogo when delete button is clicked', async () => {
      logoUrl.value = 'http://example.com/logo.jpg'

      const wrapper = mount(AgencyLogoUpload)

      const deleteButton = wrapper.find('[data-testid="delete-button"]')
      await deleteButton.trigger('click')

      expect(deleteLogo).toHaveBeenCalled()
    })

    it('should disable delete button when deleting', () => {
      logoUrl.value = 'http://example.com/logo.jpg'
      isDeleting.value = true

      const wrapper = mount(AgencyLogoUpload)

      const deleteButton = wrapper.find('[data-testid="delete-button"]')
      expect(deleteButton.attributes('disabled')).toBeDefined()
    })
  })

  describe('drag and drop', () => {
    it('should show drag indicator when dragging over', async () => {
      const wrapper = mount(AgencyLogoUpload)

      const previewArea = wrapper.find('[data-testid="logo-preview-area"]')

      await previewArea.trigger('dragover')

      expect(wrapper.find('[data-testid="drag-indicator"]').exists()).toBe(true)
    })

    it('should hide drag indicator when drag leaves', async () => {
      const wrapper = mount(AgencyLogoUpload)

      const previewArea = wrapper.find('[data-testid="logo-preview-area"]')

      await previewArea.trigger('dragover')
      expect(wrapper.find('[data-testid="drag-indicator"]').exists()).toBe(true)

      await previewArea.trigger('dragleave')
      expect(wrapper.find('[data-testid="drag-indicator"]').exists()).toBe(false)
    })

    it('should upload file on drop', async () => {
      const wrapper = mount(AgencyLogoUpload)

      const previewArea = wrapper.find('[data-testid="logo-preview-area"]')
      const file = new File(['test'], 'logo.jpg', { type: 'image/jpeg' })

      const dropEvent = new Event('drop') as Event & { dataTransfer: DataTransfer }
      Object.defineProperty(dropEvent, 'dataTransfer', {
        value: {
          files: [file],
        },
      })
      Object.defineProperty(dropEvent, 'preventDefault', {
        value: vi.fn(),
      })

      await previewArea.trigger('drop', { dataTransfer: { files: [file] } })
      await flushPromises()

      expect(uploadLogo).toHaveBeenCalledWith(file)
    })
  })

  describe('lifecycle', () => {
    it('should fetch logo on mount', async () => {
      mount(AgencyLogoUpload)
      await flushPromises()

      expect(fetchLogo).toHaveBeenCalled()
    })
  })

  describe('click on preview area', () => {
    it('should trigger file input when clicking preview area', async () => {
      const wrapper = mount(AgencyLogoUpload)

      const previewArea = wrapper.find('[data-testid="logo-preview-area"]')
      const fileInput = wrapper.find('[data-testid="file-input"]')

      const clickSpy = vi.spyOn(fileInput.element as HTMLInputElement, 'click')

      await previewArea.trigger('click')

      expect(clickSpy).toHaveBeenCalled()
    })

    it('should not trigger file input when clicking preview area during processing', async () => {
      isUploading.value = true

      const wrapper = mount(AgencyLogoUpload)

      const previewArea = wrapper.find('[data-testid="logo-preview-area"]')
      const fileInput = wrapper.find('[data-testid="file-input"]')

      const clickSpy = vi.spyOn(fileInput.element as HTMLInputElement, 'click')

      await previewArea.trigger('click')

      expect(clickSpy).not.toHaveBeenCalled()
    })
  })
})
