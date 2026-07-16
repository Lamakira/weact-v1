/**
 * useScrollReveal — timer hygiene, observer reuse, and the durability of a reveal.
 *
 * The composable schedules `setTimeout(init, 50)` at mount. Left uncleared,
 * that timer outlives an unmounted component (and even the test file's
 * environment — the source of a flaky "Unhandled Error" in the suite): init()
 * then touches a torn-down document. Same defect class as the admin search
 * debounce (review finding #9): timers must die with their component.
 *
 * Also locks in the keep-alive fix #3: init() disconnects the previous
 * observer before creating a new one (reinit() runs after every data load,
 * and onUnmounted never fires while a listing is cached).
 *
 * And the vanishing cached card: a revealed element is kept visible by the
 * `data-scroll-reveal-seen` marker, because the `is-visible` class next to it is
 * wiped whenever Vue rewrites the element's class attribute (a Face card's
 * RouterLink gaining `router-link-active` when the user opens it). Half of that
 * fix lives in main.css, which happy-dom does not load — hence the stylesheet
 * contract test at the bottom.
 */
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, h, KeepAlive, nextTick, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { useScrollReveal, REVEAL_TARGET_CLASSES } from '../useScrollReveal'

class MockIntersectionObserver {
  static instances: MockIntersectionObserver[] = []
  observe = vi.fn()
  unobserve = vi.fn()
  disconnect = vi.fn()
  constructor(private callback: IntersectionObserverCallback) {
    MockIntersectionObserver.instances.push(this)
  }

  /** Simulate `el` scrolling into view. */
  enter(el: Element) {
    this.callback(
      [{ isIntersecting: true, target: el } as IntersectionObserverEntry],
      this as unknown as IntersectionObserver,
    )
  }
}

let exposedReinit: (() => void) | null = null

const Host = defineComponent({
  setup() {
    const { reinit } = useScrollReveal()
    exposedReinit = reinit
    return () => h('div', { class: 'reveal' }, 'x')
  },
})

