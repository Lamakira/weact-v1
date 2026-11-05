import { computed, ref } from 'vue'
import { useShareLocation } from './useShareLocation'
import type { SharedLocation } from '../types'

/**
 * Logique du dialogue « Envoyer ma position », partagée par le fil de conversation et le chat
 * de booking : ouverture, localisation (après le clic de confirmation), envoi, erreurs.
 *
 * @param send Envoie la position ; renvoie un message d'erreur, ou null en cas de succès.
 */
export function useShareLocationDialog(send: (location: SharedLocation) => Promise<string | null>) {
  const { isLocating, error: locateError, locate, resetError } = useShareLocation()

  const isOpen = ref(false)
  const isSubmitting = ref(false)
  const sendError = ref<string | null>(null)
  // Une fermeture invalide la localisation / l'envoi en cours (pas de fermeture tardive)
  let session = 0

  const isBusy = computed(() => isLocating.value || isSubmitting.value)
  const error = computed(() => sendError.value ?? locateError.value)

  function open(): void {
    session++
    sendError.value = null
    resetError()
    isOpen.value = true
  }

  function close(): void {
    session++
    isSubmitting.value = false
    isOpen.value = false
  }

  async function confirm(): Promise<void> {
    if (isBusy.value) return
    const current = ++session
    sendError.value = null

    const location = await locate()
    if (!location || current !== session) return

    isSubmitting.value = true
    const failure = await send(location)
    if (current !== session) return
    isSubmitting.value = false

    if (failure) {
      sendError.value = failure
      return
    }
    isOpen.value = false
  }

  return { isOpen, isBusy, error, open, close, confirm }
}
