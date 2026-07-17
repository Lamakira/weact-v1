import { onMounted, onUnmounted, type Ref } from 'vue'

/**
 * Classes this composable reveals. main.css MUST key the revealed state of each
 * one on `[data-scroll-reveal-seen='true']` too — see the "Scroll Reveal" and
 * "Stagger Cards" sections there, and the contract test in
 * `__tests__/useScrollReveal.spec.ts`.
 */
export const REVEAL_TARGET_CLASSES = ['reveal', 'reveal-left', 'reveal-right', 'stagger-item'] as const

const REVEAL_TARGET_SELECTOR = REVEAL_TARGET_CLASSES.map((className) => `.${className}`).join(', ')

const STAGGER_STEP_MS = 80

/**
 * The property every reveal in main.css fades, and the one that identifies it: a
 * reveal also moves `transform`, but a hover on the same node can move it too,
 * whereas nothing else touches opacity.
 */
const REVEAL_TRANSITION_PROPERTY = 'opacity'

/**
 * How long a reveal takes to settle: 0.4s in main.css for every reveal class,
 * plus margin. Only sizes the fallback below, where running late costs nothing
 * and running early would cut a reveal short.
 */
const REVEAL_SETTLE_MS = 400 + 150

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
  const pendingDelayDrops = new Set<ReturnType<typeof setTimeout>>()

  function resolveRoot(): ParentNode | null {
    return root ? root.value : document
  }

  /**
   * Hold `el` back behind its siblings, then drop that delay once the reveal is
   * done with it. The delay must not outlive the reveal it staggers: it sits on
   * the very node that also carries the card's own transitions (FaceCard's root
   * RouterLink is the .stagger-item), so a leftover 1.2s delay would make the
   * last card of a grid react a second late to every hover.
   */
  function staggerReveal(el: HTMLElement): void {
    const siblings = Array.from(el.parentElement?.children ?? []).filter((c) =>
      c.classList.contains('stagger-item'),
    )
    const delayMs = siblings.indexOf(el) * STAGGER_STEP_MS
    el.style.transitionDelay = `${delayMs}ms`

    const drop = (): void => {
      el.style.removeProperty('transition-delay')
      el.removeEventListener('transitionend', onRevealSettled)
      el.removeEventListener('transitioncancel', onRevealSettled)
      clearTimeout(fallback)
      pendingDelayDrops.delete(fallback)
    }

    const onRevealSettled = (event: TransitionEvent): void => {
      // Ignore what bubbles up from a child (the card photo's zoom), and anything
      // on this node that is not the reveal. Only the property tells the two
      // apart reliably: main.css pins this card's transition-property to
      // `opacity, transform` today — its reveal rules are unlayered, so they
      // outrank the `transition-all` utility sharing the element — but that
      // cascade is invisible from here and one @layer away from letting a hover
      // fire on this handler.
      if (event.target !== el) return
      if (event.propertyName !== REVEAL_TRANSITION_PROPERTY) return
      drop()
    }

    // Cancelled counts as settled: opening a profile mid-reveal detaches the
    // cached card, which kills the transition rather than ending it. But neither
    // event is guaranteed — a reveal that never transitions fires neither — and
    // the marker in reveal() means this node is never revealed again, so there is
    // no second chance to take the delay back off. Hence a fallback that runs
    // whatever happens.
    const fallback = setTimeout(drop, delayMs + REVEAL_SETTLE_MS)
    pendingDelayDrops.add(fallback)
    el.addEventListener('transitionend', onRevealSettled)
    el.addEventListener('transitioncancel', onRevealSettled)
  }

  function reveal(el: HTMLElement): void {
    if (el.classList.contains('stagger-item')) staggerReveal(el)

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
    // Same for the stagger fallbacks — their elements are going away with the
    // component, so there is no delay left to take back off. Note keep-alive
    // deactivation does NOT come through here: those timers keep running, which
    // is exactly what un-strands the delay on a card cached mid-reveal.
    pendingDelayDrops.forEach(clearTimeout)
    pendingDelayDrops.clear()
    observer?.disconnect()
  })

  return { reinit: init }
}
