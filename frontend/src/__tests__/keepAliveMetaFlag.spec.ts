/**
 * Regression guard for the meta-driven keep-alive used by the dashboard/admin
 * layouts (cleanup #12: cache by router `meta.keepAlive`, not a name-based
 * :include, so a rename can't silently disable a cache and generic component
 * names — AdminListPage, MissionsListPage — can't collide).
 *
 * The pattern MUST keep <keep-alive> ALWAYS mounted (no v-if on it), with the
 * v-if on the INNER component:
 *
 *   <keep-alive>
 *     <component :is="Component" v-if="route.meta.keepAlive" />
 *   </keep-alive>
 *   <component :is="Component" v-if="!route.meta.keepAlive" />
 *
 * A tempting-but-wrong variant puts the v-if on <keep-alive> itself — that
 * UNMOUNTS the keep-alive whenever the current route isn't cached (a detail page,
 * the dashboard), wiping the cache, so the "cached" list remounts on return. This
 * test locks in the correct behavior: a flagged route survives a visit to a
 * non-flagged sibling (mounted exactly once), while a non-flagged route remounts.
 */
import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h, onMounted, KeepAlive } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, RouterView, useRoute } from 'vue-router'

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

  // Mirrors the FaceLayout/ProducerLayout/AdminLayout template: an always-mounted
  // keep-alive whose inner child is gated by route.meta.keepAlive, plus a sibling
  // bare component for non-flagged routes.
  const Layout = defineComponent({
    setup() {
      const route = useRoute()
      return () =>
        h(RouterView, null, {
          default: ({ Component }: { Component: unknown }) => [
            h(KeepAlive, null, () =>
              route.meta.keepAlive && Component ? h(Component as never) : null,
            ),
            !route.meta.keepAlive && Component ? h(Component as never) : null,
          ],
        })
    },
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/kept', name: 'kept', component: KeptPage, meta: { keepAlive: true } },
      { path: '/plain', name: 'plain', component: PlainPage },
      { path: '/other', name: 'other', component: OtherPage },
    ],
  })

  return { Layout, router, keptMounted, plainMounted }
}

describe('meta.keepAlive-driven keep-alive', () => {
  it('a flagged route is cached across a visit to a non-flagged route (mounted once)', async () => {
    const { Layout, router, keptMounted } = setup()

    router.push('/kept')
    await router.isReady()
    mount(Layout, { global: { plugins: [router] } })
    await flushPromises()
    expect(keptMounted).toHaveBeenCalledTimes(1)

    await router.push('/other') // keep-alive stays mounted → cache preserved
    await flushPromises()

    await router.push('/kept') // restored from cache, NOT remounted
    await flushPromises()
    expect(keptMounted).toHaveBeenCalledTimes(1)
  })

  it('a non-flagged route is NOT cached (remounts on return)', async () => {
    const { Layout, router, plainMounted } = setup()

    router.push('/plain')
    await router.isReady()
    mount(Layout, { global: { plugins: [router] } })
    await flushPromises()
    expect(plainMounted).toHaveBeenCalledTimes(1)

    await router.push('/other')
    await flushPromises()

    await router.push('/plain') // rendered outside keep-alive → fresh mount
    await flushPromises()
    expect(plainMounted).toHaveBeenCalledTimes(2)
  })
})
