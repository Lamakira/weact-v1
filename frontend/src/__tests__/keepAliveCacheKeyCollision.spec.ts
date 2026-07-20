/**
 * Reproduction guard: KeepAliveRouterView collapses EVERY cached route into a
 * SINGLE <keep-alive> cache entry.
 *
 * The shipped template is:
 *
 *   <keep-alive>
 *     <component :is="Component" v-if="route.meta.keepAlive" />
 *   </keep-alive>
 *
 * The Vue compiler turns that `v-if` branch into `createBlock(resolveDynamicComponent(Component), { key: 0 })`
 * — a HARDCODED `key: 0` (verified with compileTemplate; the non-cached sibling
 * below it correctly gets `key: route.path`). Since <KeepAlive> indexes its cache
 * by `vnode.key ?? vnode.type`, every route carrying `meta.keepAlive: true`
 * writes to — and reads from — the same cache slot `0`.
 *
 * Consequence: navigating from cached route A to cached route B hands KeepAlive a
 * vnode whose key already maps to A's cached instance. Vue reuses A's component
 * instance/subtree for B's vnode, then tries to deactivate it through a context
 * that is no longer a KeepAlive host, throwing
 * `TypeError: parentComponent.ctx.deactivate is not a function`, and the DOM stays
 * frozen on A.
 *
 * The existing keep-alive specs never caught this because they all declare a
 * SINGLE `meta.keepAlive` route — one route can't collide with itself. This spec
 * declares TWO.
 *
 * DO NOT "fix" this spec by giving the routes the same component or by dropping
 * one of them: the two distinct cached routes ARE the reproduction.
 *
 * On the Transition stub: these tests rely on @vue/test-utils' DEFAULT Transition
 * stub, deliberately — NOT on `stubs: { Transition: false }`. The bug lives in
 * KeepAlive, not in Transition, and the stub keeps it fully observable (both
 * assertions below fail against the current code, with the exact TypeError).
 * Running the REAL Transition instead adds an unrelated failure mode: with
 * `mode="out-in"` and no CSS in happy-dom, the leave phase does not settle within
 * `flushPromises`, so route B stays unrendered even when the cache key is CORRECT
 * — a false positive that would make this spec fail for the wrong reason. Verified
 * both ways: with the real Transition the spec still fails after keying the branch
 * by `route.path`; with the stub it goes green, so it tracks the cache-key bug and
 * nothing else.
 */
import { describe, it, expect } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import KeepAliveRouterView from '@/components/layout/KeepAliveRouterView.vue'

function setup() {
  const PageA = defineComponent({
    name: 'PageA',
    setup: () => () => h('div', { class: 'page-a' }, 'PAGE_A'),
  })
  const PageB = defineComponent({
    name: 'PageB',
    setup: () => () => h('div', { class: 'page-b' }, 'PAGE_B'),
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/a', name: 'a', component: PageA, meta: { keepAlive: true } },
      { path: '/b', name: 'b', component: PageB, meta: { keepAlive: true } },
    ],
  })

  return { router }
}

/**
 * Vue throws this one from inside a scheduler flush job, so it escapes BOTH
 * `console.error` and `app.config.errorHandler` (verified empirically) and lands
 * on the process-level unhandled channel. Listening here is also what keeps the
 * crash from being reported as a stray "Unhandled Error" by the runner.
 */
async function captureUnhandled(run: () => Promise<void>): Promise<string[]> {
  const captured: string[] = []
  const onError = (error: unknown) => captured.push(String(error))
  process.on('uncaughtException', onError)
  process.on('unhandledRejection', onError)
  try {
    await run()
    // Let node surface anything queued by the flush before we stop listening.
    await new Promise((resolve) => setTimeout(resolve, 20))
  } finally {
    process.off('uncaughtException', onError)
    process.off('unhandledRejection', onError)
  }
  return captured
}

describe('KeepAliveRouterView — cache key collision between two cached routes', () => {
  it('renders route B after navigating from cached route A to cached route B', async () => {
    const { router } = setup()

    router.push('/a')
    await router.isReady()
    const wrapper = mount(KeepAliveRouterView, {
      global: { plugins: [router] },
    })
    await flushPromises()

    // Baseline: A is on screen.
    expect(wrapper.text()).toContain('PAGE_A')

    await router.push('/b')
    await flushPromises()

    // Both routes carry meta.keepAlive, so both land in the same cache slot.
    expect(wrapper.text()).toContain('PAGE_B')
    expect(wrapper.text()).not.toContain('PAGE_A')
  })

  it('does not throw while navigating from cached route A to cached route B', async () => {
    const { router } = setup()

    router.push('/a')
    await router.isReady()
    mount(KeepAliveRouterView, {
      global: { plugins: [router] },
    })
    await flushPromises()

    const errors = await captureUnhandled(async () => {
      await router.push('/b')
      await flushPromises()
    })

    expect(errors.join('\n')).not.toMatch(/deactivate is not a function/)
    expect(errors).toEqual([])
  })
})
