import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  LOCATION_DENIED_MESSAGE,
  LOCATION_UNAVAILABLE_MESSAGE,
  LOCATION_UNSUPPORTED_MESSAGE,
  useShareLocation,
} from '../composables/useShareLocation'
import { useShareLocationDialog } from '../composables/useShareLocationDialog'

type Success = (position: { coords: { latitude: number; longitude: number; accuracy: number } }) => void
type Failure = (error: { code: number }) => void

function stubGeolocation(
  behaviour: (success: Success, failure: Failure, options: PositionOptions) => void,
) {
  const getCurrentPosition = vi.fn(behaviour)
  Object.defineProperty(navigator, 'geolocation', {
    configurable: true,
    value: { getCurrentPosition },
  })
  return getCurrentPosition
}

afterEach(() => {
  Object.defineProperty(navigator, 'geolocation', { configurable: true, value: undefined })
})

describe('useShareLocation', () => {
  it('succès : renvoie la position, précision arrondie à l’entier, avec les options attendues', async () => {
    // Réponse asynchrone, comme le navigateur : l'état « localisation en cours » est observable
    const getCurrentPosition = stubGeolocation((success) =>
      queueMicrotask(() =>
        success({ coords: { latitude: 6.366667, longitude: 2.433333, accuracy: 14.6 } }),
      ),
    )
    const { locate, isLocating, error } = useShareLocation()

    const promise = locate()
    expect(isLocating.value).toBe(true)

    await expect(promise).resolves.toEqual({ lat: 6.366667, lng: 2.433333, accuracy_m: 15 })
    expect(isLocating.value).toBe(false)
    expect(error.value).toBeNull()
    expect(getCurrentPosition.mock.calls[0]![2]).toEqual({
      enableHighAccuracy: true,
      timeout: 15000,
      maximumAge: 0,
    })
  })

  it('refus de l’autorisation : message dédié', async () => {
    stubGeolocation((_success, failure) => failure({ code: 1 }))
    const { locate, isLocating, error } = useShareLocation()

    await expect(locate()).resolves.toBeNull()
    expect(error.value).toBe(
      'Autorisez la localisation dans votre navigateur, ou écrivez votre quartier et un point de repère.',
    )
    expect(error.value).toBe(LOCATION_DENIED_MESSAGE)
    expect(isLocating.value).toBe(false)
  })

  it('délai dépassé ou position indisponible : message « introuvable »', async () => {
    for (const code of [2, 3]) {
      stubGeolocation((_success, failure) => failure({ code }))
      const { locate, error } = useShareLocation()

      await expect(locate()).resolves.toBeNull()
      expect(error.value).toBe(
        'Position introuvable pour le moment. Réessayez, ou écrivez votre quartier et un point de repère.',
      )
      expect(error.value).toBe(LOCATION_UNAVAILABLE_MESSAGE)
    }
  })

  it('API absente : message dédié, sans appel', async () => {
    Object.defineProperty(navigator, 'geolocation', { configurable: true, value: undefined })
    const { locate, isLocating, error } = useShareLocation()

    await expect(locate()).resolves.toBeNull()
    expect(isLocating.value).toBe(false)
    expect(error.value).toBe(
      'Votre navigateur ne permet pas d\'envoyer une position. Écrivez votre quartier et un point de repère.',
    )
    expect(error.value).toBe(LOCATION_UNSUPPORTED_MESSAGE)
  })

  it('une nouvelle tentative efface l’erreur précédente', async () => {
    stubGeolocation((_success, failure) => failure({ code: 1 }))
    const { locate, error } = useShareLocation()
    await locate()
    expect(error.value).not.toBeNull()

    stubGeolocation((success) => success({ coords: { latitude: 1, longitude: 2, accuracy: 5 } }))
    await locate()
    expect(error.value).toBeNull()
  })
})

describe('useShareLocationDialog', () => {
  const position = { coords: { latitude: 6.37, longitude: 2.43, accuracy: 20 } }

  it('ne demande la position qu’à la confirmation, puis envoie et ferme', async () => {
    const getCurrentPosition = stubGeolocation((success) => success(position))
    const send = vi.fn().mockResolvedValue(null)
    const dialog = useShareLocationDialog(send)

    dialog.open()
    expect(dialog.isOpen.value).toBe(true)
    expect(getCurrentPosition).not.toHaveBeenCalled()

    await dialog.confirm()
    expect(send).toHaveBeenCalledWith({ lat: 6.37, lng: 2.43, accuracy_m: 20 })
    expect(dialog.isOpen.value).toBe(false)
  })

  it('échec d’envoi : le dialogue reste ouvert avec l’erreur', async () => {
    stubGeolocation((success) => success(position))
    const dialog = useShareLocationDialog(vi.fn().mockResolvedValue('Chat verrouillé'))

    dialog.open()
    await dialog.confirm()

    expect(dialog.isOpen.value).toBe(true)
    expect(dialog.error.value).toBe('Chat verrouillé')
    expect(dialog.isBusy.value).toBe(false)
  })

  it('refus du navigateur : aucun envoi, erreur affichée', async () => {
    stubGeolocation((_success, failure) => failure({ code: 1 }))
    const send = vi.fn()
    const dialog = useShareLocationDialog(send)

    dialog.open()
    await dialog.confirm()

    expect(send).not.toHaveBeenCalled()
    expect(dialog.isOpen.value).toBe(true)
    expect(dialog.error.value).toBe(LOCATION_DENIED_MESSAGE)
  })
})
