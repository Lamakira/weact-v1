import { nextTick, onActivated, onDeactivated, onMounted, onUnmounted } from 'vue'

const REVEAL_TARGET_SELECTOR = '.reveal, .reveal-left, .reveal-right, .stagger-item'

/**
 * useScrollReveal — Applies IntersectionObserver to elements with
 * .reveal, .reveal-left, .reveal-right, or .stagger-item classes
 * within the given root selector (defaults to document).
 *
 * Adds `is-visible` class when an element enters the viewport.
 */
export function useScrollReveal(rootSelector?: string) {
  let observer: IntersectionObserver | null = null

  function resolveRoot(): ParentNode | null {
    if (!rootSelector) return document

    try {
      return document.querySelector(rootSelector)
    } catch {
      return null
    }
  }

  function init() {
    // Disconnect any previous observer before creating a new one. init() runs
    // again on every reinit() (e.g. after each async data load, and repeatedly
    // while a listing is kept alive under <keep-alive>), and onUnmounted does NOT
    // fire on keep-alive deactivation — so without this each call would leak an
    // observer still holding the now-detached grid nodes.
    observer?.disconnect()
    observer = null

    const root = resolveRoot()

    if (!root) return

    const targets = root.querySelectorAll<HTMLElement>(REVEAL_TARGET_SELECTOR)

    if (targets.length === 0) return

    observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const el = entry.target as HTMLElement
            // For stagger items, delay based on their position among siblings
            if (el.classList.contains('stagger-item')) {
              const siblings = Array.from(el.parentElement?.children ?? []).filter((c) =>
                c.classList.contains('stagger-item'),
              )
              const index = siblings.indexOf(el)
              el.style.transitionDelay = `${index * 80}ms`
            }
            el.dataset.scrollRevealSeen = 'true'
            el.classList.add('is-visible')
            observer?.unobserve(el)
          }
        })
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    )

    targets.forEach((el) => {
      if (el.dataset.scrollRevealSeen !== 'true') observer!.observe(el)
    })
  }

  function restoreRevealedTargets(): void {
    const root = resolveRoot()
    if (!root) return

    const targets = root.querySelectorAll<HTMLElement>(
      `${REVEAL_TARGET_SELECTOR.split(', ').join('[data-scroll-reveal-seen="true"], ')}[data-scroll-reveal-seen="true"]`,
    )

    targets.forEach((el) => {
      // Restore the already-seen state synchronously without replaying the
      // stagger delay/transition, then hand styling back to the stylesheet.
      const previousTransition = el.style.transition
      const previousTransitionDelay = el.style.transitionDelay
      el.style.transition = 'none'
      el.style.transitionDelay = '0ms'
      el.classList.add('is-visible')
      void el.offsetHeight
      el.style.transition = previousTransition
      el.style.transitionDelay = previousTransitionDelay
    })
  }

  let initTimeout: ReturnType<typeof setTimeout> | null = null
  let activationCount = 0
  let activationGeneration = 0
  let isActive = false

  onMounted(() => {
    // Small delay so elements are in the DOM
    initTimeout = setTimeout(init, 50)
  })

  onActivated(() => {
    isActive = true
    const isReactivation = activationCount > 0
    activationCount++
    const generation = ++activationGeneration
    if (!isReactivation) return

    // Cached listing content was already seen before navigation. Restore only
    // those targets; unseen below-fold cards remain observer-controlled.
    void nextTick(() => {
      if (!isActive || generation !== activationGeneration) return
      if (!observer) init()
      restoreRevealedTargets()
    })
  })

  onDeactivated(() => {
    isActive = false
    activationGeneration++
    if (initTimeout) {
      clearTimeout(initTimeout)
      initTimeout = null
    }
  })

  onUnmounted(() => {
    isActive = false
    // Clear the pending mount timer too: left alive it would run init() after
    // the component (or a test environment) is gone.
    if (initTimeout) clearTimeout(initTimeout)
    observer?.disconnect()
  })

  return { reinit: init }
}
