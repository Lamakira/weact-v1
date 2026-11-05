import { ref } from 'vue'
import type { SharedLocation } from '../types'

const GEOLOCATION_TIMEOUT_MS = 15_000

export const LOCATION_DENIED_MESSAGE =
  'Autorisez la localisation dans votre navigateur, ou écrivez votre quartier et un point de repère.'
export const LOCATION_UNAVAILABLE_MESSAGE =
  'Position introuvable pour le moment. Réessayez, ou écrivez votre quartier et un point de repère.'
export const LOCATION_UNSUPPORTED_MESSAGE =
  'Votre navigateur ne permet pas d\'envoyer une position. Écrivez votre quartier et un point de repère.'

/**
 * Position GPS instantanée du navigateur (jamais de suivi en direct).
 * L'autorisation du navigateur n'est demandée qu'à l'appel de `locate`.
 */
export function useShareLocation() {
  const isLocating = ref(false)
  const error = ref<string | null>(null)

  function locate(): Promise<SharedLocation | null> {
    error.value = null

    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      error.value = LOCATION_UNSUPPORTED_MESSAGE
      return Promise.resolve(null)
    }

    isLocating.value = true

    return new Promise((resolve) => {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          isLocating.value = false
          resolve({
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy_m: Number.isFinite(position.coords.accuracy)
              ? Math.round(position.coords.accuracy)
              : null,
          })
        },
        (failure) => {
          isLocating.value = false
          // 1 = PERMISSION_DENIED (aussi renvoyé quand Permissions-Policy bloque l'API)
          error.value = failure.code === 1 ? LOCATION_DENIED_MESSAGE : LOCATION_UNAVAILABLE_MESSAGE
          resolve(null)
        },
        { enableHighAccuracy: true, timeout: GEOLOCATION_TIMEOUT_MS, maximumAge: 0 },
      )
    })
  }

  function resetError(): void {
    error.value = null
  }

  return { isLocating, error, locate, resetError }
}
