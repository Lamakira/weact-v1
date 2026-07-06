import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue'

export interface HqLightboxPhoto {
  photo_url: string | null
  large_url: string | null
}

/**
 * Shared "HQ on demand" lightbox behaviour.
 *
 * Shows the large (display) variant by default and reveals the full original
 * only after an explicit user action. Centralises the src-selection rule and
 * the "can view original" rule so the album grid, the candidate gallery and the
 * admin face-detail lightbox never drift, and so the `showOriginal` reset that
 * must run on every open/close/navigation lives behind a single `reset()`.
 *
 * `large_url` server-side falls back to the medium then the original, so on a
 * legacy row / pending job it may already equal the original — in which case the
 * "Voir l'original" affordance is hidden.
 */
export function useHqLightbox<T extends HqLightboxPhoto>(photo: MaybeRefOrGetter<T | null>) {
  const showOriginal = ref(false)

  const lightboxSrc = computed<string>(() => {
    const current = toValue(photo)
    if (!current) return ''

    return (showOriginal.value ? current.photo_url : current.large_url || current.photo_url) || ''
  })

  const canViewOriginal = computed<boolean>(() => {
    const current = toValue(photo)

    return !!current && !!current.large_url && current.large_url !== current.photo_url
  })

  function reset(): void {
    showOriginal.value = false
  }

  return { showOriginal, lightboxSrc, canViewOriginal, reset }
}
