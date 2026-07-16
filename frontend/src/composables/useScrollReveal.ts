import { onMounted, onUnmounted, type Ref } from 'vue'

/**
 * Classes this composable reveals. main.css MUST key the revealed state of each
 * one on `[data-scroll-reveal-seen='true']` too — see the "Scroll Reveal" and
 * "Stagger Cards" sections there, and the contract test in
 * `__tests__/useScrollReveal.spec.ts`.
 */
export const REVEAL_TARGET_CLASSES = ['reveal', 'reveal-left', 'reveal-right', 'stagger-item'] as const

const REVEAL_TARGET_SELECTOR = REVEAL_TARGET_CLASSES.map((className) => `.${className}`).join(', ')

/**
 * useScrollReveal — Applies IntersectionObserver to elements with
 * .reveal, .reveal-left, .reveal-right, or .stagger-item classes
 * within `root` (defaults to the whole document).
 *
 * A revealed element is marked `data-scroll-reveal-seen="true"` — and that marker,
 * not the `is-visible` class added alongside it, is what keeps it visible: Vue
 * rewrites `class` wholesale whenever a binding on the same element changes, so an
 * imperatively-added class does not survive (main.css keys the revealed state on
 * both — see the comment there). The marker also tells init() not to observe that
 * element again, which would replay the stagger on an already-seen card.
 *
 * @param root the component's own root element, as a template ref. Pass it when the
 *   component is cached by `<keep-alive>`: while deactivated its subtree is detached
 *   from the document, so a document-wide query would scan whatever view replaced it
 *   and leave the cached listing's own cards unobserved — permanently hidden on
 *   return. Omit it for views that are never cached.
 */
export function useScrollReveal(root?: Ref<HTMLElement | null>) {
  let observer: IntersectionObserver | null = null

  function resolveRoot(): ParentNode | null {
    return root ? root.value : document
  }

  function reveal(el: HTMLElement): void {
    // For stagger items, delay based on their position among siblings
    if (el.classList.contains('stagger-item')) {
      const siblings = Array.from(el.parentElement?.children ?? []).filter((c) =>
        c.classList.contains('stagger-item'),
      )
      const index = siblings.indexOf(el)
      el.style.transitionDelay = `${index * 80}ms`

      // The stagger delay must not outlive the reveal it staggers: it sits on the
      // very node that also carries the card's hover transition (FaceCard's root
      // RouterLink is the .stagger-item), so a leftover 1.2s delay would make the
      // last card of a grid react a second late to every hover. Drop it once the
      // reveal is over — cancelled counts, since opening a profile mid-reveal
      // detaches the cached card and kills its transition, and it is never
      // revealed again (the marker below keeps it out of the observer). Ignore
      // events bubbling up from children (the card photo's zoom).
      const dropStaggerDelay = (event: Event): void => {
        if (event.target !== el) return
        el.style.removeProperty('transition-delay')
        el.removeEventListener('transitionend', dropStaggerDelay)
        el.removeEventListener('transitioncancel', dropStaggerDelay)
      }
      el.addEventListener('transitionend', dropStaggerDelay)
      el.addEventListener('transitioncancel', dropStaggerDelay)
    }

    el.dataset.scrollRevealSeen = 'true'
    el.classList.add('is-visible')
    observer?.unobserve(el)
  }

  function init() {
    const container = resolveRoot()

    // Before mount, or once unmounted, there is nothing to scan: bail before
    // touching the observer rather than tearing down a live one for nothing.
    if (!container) return

    // Disconnect any previous observer before creating a new one. init() runs
    // again on every reinit() (e.g. after each async data load, and repeatedly
    // while a listing is kept alive under <keep-alive>), and onUnmounted does NOT
    // fire on keep-alive deactivation — so without this each call would leak an
    // observer still holding the now-detached grid nodes.
    observer?.disconnect()
    observer = null

    const targets = container.querySelectorAll<HTMLElement>(REVEAL_TARGET_SELECTOR)

    if (targets.length === 0) return

    observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) reveal(entry.target as HTMLElement)
        })
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    )

    targets.forEach((el) => {
      if (el.dataset.scrollRevealSeen !== 'true') observer!.observe(el)
    })
  }

  let initTimeout: ReturnType<typeof setTimeout> | null = null

  onMounted(() => {
    // Small delay so elements are in the DOM
    initTimeout = setTimeout(init, 50)
  })

  onUnmounted(() => {
    // Clear the pending mount timer too: left alive it would run init() after
    // the component (or a test environment) is gone.
    if (initTimeout) clearTimeout(initTimeout)
    observer?.disconnect()
  })

  return { reinit: init }
}
