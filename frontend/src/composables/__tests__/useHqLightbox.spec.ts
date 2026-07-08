import { describe, it, expect } from 'vitest'
import { ref, computed } from 'vue'
import { useHqLightbox, type HqLightboxPhoto } from '../useHqLightbox'

describe('useHqLightbox', () => {
  it('shows the large variant by default and the original after reveal', () => {
    const photo = ref({ photo_url: '/orig.jpg', large_url: '/large.webp' })
    const { showOriginal, lightboxSrc } = useHqLightbox(photo)

    expect(lightboxSrc.value).toBe('/large.webp')
    showOriginal.value = true
    expect(lightboxSrc.value).toBe('/orig.jpg')
  })

  it('falls back to the original when there is no large variant', () => {
    const photo = ref({ photo_url: '/orig.jpg', large_url: null })
    const { lightboxSrc } = useHqLightbox(photo)

    expect(lightboxSrc.value).toBe('/orig.jpg')
  })

  it('returns an empty string when there is no selected photo', () => {
    const photo = ref<HqLightboxPhoto | null>(null)
    const { lightboxSrc, canViewOriginal } = useHqLightbox(photo)

    expect(lightboxSrc.value).toBe('')
    expect(canViewOriginal.value).toBe(false)
  })

  it('hides "view original" when the large variant already equals the original', () => {
    const photo = ref({ photo_url: '/orig.jpg', large_url: '/orig.jpg' })
    const { canViewOriginal } = useHqLightbox(photo)

    expect(canViewOriginal.value).toBe(false)
  })

  it('offers "view original" when the large variant differs from the original', () => {
    const photo = ref({ photo_url: '/orig.jpg', large_url: '/large.webp' })
    const { canViewOriginal } = useHqLightbox(photo)

    expect(canViewOriginal.value).toBe(true)
  })

  it('reset() collapses the original view', () => {
    const photo = ref({ photo_url: '/orig.jpg', large_url: '/large.webp' })
    const { showOriginal, reset } = useHqLightbox(photo)

    showOriginal.value = true
    reset()
    expect(showOriginal.value).toBe(false)
  })

  it('accepts a getter/computed source (not only a writable ref)', () => {
    const source = ref({ photo_url: '/o.jpg', large_url: '/l.webp' })
    const { lightboxSrc } = useHqLightbox(computed(() => source.value))

    expect(lightboxSrc.value).toBe('/l.webp')
  })
})
