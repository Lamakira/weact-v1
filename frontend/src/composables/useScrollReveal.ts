import { onMounted, onUnmounted, onActivated, nextTick } from 'vue'

/**
 * useScrollReveal — Applies IntersectionObserver to elements with
 * .reveal, .reveal-left, .reveal-right, or .stagger-item classes
 * within the given root selector (defaults to document).
 *
 * Adds `is-visible` class when an element enters the viewport.
 */
export function useScrollReveal(rootSelector?: string) {
  let observer: IntersectionObserver | null = null

  function init() {
    const root = rootSelector ? document.querySelector(rootSelector) : document

    const targets = (root ?? document).querySelectorAll<HTMLElement>(
      '.reveal, .reveal-left, .reveal-right, .stagger-item',
    )

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
            el.classList.add('is-visible')
            observer?.unobserve(el)
          }
        })
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    )

    targets.forEach((el) => observer!.observe(el))
  }

  /**
   * Force every reveal target to its visible state immediately, without
   * relying on the IntersectionObserver firing. Used on <keep-alive>
   * re-activation: the content was already revealed before the user left, so
   * re-playing the scroll animation (which is flaky mid page-transition and can
   * leave an element stuck hidden) is both unnecessary and buggy.
   */
  function revealAll() {
    const root = rootSelector ? document.querySelector(rootSelector) : document
    ;(root ?? document)
      .querySelectorAll<HTMLElement>('.reveal, .reveal-left, .reveal-right, .stagger-item')
      .forEach((el) => el.classList.add('is-visible'))
  }

  onMounted(() => {
    // Small delay so elements are in the DOM
    setTimeout(init, 50)
  })

  // Fires only for components cached by <keep-alive>. On return, show the
  // already-seen content at once instead of re-animating it.
  onActivated(() => {
    void nextTick(revealAll)
  })

  onUnmounted(() => {
    observer?.disconnect()
  })

  return { reinit: init }
}
