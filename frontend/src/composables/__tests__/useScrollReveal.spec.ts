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
 * `data-scroll-reveal-seen` marker — a data attribute rather than a class, because
 * Vue rewrites the class attribute wholesale whenever a binding on the same
 * element changes (a Face card's RouterLink gaining `router-link-active` when the
 * user opens it). Half of that mechanism lives in main.css, so the contract test
 * at the bottom feeds the real stylesheet to happy-dom and asserts the cascade.
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

/**
 * A transition event carrying the property it is about. Built by hand because
 * happy-dom's `TransitionEvent` constructor drops `propertyName` from its init
 * dict on the floor — it exists and it does not throw, it just hands back an
 * event whose `propertyName` is `undefined`, which would make every assertion
 * below pass or fail for the wrong reason.
 */
const transitionEvent = (type: string, propertyName: string, bubbles = false) =>
  Object.assign(new Event(type, { bubbles }), { propertyName })

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
      // reveal: the delay must still be in force for the reveal itself. It fades
      // the same property, so only its target sets it apart.
      secondCard.firstChild?.dispatchEvent(transitionEvent('transitionend', 'opacity', true))
      expect(secondCard.style.transitionDelay).toBe('80ms')

      // The reveal has now played. Left in place, the delay would also postpone
      // every later transition on this node — the card's own hover.
      secondCard.dispatchEvent(transitionEvent('transitionend', 'opacity'))
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
      secondCard.dispatchEvent(transitionEvent('transitioncancel', 'opacity'))
      expect(secondCard.style.transitionDelay).toBe('')
      wrapper.unmount()
    })

    it('keeps its stagger delay when a transition that is not the reveal ends on the card', () => {
      const wrapper = mount(Grid, { attachTo: document.body })
      vi.advanceTimersByTime(60)
      const secondCard = wrapper.get('[data-testid="card-2"]').element as HTMLElement

      MockIntersectionObserver.instances[0].enter(secondCard)
      expect(secondCard.style.transitionDelay).toBe('80ms')

      // Hardening, not a live bug: main.css's reveal rules are unlayered, so they
      // outrank the `transition-all` utility on the same RouterLink and pin it to
      // `opacity, transform` — a hover moves neither, and cannot reach this
      // handler today. What is pinned here is that the delay belongs to the
      // reveal alone, so that staying true does not depend on a cascade this file
      // cannot see, one @layer away from changing.
      secondCard.dispatchEvent(transitionEvent('transitionend', 'box-shadow'))
      expect(secondCard.style.transitionDelay).toBe('80ms')

      // Only the property the reveal actually fades ends it.
      secondCard.dispatchEvent(transitionEvent('transitionend', 'opacity'))
      expect(secondCard.style.transitionDelay).toBe('')
      wrapper.unmount()
    })

    it('drops its stagger delay even when no transition event ever fires', () => {
      const wrapper = mount(Grid, { attachTo: document.body })
      vi.advanceTimersByTime(60)
      const secondCard = wrapper.get('[data-testid="card-2"]').element as HTMLElement

      MockIntersectionObserver.instances[0].enter(secondCard)
      expect(secondCard.style.transitionDelay).toBe('80ms')

      // The transition events are a fast path, not a guarantee: a reveal that
      // never transitions fires neither. main.css has no reduced-motion block
      // today, so this is latent rather than live — but adding one would zero
      // every reveal's duration, and the marker means a card is revealed once and
      // never revisited. Stranding the delay is therefore permanent, and takes
      // both listeners with it.
      vi.advanceTimersByTime(2000)

      expect(secondCard.style.transitionDelay).toBe('')
      wrapper.unmount()
    })

    it('survives Vue rewriting the class attribute, where a class would not', async () => {
      // The reason the revealed state is a data attribute and not a class. Vue
      // rewrites `class` wholesale when a binding on the node changes — a card's
      // RouterLink gaining `router-link-active` — taking any imperatively-added
      // class with it. This mounts that exact shape rather than mimicking the
      // wipe by hand, so the test fails if the marker ever becomes a class.
      const opened = ref(false)
      const Card = defineComponent({
        setup() {
          const root = ref<HTMLElement | null>(null)
          useScrollReveal(root)
          return () =>
            h('div', { ref: root }, [
              h(
                'a',
                {
                  class: ['stagger-item', opened.value && 'router-link-active'],
                  'data-testid': 'card',
                },
                'Adjoua',
              ),
            ])
        },
      })

      const wrapper = mount(Card, { attachTo: document.body })
      vi.advanceTimersByTime(60)
      const card = wrapper.get('[data-testid="card"]').element as HTMLElement
      MockIntersectionObserver.instances[0].enter(card)

      // Stands in for the class the composable would add if it used one.
      card.classList.add('control-class')
      expect(card.dataset.scrollRevealSeen).toBe('true')

      opened.value = true
      await nextTick()

      expect(card.classList.contains('router-link-active')).toBe(true) // the rewrite happened
      expect(card.classList.contains('control-class')).toBe(false) // a class does not survive it
      expect(card.dataset.scrollRevealSeen).toBe('true') // the marker does
      wrapper.unmount()
    })

    it('stays revealed when the cached listing is deactivated and comes back', async () => {
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

      // Opening a profile deactivates — but does not unmount — the public listing;
      // the app's "Retour aux talents" then brings it back from the cache.
      listingActive.value = false
      await nextTick()
      listingActive.value = true
      await nextTick()

      expect(clickedCard.dataset.scrollRevealSeen).toBe('true')
      expect(unseenCard.dataset.scrollRevealSeen).toBeUndefined()
      // Reactivation is not a reason to rebuild anything: the below-fold card is
      // still registered with the observer built at mount.
      expect(MockIntersectionObserver.instances).toHaveLength(1)
      expect(MockIntersectionObserver.instances[0].observe).toHaveBeenCalledWith(unseenCard)
      wrapper.unmount()
    })

    it('builds no observer once every target has been revealed', () => {
      const wrapper = mount(Grid, { attachTo: document.body })
      vi.advanceTimersByTime(60)
      const [first, second] = ['card-1', 'card-2'].map(
        (id) => wrapper.get(`[data-testid="${id}"]`).element as HTMLElement,
      )

      MockIntersectionObserver.instances[0].enter(first)
      MockIntersectionObserver.instances[0].enter(second)

      // Every card is now seen, and reinit() runs on each fetch a cached listing
      // resolves. With nothing left to watch there is nothing to build.
      exposedReinit?.()
      expect(MockIntersectionObserver.instances).toHaveLength(1)
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

  it('is backed by a stylesheet that reveals on the marker alone', () => {
    // The other half of the mechanism lives in CSS: setting the marker is what
    // makes an element visible, so assert the cascade actually says so rather
    // than that a selector appears in the file. A rule commented out, emptied, or
    // overridden lower down would satisfy a text search and still leave every
    // cached card at opacity 0.
    // (Read through node:url — happy-dom's global URL does not keep the file
    // scheme — and never through cwd, which follows wherever vitest was started.)
    const mainCss = readFileSync(
      join(dirname(fileURLToPath(import.meta.url)), '../../assets/main.css'),
      'utf-8',
    )
    const stylesheet = document.createElement('style')
    stylesheet.textContent = mainCss
    document.head.appendChild(stylesheet)

    try {
      REVEAL_TARGET_CLASSES.forEach((className) => {
        const el = document.createElement('div')
        el.className = className
        document.body.appendChild(el)

        expect(getComputedStyle(el).opacity, `.${className} starts hidden`).toBe('0')
        el.dataset.scrollRevealSeen = 'true'
        expect(getComputedStyle(el).opacity, `.${className} is revealed by the marker`).toBe('1')
      })
    } finally {
      stylesheet.remove()
    }
  })
})