describe('useScrollReveal', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    MockIntersectionObserver.instances = []
    exposedReinit = null
    vi.stubGlobal('IntersectionObserver', MockIntersectionObserver)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('does not run the delayed init after unmount (no leaked timer)', () => {
    const wrapper = mount(Host, { attachTo: document.body })
    wrapper.unmount()

    // init() always starts by querying the document — spy on that to observe
    // whether the leaked timer still runs it (an observer would not even be
    // constructed post-unmount, since the .reveal node left the DOM).
    const querySpy = vi.spyOn(document, 'querySelectorAll')
    vi.advanceTimersByTime(60)

    // The 50ms mount timer must have been cleared with the component.
    expect(querySpy).not.toHaveBeenCalled()
    querySpy.mockRestore()
  })

  it('runs init 50ms after mount while mounted (control)', () => {
    const wrapper = mount(Host, { attachTo: document.body })

    vi.advanceTimersByTime(60)

    expect(MockIntersectionObserver.instances).toHaveLength(1)
    expect(MockIntersectionObserver.instances[0].observe).toHaveBeenCalled()
    wrapper.unmount()
  })

  it('reinit() disconnects the previous observer before creating a new one (fix #3)', () => {
    const wrapper = mount(Host, { attachTo: document.body })
    vi.advanceTimersByTime(60)
    expect(MockIntersectionObserver.instances).toHaveLength(1)

    exposedReinit?.()

    expect(MockIntersectionObserver.instances).toHaveLength(2)
    expect(MockIntersectionObserver.instances[0].disconnect).toHaveBeenCalled()
    wrapper.unmount()
  })

  describe('a revealed element', () => {
    const Grid = defineComponent({
      setup() {
        const root = ref<HTMLElement | null>(null)
        const { reinit } = useScrollReveal(root)
        exposedReinit = reinit
        return () =>
          h('div', { ref: root }, [
            h('a', { class: 'stagger-item', 'data-testid': 'card-1' }, 'Adjoua'),
            h('a', { class: 'stagger-item', 'data-testid': 'card-2' }, 'Koffi'),
          ])
      },
    })

    it('is marked with the wipe-proof marker and left out of the observer', () => {
      const wrapper = mount(Grid, { attachTo: document.body })
      vi.advanceTimersByTime(60)
      const card = wrapper.get('[data-testid="card-1"]').element as HTMLElement

      MockIntersectionObserver.instances[0].enter(card)

      expect(card.dataset.scrollRevealSeen).toBe('true')
      expect(card.classList.contains('is-visible')).toBe(true)
      expect(MockIntersectionObserver.instances[0].unobserve).toHaveBeenCalledWith(card)

      // A later reinit() (a fetch resolving, a filter change) must not observe it
      // again: it is already visible, and re-observing would replay the stagger.
      exposedReinit?.()
      expect(MockIntersectionObserver.instances[1].observe).not.toHaveBeenCalledWith(card)
      wrapper.unmount()
    })

    it('drops its stagger delay once the reveal transition has played', () => {
      const wrapper = mount(Grid, { attachTo: document.body })
      vi.advanceTimersByTime(60)
      const secondCard = wrapper.get('[data-testid="card-2"]').element as HTMLElement

      MockIntersectionObserver.instances[0].enter(secondCard)
      expect(secondCard.style.transitionDelay).toBe('80ms')

      // A child's transition (the card photo's zoom) bubbles up but is not the
      // reveal: the delay must still be in force for the reveal itself.
      secondCard.firstChild?.dispatchEvent(new Event('transitionend', { bubbles: true }))
      expect(secondCard.style.transitionDelay).toBe('80ms')

      // The reveal has now played. Left in place, the delay would also postpone
      // every later transition on this node — the card's own hover.
      secondCard.dispatchEvent(new Event('transitionend'))
      expect(secondCard.style.transitionDelay).toBe('')
      wrapper.unmount()
    })

    it('drops its stagger delay when the reveal is cancelled mid-flight', () => {
      const wrapper = mount(Grid, { attachTo: document.body })
      vi.advanceTimersByTime(60)
      const secondCard = wrapper.get('[data-testid="card-2"]').element as HTMLElement

      MockIntersectionObserver.instances[0].enter(secondCard)
      expect(secondCard.style.transitionDelay).toBe('80ms')

      // Opening a profile while the card is still fading in detaches the cached
      // subtree, which cancels the transition instead of ending it. The card is
      // never revealed again — the marker keeps it out of the observer — so this
      // is the last chance to drop the delay before it slows every hover.
      secondCard.dispatchEvent(new Event('transitioncancel'))
      expect(secondCard.style.transitionDelay).toBe('')
      wrapper.unmount()
    })

    it('stays revealed when Vue rewrites its class and the cached listing comes back', async () => {
      const listingActive = ref(true)

      const FacesListing = defineComponent({
        name: 'PublicFacesView',
        setup: Grid.setup,
      })

      const Detail = defineComponent({
        setup: () => () => h('button', { class: 'reveal' }, 'Retour aux talents'),
      })

      const Harness = defineComponent({
        setup: () => () =>
          h(KeepAlive, { include: ['PublicFacesView'] }, () =>
            listingActive.value ? h(FacesListing) : h(Detail),
          ),
      })

      const wrapper = mount(Harness, { attachTo: document.body })
      vi.advanceTimersByTime(60)
      const clickedCard = wrapper.get('[data-testid="card-1"]').element as HTMLElement
      const unseenCard = wrapper.get('[data-testid="card-2"]').element as HTMLElement
      MockIntersectionObserver.instances[0].enter(clickedCard)

      // Opening the profile gives the clicked card's RouterLink an active class;
      // Vue rewrites the whole class attribute, dropping `is-visible`. It then
      // deactivates (but does not unmount) the public listing.
      clickedCard.classList.remove('is-visible')
      listingActive.value = false
      await nextTick()

      // The app's "Retour aux talents" navigation reactivates the cached list.
      listingActive.value = true
      await nextTick()

      // The marker survived the class rewrite, so main.css still shows the card.
      expect(clickedCard.dataset.scrollRevealSeen).toBe('true')
      expect(unseenCard.dataset.scrollRevealSeen).toBeUndefined()
      // Reactivation is not a reason to rebuild anything: the below-fold card is
      // still registered with the observer built at mount.
      expect(MockIntersectionObserver.instances).toHaveLength(1)
      expect(MockIntersectionObserver.instances[0].observe).toHaveBeenCalledWith(unseenCard)
      wrapper.unmount()
    })

    it('is found through the root ref while the listing is cached off-DOM', async () => {
      const listingActive = ref(true)

      const FacesListing = defineComponent({
        name: 'PublicFacesView',
        setup: Grid.setup,
      })

      const Detail = defineComponent({
        setup: () => () => h('button', { class: 'reveal' }, 'Retour aux talents'),
      })

      const Harness = defineComponent({
        setup: () => () =>
          h(KeepAlive, { include: ['PublicFacesView'] }, () =>
            listingActive.value ? h(FacesListing) : h(Detail),
          ),
      })

      const wrapper = mount(Harness, { attachTo: document.body })
      vi.advanceTimersByTime(60)
      const cachedCard = wrapper.get('[data-testid="card-1"]').element as HTMLElement

      listingActive.value = false
      await nextTick()

      // An in-flight fetch resolving after the user left reinits the cached
      // listing. Its subtree is detached, so a document-wide scan would find the
      // detail page's `.reveal` instead and leave these cards unobserved forever.
      exposedReinit?.()

      const rebuilt = MockIntersectionObserver.instances[1]
      expect(rebuilt.observe).toHaveBeenCalledWith(cachedCard)
      expect(rebuilt.observe).not.toHaveBeenCalledWith(wrapper.get('.reveal').element)
      wrapper.unmount()
    })
  })

  it('is backed by a stylesheet that keys the revealed state on the marker', () => {
    // happy-dom loads no stylesheet, so the half of the fix that lives in CSS can
    // only be pinned here: every reveal class must stay visible on the marker
    // alone, since `is-visible` next to it does not survive a class rewrite.
    // (Read through node:url — happy-dom's global URL does not keep the file
    // scheme — and never through cwd, which follows wherever vitest was started.)
    const mainCss = readFileSync(
      join(dirname(fileURLToPath(import.meta.url)), '../../assets/main.css'),
      'utf-8',
    )

    REVEAL_TARGET_CLASSES.forEach((className) => {
      expect(mainCss).toMatch(new RegExp(`\\.${className}\\[data-scroll-reveal-seen=['"]true['"]\\]`))
    })
  })
})
