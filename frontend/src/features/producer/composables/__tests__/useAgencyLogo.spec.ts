import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { useAgencyLogo } from '../useAgencyLogo'
import { producerApi } from '../../services/producerApi'

// Mock the producer API
vi.mock('../../services/producerApi', () => ({
  producerApi: {
    getLogo: vi.fn(),
    uploadLogo: vi.fn(),
    deleteLogo: vi.fn(),
  },
}))

describe('useAgencyLogo', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('should have null logoUrl initially', () => {
      const { logoUrl } = useAgencyLogo()
      expect(logoUrl.value).toBeNull()
    })

    it('should have null thumbnailUrl initially', () => {
      const { thumbnailUrl } = useAgencyLogo()
      expect(thumbnailUrl.value).toBeNull()
    })

    it('should not be loading initially', () => {
      const { isLoading } = useAgencyLogo()
      expect(isLoading.value).toBe(false)
    })

    it('should not be uploading initially', () => {
      const { isUploading } = useAgencyLogo()
      expect(isUploading.value).toBe(false)
    })

    it('should not be deleting initially', () => {
      const { isDeleting } = useAgencyLogo()
      expect(isDeleting.value).toBe(false)
    })

    it('should have no error initially', () => {
      const { error } = useAgencyLogo()
      expect(error.value).toBeNull()
    })

    it('should have hasLogo as false initially', () => {
      const { hasLogo } = useAgencyLogo()
      expect(hasLogo.value).toBe(false)
    })
  })

  describe('fetchLogo', () => {
    it('should fetch logo successfully', async () => {
      vi.mocked(producerApi.getLogo).mockResolvedValue({
        data: {
          agency_logo_url: 'http://example.com/logo.jpg',
          agency_logo_thumbnail_url: 'http://example.com/logo-thumb.jpg',
        },
      })

      const { logoUrl, thumbnailUrl, fetchLogo, isLoading, hasLogo } = useAgencyLogo()

      const promise = fetchLogo()
      expect(isLoading.value).toBe(true)

      const result = await promise
      await flushPromises()

      expect(isLoading.value).toBe(false)
      expect(logoUrl.value).toBe('http://example.com/logo.jpg')
      expect(thumbnailUrl.value).toBe('http://example.com/logo-thumb.jpg')
      expect(hasLogo.value).toBe(true)
      expect(result.success).toBe(true)
    })

    it('should handle fetch error', async () => {
      vi.mocked(producerApi.getLogo).mockRejectedValue(new Error('Network error'))

      const { logoUrl, fetchLogo, error } = useAgencyLogo()

      const result = await fetchLogo()
      await flushPromises()

      expect(logoUrl.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue. Veuillez réessayer.')
      expect(result.success).toBe(false)
    })

    it('should handle null logo from API', async () => {
      vi.mocked(producerApi.getLogo).mockResolvedValue({
        data: {
          agency_logo_url: null,
          agency_logo_thumbnail_url: null,
        },
      })

      const { logoUrl, thumbnailUrl, hasLogo, fetchLogo } = useAgencyLogo()

      await fetchLogo()
      await flushPromises()

      expect(logoUrl.value).toBeNull()
      expect(thumbnailUrl.value).toBeNull()
      expect(hasLogo.value).toBe(false)
    })
  })

  describe('uploadLogo', () => {
    it('should upload logo successfully', async () => {
      vi.mocked(producerApi.uploadLogo).mockResolvedValue({
        data: {
          id: 1,
          type: 'agency',
          display_name: 'Test Agency',
          agency_logo_url: 'http://example.com/new-logo.jpg',
          agency_logo_thumbnail_url: 'http://example.com/new-logo-thumb.jpg',
          agency_name: 'Test Agency',
          first_name: null,
          last_name: null,
          bio: null,
          profile_photo_url: null,
          thumbnail_url: null,
          created_at: '2024-01-01T00:00:00Z',
          updated_at: '2024-01-01T00:00:00Z',
        },
        message: "Logo de l'agence mis à jour",
      })

      const { logoUrl, thumbnailUrl, uploadLogo, isUploading } = useAgencyLogo()

      const file = new File(['test'], 'logo.jpg', { type: 'image/jpeg' })

      const promise = uploadLogo(file)
      expect(isUploading.value).toBe(true)

      const result = await promise
      await flushPromises()

      expect(isUploading.value).toBe(false)
      expect(logoUrl.value).toBe('http://example.com/new-logo.jpg')
      expect(thumbnailUrl.value).toBe('http://example.com/new-logo-thumb.jpg')
      expect(result.success).toBe(true)
      expect(result.message).toBe("Logo de l'agence mis à jour")
    })

    it('should handle upload error', async () => {
      vi.mocked(producerApi.uploadLogo).mockRejectedValue(new Error('Upload failed'))

      const { uploadLogo, error } = useAgencyLogo()

      const file = new File(['test'], 'logo.jpg', { type: 'image/jpeg' })

      const result = await uploadLogo(file)
      await flushPromises()

      expect(result.success).toBe(false)
      expect(error.value).toBe('Une erreur est survenue. Veuillez réessayer.')
    })
  })

  describe('deleteLogo', () => {
    it('should delete logo successfully', async () => {
      vi.mocked(producerApi.deleteLogo).mockResolvedValue({
        data: {
          id: 1,
          type: 'agency',
          display_name: 'Test Agency',
          agency_logo_url: null,
          agency_logo_thumbnail_url: null,
          agency_name: 'Test Agency',
          first_name: null,
          last_name: null,
          bio: null,
          profile_photo_url: null,
          thumbnail_url: null,
          created_at: '2024-01-01T00:00:00Z',
          updated_at: '2024-01-01T00:00:00Z',
        },
        message: "Logo de l'agence supprimé",
      })

      const { logoUrl, thumbnailUrl, deleteLogo, isDeleting, hasLogo } = useAgencyLogo()

      // Set initial logo state
      logoUrl.value = 'http://example.com/logo.jpg'
      thumbnailUrl.value = 'http://example.com/logo-thumb.jpg'

      const promise = deleteLogo()
      expect(isDeleting.value).toBe(true)

      const result = await promise
      await flushPromises()

      expect(isDeleting.value).toBe(false)
      expect(logoUrl.value).toBeNull()
      expect(thumbnailUrl.value).toBeNull()
      expect(hasLogo.value).toBe(false)
      expect(result.success).toBe(true)
      expect(result.message).toBe("Logo de l'agence supprimé")
    })

    it('should handle delete error', async () => {
      vi.mocked(producerApi.deleteLogo).mockRejectedValue(new Error('Delete failed'))

      const { deleteLogo, error } = useAgencyLogo()

      const result = await deleteLogo()
      await flushPromises()

      expect(result.success).toBe(false)
      expect(error.value).toBe('Une erreur est survenue. Veuillez réessayer.')
    })
  })

  describe('validateFile', () => {
    it('should accept valid JPEG file', () => {
      const { validateFile } = useAgencyLogo()

      const file = new File(['test'], 'logo.jpg', { type: 'image/jpeg' })
      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('should accept valid PNG file', () => {
      const { validateFile } = useAgencyLogo()

      const file = new File(['test'], 'logo.png', { type: 'image/png' })
      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('should reject GIF file', () => {
      const { validateFile } = useAgencyLogo()

      const file = new File(['test'], 'logo.gif', { type: 'image/gif' })
      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le fichier doit être au format JPG ou PNG')
    })

    it('should reject WebP file', () => {
      const { validateFile } = useAgencyLogo()

      const file = new File(['test'], 'logo.webp', { type: 'image/webp' })
      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le fichier doit être au format JPG ou PNG')
    })

    it('should reject file over 2MB', () => {
      const { validateFile } = useAgencyLogo()

      // Create a file larger than 2MB
      const largeContent = new Uint8Array(3 * 1024 * 1024) // 3MB
      const file = new File([largeContent], 'logo.jpg', { type: 'image/jpeg' })
      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le logo ne peut pas dépasser 2 Mo')
    })

    it('should accept file exactly 2MB', () => {
      const { validateFile } = useAgencyLogo()

      // Create a file exactly 2MB
      const content = new Uint8Array(2 * 1024 * 1024)
      const file = new File([content], 'logo.jpg', { type: 'image/jpeg' })
      const result = validateFile(file)

      expect(result.valid).toBe(true)
    })

    it('should reject invalid file before API call', async () => {
      const { uploadLogo, error } = useAgencyLogo()

      const file = new File(['test'], 'logo.gif', { type: 'image/gif' })
      const result = await uploadLogo(file)

      expect(producerApi.uploadLogo).not.toHaveBeenCalled()
      expect(result.success).toBe(false)
      expect(error.value).toBe('Le fichier doit être au format JPG ou PNG')
    })

    it('should reject oversized file before API call', async () => {
      const { uploadLogo, error } = useAgencyLogo()

      const largeContent = new Uint8Array(3 * 1024 * 1024)
      const file = new File([largeContent], 'logo.jpg', { type: 'image/jpeg' })
      const result = await uploadLogo(file)

      expect(producerApi.uploadLogo).not.toHaveBeenCalled()
      expect(result.success).toBe(false)
      expect(error.value).toBe('Le logo ne peut pas dépasser 2 Mo')
    })
  })
})
