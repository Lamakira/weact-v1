/**
 * Regression guard for the REAL shared KeepAliveRouterView SFC
 * (src/components/layout/KeepAliveRouterView.vue) — the meta-driven keep-alive
 * outlet used by the dashboard/admin layouts (cleanup #12: cache by router
 * `meta.keepAlive`, not a name-based :include, so a rename can't silently
 * disable a cache and generic component names — AdminListPage,
 * MissionsListPage — can't collide). Earlier versions of this spec mounted a
 * local re-copy of the layouts' template, which could drift from the shipped
 * code; it now mounts the extracted SFC itself.
 *
 * The pattern MUST keep <keep-alive> ALWAYS mounted (no v-if on it), with the
 * v-if on the INNER component:
 *
 *   <keep-alive>
 *     <component :is="Component" v-if="route.meta.keepAlive" />
 *   </keep-alive>
 *   <component :is="Component" v-if="!route.meta.keepAlive" :key="route.path" />
 *
 * A tempting-but-wrong variant puts the v-if on <keep-alive> itself — that
 * UNMOUNTS the keep-alive whenever the current route isn't cached (a detail page,
 * the dashboard), wiping the cache, so the "cached" list remounts on return. This
 * test locks in the correct behavior: a flagged route survives a visit to a
 * non-flagged sibling (mounted exactly once), while a non-flagged route remounts.
 *
 * NOTE: the SFC also wraps each sibling in a <transition name="dash-page">.
 * @vue/test-utils stubs Transitions by default, so the transitions don't
 * perturb these mount-count assertions (structural coverage only — the
 * animation itself isn't verifiable here).
 */
import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h, onMounted } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import KeepAliveRouterView from '@/components/layout/KeepAliveRouterView.vue'

function setup() {
  const keptMounted = vi.fn()
  const plainMounted = vi.fn()

  const KeptPage = defineComponent({
    name: 'KeptPage',
    setup: () => {
      onMounted(keptMounted)
      return () => h('div', 'kept')
    },
  })
  const PlainPage = defineComponent({
    name: 'PlainPage',
    setup: () => {
      onMounted(plainMounted)
      return () => h('div', 'plain')
    },
  })
  const OtherPage = defineComponent({ name: 'OtherPage', setup: () => () => h('div', 'other') })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/kept', name: 'kept', component: KeptPage, meta: { keepAlive: true } },
      { path: '/plain', name: 'plain', component: PlainPage },
      { path: '/other', name: 'other', component: OtherPage },
    ],
  })

  return { router, keptMounted, plainMounted }
}

describe('meta.keepAlive-driven keep-alive (KeepAliveRouterView)', () => {
  it('a flagged route is cached across a visit to a non-flagged route (mounted once)', async () => {
    const { router, keptMounted } = setup()

    router.push('/kept')
    await router.isReady()
    mount(KeepAliveRouterView, { global: { plugins: [router] } })
    await flushPromises()
    expect(keptMounted).toHaveBeenCalledTimes(1)

    await router.push('/other') // keep-alive stays mounted → cache preserved
    await flushPromises()

    await router.push('/kept') // restored from cache, NOT remounted
    await flushPromises()
    expect(keptMounted).toHaveBeenCalledTimes(1)
  })

  it('a non-flagged route is NOT cached (remounts on return)', async () => {
    const { router, plainMounted } = setup()

    router.push('/plain')
    await router.isReady()
    mount(KeepAliveRouterView, { global: { plugins: [router] } })
    await flushPromises()
    expect(plainMounted).toHaveBeenCalledTimes(1)

    await router.push('/other')
    await flushPromises()

    await router.push('/plain') // rendered outside keep-alive → fresh mount
    await flushPromises()
    expect(plainMounted).toHaveBeenCalledTimes(2)
  })
})
