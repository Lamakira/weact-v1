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
import { defineComponent, h } from 'vue'
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
})
