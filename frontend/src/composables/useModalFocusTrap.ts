import { nextTick, ref, type Ref } from 'vue'

/**
 * Gestion de focus pour modales accessibles (pattern AdminFaceSubscriptionSection) :
 * - `prepareModalFocus` mémorise l'élément actif puis focus la racine de la modale ;
 * - `restoreFocus` rend le focus à l'élément d'origine s'il est toujours dans le DOM ;
 * - `trapModalFocus` boucle Tab / Shift+Tab à l'intérieur de la modale.
 *
 * Une instance par périmètre de modales : les modales d'un même composant ne sont
 * jamais ouvertes simultanément, elles peuvent donc partager `lastFocusedElement`.
 */
export function useModalFocusTrap() {
  const lastFocusedElement = ref<HTMLElement | null>(null)

  async function prepareModalFocus(modalRef: Ref<HTMLDivElement | null>): Promise<void> {
    lastFocusedElement.value =
      document.activeElement instanceof HTMLElement ? document.activeElement : null
    await nextTick()
    modalRef.value?.focus()
  }

  function restoreFocus(): void {
    if (lastFocusedElement.value && document.contains(lastFocusedElement.value)) {
      lastFocusedElement.value.focus({ preventScroll: true })
    }
    lastFocusedElement.value = null
  }

  function trapModalFocus(event: KeyboardEvent, root: HTMLDivElement | null): void {
    if (!root) return

    const focusable = Array.from(
      root.querySelectorAll<HTMLElement>(
        'button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ),
    ).filter((element) => element.offsetParent !== null)

    if (focusable.length === 0) return

    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (!first || !last) return

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first.focus()
    }
  }

  return {
    lastFocusedElement,
    prepareModalFocus,
    restoreFocus,
    trapModalFocus,
  }
}
