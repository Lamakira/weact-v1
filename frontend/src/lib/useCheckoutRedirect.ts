import { getCurrentInstance, onUnmounted } from 'vue'

/**
 * Back/forward-cache recovery for same-tab checkout redirects.
 *
 * iOS Safari restores the page from the bfcache when the user presses Back from
 * FedaPay: the JS state is frozen as it was just before `redirectToCheckout`
 * (« Redirection vers FedaPay… »), with no way to retry. One shared `pageshow`
 * listener calls every registered callback when `event.persisted` is true, so the
 * initiating composables/pages can reset and let the payment start again.
 *
 * Call it from setup (composable or component); the callback is unregistered on
 * unmount and the global listener removed when nobody is left.
 */
const callbacks = new Set<() => void>()

function onPageShow(event: PageTransitionEvent): void {
  if (!event.persisted) return
  for (const callback of [...callbacks]) callback()
}

export function useCheckoutRedirect(onRestored: () => void): void {
  if (callbacks.size === 0) window.addEventListener('pageshow', onPageShow)
  callbacks.add(onRestored)

  if (getCurrentInstance()) {
    onUnmounted(() => {
      callbacks.delete(onRestored)
      if (callbacks.size === 0) window.removeEventListener('pageshow', onPageShow)
    })
  }
}
