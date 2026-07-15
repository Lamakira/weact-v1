/**
 * useScrollReveal — timer hygiene and observer reuse.
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
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, h, KeepAlive, nextTick, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { useScrollReveal } from '../useScrollReveal'

class MockIntersectionObserver {
  static instances: MockIntersectionObserver[] = []
  observe = vi.fn()
  unobserve = vi.fn()
  disconnect = vi.fn()
  constructor() {
    MockIntersectionObserver.instances.push(this)
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

  it('makes a cached Face card visible again when the listing is reactivated', async () => {
    const listingActive = ref(true)

    const FacesListing = defineComponent({
      name: 'PublicFacesView',
      setup() {
        useScrollReveal('[data-testid="faces-listing"]')
        return () => h('div', { 'data-testid': 'faces-listing' }, [
          h('a', { class: 'stagger-item', 'data-testid': 'face-card-1' }, 'Adjoua'),
          h('a', { class: 'stagger-item', 'data-testid': 'face-card-2' }, 'Koffi'),
        ])
      },
    })

    const Detail = defineComponent({
      setup: () => () => h('button', { 'data-testid': 'back-to-list' }, 'Retour aux talents'),
    })

    const Harness = defineComponent({
      setup: () => () =>
        h(KeepAlive, { include: ['PublicFacesView'] }, () =>
          listingActive.value ? h(FacesListing) : h(Detail),
        ),
    })

    const wrapper = mount(Harness, { attachTo: document.body })
    await nextTick()
    const clickedCard = wrapper.get('[data-testid="face-card-1"]')
    const unseenCard = wrapper.get('[data-testid="face-card-2"]')
    // The initial KeepAlive activation must preserve observer-driven reveals.
    expect(clickedCard.classes()).not.toContain('is-visible')

    clickedCard.element.classList.add('is-visible')
    ;(clickedCard.element as HTMLElement).dataset.scrollRevealSeen = 'true'
    ;(clickedCard.element as HTMLElement).style.transition = 'all 1s ease'
    ;(clickedCard.element as HTMLElement).style.transitionDelay = '20ms'

    // Opening the profile deactivates (but does not unmount) the public listing.
    listingActive.value = false
    await nextTick()

    // In the browser the reveal state can be lost while the cached node is moved
    // through the route transition's off-DOM storage. Without a reactivation guard,
    // the CSS rule `.stagger-item { opacity: 0 }` makes this one card disappear.
    clickedCard.element.classList.remove('is-visible')

    // The app's "Retour aux talents" navigation reactivates the same cached list.
    listingActive.value = true
    await nextTick()
    await nextTick()

    expect(wrapper.get('[data-testid="face-card-1"]').classes()).toContain('is-visible')
    expect(wrapper.get('[data-testid="face-card-2"]').classes()).not.toContain('is-visible')
    expect(unseenCard.element.getAttribute('data-scroll-reveal-seen')).toBeNull()
    expect((clickedCard.element as HTMLElement).style.transition).toBe('all 1s ease')
    expect((clickedCard.element as HTMLElement).style.transitionDelay).toBe('20ms')
    expect(MockIntersectionObserver.instances).toHaveLength(1)
    expect(MockIntersectionObserver.instances[0].observe).toHaveBeenCalledWith(unseenCard.element)
    expect(MockIntersectionObserver.instances[0].observe).not.toHaveBeenCalledWith(clickedCard.element)
    wrapper.unmount()
  })
})
