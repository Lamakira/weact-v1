import { onMounted, onUnmounted } from 'vue'

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
    // Disconnect any previous observer before creating a new one. init() runs
    // again on every reinit() (e.g. after each async data load, and repeatedly
    // while a listing is kept alive under <keep-alive>), and onUnmounted does NOT
    // fire on keep-alive deactivation — so without this each call would leak an
    // observer still holding the now-detached grid nodes.
    observer?.disconnect()
    observer = null

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
